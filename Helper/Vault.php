<?php
/**
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2022 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Exception\InvalidAdditionalDataException;
use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Model\Method\TxVariant;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\AdyenPaymentMethod;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class Vault
{
    const RECURRING_DETAIL_REFERENCE = 'recurring.recurringDetailReference';
    const CARD_SUMMARY = 'cardSummary';
    const EXPIRY_DATE = 'expiryDate';
    const PAYMENT_METHOD = 'paymentMethod';
    const TOKEN_TYPE = 'tokenType';
    const TOKEN_LABEL = 'tokenLabel';
    const ADDITIONAL_DATA_ERRORS = [
        self::RECURRING_DETAIL_REFERENCE => 'Missing Token in Result please enable in ' .
            'Settings -> API URLs and Response menu in the Adyen Customer Area Recurring details setting',
        self::CARD_SUMMARY => 'Missing cardSummary in Result please login to the adyen portal ' .
            'and go to Settings -> API URLs and Response and enable the Card summary property',
        self::EXPIRY_DATE => 'Missing expiryDate in Result please login to the adyen portal and go to ' .
            'Settings -> API URLs and Response and enable the Expiry date property',
        self::PAYMENT_METHOD => 'Missing paymentMethod in Result please login to the adyen portal and go to ' .
            'Settings -> API URLs and Response and enable the Variant property'
    ];
    const CARD_ON_FILE = 'CardOnFile';
    const SUBSCRIPTION = 'Subscription';
    const UNSCHEDULED_CARD_ON_FILE = 'UnscheduledCardOnFile';
    const MODE_MAGENTO_VAULT = 'Magento Vault';
    const MODE_ADYEN_TOKENIZATION = 'Adyen Tokenization';

    private AdyenLogger $adyenLogger;
    private PaymentTokenManagement $paymentTokenManagement;
    private PaymentTokenFactoryInterface $paymentTokenFactory;
    private PaymentTokenRepositoryInterface $paymentTokenRepository;
    private Config $config;

    public function __construct(
        AdyenLogger $adyenLogger,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Config $config
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->config = $config;
    }

    /**
     * @return string[]
     */
    public static function getRecurringTypes(): array
    {
        return [
            self::CARD_ON_FILE,
            self::SUBSCRIPTION,
            self::UNSCHEDULED_CARD_ON_FILE
        ];
    }

    public function getPaymentMethodRecurringActive(string $paymentMethodCode, int $storeId): ?bool
    {
        $recurringConfiguration = $this->config->getConfigData(
            Config::XML_RECURRING_CONFIGURATION,
            Config::XML_ADYEN_ABSTRACT_PREFIX, $storeId
        );

        if (!isset($recurringConfiguration)) {
            return false;
        } else {
            $recurringConfiguration = json_decode($recurringConfiguration, true);
            return isset($recurringConfiguration[$paymentMethodCode]['enabled']) &&
                $recurringConfiguration[$paymentMethodCode]['enabled'];
        }
    }

    public function getPaymentMethodRecurringProcessingModel(string $paymentMethodCode, int $storeId): ?string
    {
        $recurringConfiguration = $this->config->getConfigData(
            Config::XML_RECURRING_CONFIGURATION,
            Config::XML_ADYEN_ABSTRACT_PREFIX, $storeId
        );

        if (!isset($recurringConfiguration)) {
            return null;
        } else {
            $recurringConfiguration = json_decode($recurringConfiguration, true);
            return $recurringConfiguration[$paymentMethodCode]['recurringProcessingModel'] ?? null;
        }
    }

    /**
     * @param array $response
     * @return bool
     */
    public function hasRecurringDetailReference(array $response): bool
    {
        if (array_key_exists('additionalData', $response) &&
            array_key_exists(self::RECURRING_DETAIL_REFERENCE, $response['additionalData'])) {
            return true;
        }

        return false;
    }

    /**
     * @throws LocalizedException
     */
    public function saveRecurringDetails(OrderPaymentInterface $payment, array $additionalData): void
    {
        $payment->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, true);
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();
        if ($paymentMethod->isWallet()) {
            $this->saveRecurringCardDetails($payment, $additionalData);
        } else {
            $this->saveRecurringPaymentMethodDetails($payment, $additionalData);
        }
    }

    /**
     * Save token in vault for card payments OR payments done using wallet payment methods (googlepay)
     */
    public function saveRecurringCardDetails(OrderPaymentInterface $payment, array $additionalData): ?PaymentTokenInterface {
        if (!$this->getPaymentMethodRecurringActive($payment->getMethod(), $payment->getOrder()->getStoreId())) {
            return null;
        }

        if (!$this->validateAdditionalData($additionalData)) {
            return null;
        }

        try {
            $paymentToken = $this->getVaultCardToken($payment, $additionalData);
        } catch (Exception $exception) {
            $this->adyenLogger->error(
                sprintf(
                    'Failure trying to save card token in vault for order %s, with exception message %s',
                    $payment->getOrder()->getIncrementId(),
                    $exception->getMessage()
                )
            );

            return null;
        }

        $extensionAttributes = $this->getExtensionAttributes($payment);
        $extensionAttributes->setVaultPaymentToken($paymentToken);

        return $paymentToken;
    }

    /**
     * Save token in vault for non-card and non-wallet payment methods
     *
     */
    public function saveRecurringPaymentMethodDetails(OrderPaymentInterface $payment, array $additionalData): ?PaymentTokenInterface
    {
        try {
            $paymentToken = $this->createVaultAccountToken($payment, $additionalData);
            $extensionAttributes = $this->getExtensionAttributes($payment);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        } catch (PaymentMethodException $e) {
            $this->adyenLogger->error(sprintf(
                'Unable to create token for order %s',
                $payment->getOrder()->getEntityId()
            ));

            return null;
        } catch (Exception $exception) {
            $this->adyenLogger->error($exception->getMessage());

            return null;
        }

        return $paymentToken;
    }

    /**
     * Build the recurring data when payment is done through a payment method (not card)
     *
     */
    public function buildPaymentMethodRecurringData(InfoInterface $payment, int $storeId): array
    {
        $request = [];
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();
        if (!$this->getPaymentMethodRecurringActive($paymentMethod->getCode(), $storeId)) {
            return $request;
        }

        $requestRpm = $payment->getAdditionalInformation('recurringProcessingModel');
        $configuredRpm = $this->getPaymentMethodRecurringProcessingModel($paymentMethod->getCode(), $storeId);

        $recurringProcessingModel = $requestRpm ?? $configuredRpm;

        if (isset($recurringProcessingModel) &&
            $this->paymentMethodSupportsRpm($paymentMethod, $recurringProcessingModel)) {
            $request['storePaymentMethod'] = true;
            $request['recurringProcessingModel'] = $recurringProcessingModel;
        }

        return $request;
    }

    /**
     * Return the Adyen token type (CardOnFile/Subscription)
     * If it does not exist (token was created in an older version) return null
     *
     * @param PaymentTokenInterface $paymentToken
     * @return string|null
     */
    public function getAdyenTokenType(PaymentTokenInterface $paymentToken): ?string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        if (array_key_exists(self::TOKEN_TYPE, $details)) {
            return $details[self::TOKEN_TYPE];
        }

        return null;
    }

    /**
     * Create an entry in the vault table w/type=Account (for pms such as PayPal)
     * If the token has already been created, do nothing
     *
     * @throws InvalidAdditionalDataException
     */
    private function createVaultAccountToken(OrderPaymentInterface $payment, array $additionalData): PaymentTokenInterface
    {
        /** @var AdyenPaymentMethod $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();
        // Check if paymentToken exists already
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $additionalData[self::RECURRING_DETAIL_REFERENCE],
            $paymentMethod->getCode(),
            $payment->getOrder()->getCustomerId()
        );

        // In case the payment token does not exist, create it based on the additionalData
        if (is_null($paymentToken)) {
            $storeId = $payment->getOrder()->getStoreId();
            $requestRpm = $payment->getAdditionalInformation('recurringProcessingModel');
            $configuredRpm = $this->getPaymentMethodRecurringProcessingModel($paymentMethod->getCode(), $storeId);

            $recurringProcessingModel = $requestRpm ?? $configuredRpm;

            $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
            $paymentToken->setGatewayToken($additionalData[self::RECURRING_DETAIL_REFERENCE]);
            $today = new DateTime();

            $details = [
                'type' => $payment->getCcType(),
                self::TOKEN_TYPE => $recurringProcessingModel,
                self::TOKEN_LABEL => $paymentMethod->getPaymentMethodName() . ' token created on ' . $today->format('Y-m-d')
            ];

            $today->add(new DateInterval('P1Y'));
            $paymentToken->setExpiresAt($today);

            $paymentToken->setTokenDetails(json_encode($details, JSON_FORCE_OBJECT));
        }

        return $paymentToken;
    }

    /**
     * @throws Exception
     */
    private function getVaultCardToken(OrderPaymentInterface $payment, array $additionalData): PaymentTokenInterface {
        // Check if paymentToken exists already
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $additionalData[self::RECURRING_DETAIL_REFERENCE],
            $payment->getMethodInstance()->getCode(),
            $payment->getOrder()->getCustomerId()
        );

        $paymentMethodInstance = $payment->getMethodInstance();
        $paymentMethodCode = $paymentMethodInstance->getCode();
        $paymentTokenSaveRequired = false;

        // In case the payment token does not exist, create it based on the additionalData
        if ($paymentToken === null) {
            $paymentToken = $this->paymentTokenFactory->create(
                PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
            );
            $paymentToken->setGatewayToken($additionalData[self::RECURRING_DETAIL_REFERENCE]);
        } else {
            $paymentTokenSaveRequired = true;
        }

        $paymentToken->setExpiresAt($this->getExpirationDate($additionalData[self::EXPIRY_DATE]));

        // If wallet payment method
        if ($paymentMethodCode !== PaymentMethods::ADYEN_CC && $paymentMethodInstance instanceof PaymentMethodInterface) {
            $txVariant = new TxVariant($payment->getCcType());
            $details = [
                'type' => $txVariant->getCard(),
                'walletType' => $txVariant->getPaymentMethod()
            ];
        } else {
            $details = ['type' => $additionalData[self::PAYMENT_METHOD]];
        }

        if (!empty($additionalData[self::CARD_SUMMARY])) {
            $details['maskedCC'] =  $additionalData[self::CARD_SUMMARY];
        }

        if (!empty($additionalData[self::EXPIRY_DATE])) {
            $details['expirationDate'] =  $additionalData[self::EXPIRY_DATE];
        }

        $requestRpm = $payment->getAdditionalInformation('recurringProcessingModel');

        // If rpm was included in initial request, use it
        // Else, get it from configuration.
        if (isset($requestRpm)) {
            $recurringProcessingModel = $payment->getAdditionalInformation('recurringProcessingModel');
        } else {
            $storeId = $payment->getOrder()->getStoreId();
            $recurringProcessingModel = $this->getPaymentMethodRecurringProcessingModel($paymentMethodCode, $storeId);
        }

        if (isset($recurringProcessingModel)) {
            $details[self::TOKEN_TYPE] = $recurringProcessingModel;
        }

        $paymentToken->setTokenDetails(json_encode($details));

        // If the token is updated, it needs to be saved to keep the changes
        if ($paymentTokenSaveRequired) {
            $this->paymentTokenRepository->save($paymentToken);
        }

        return $paymentToken;
    }

    /**
     * @param array $additionalData
     * @return bool
     */
    private function validateAdditionalData(array $additionalData)
    {
        if (empty($additionalData)) {
            return false;
        }

        foreach (self::ADDITIONAL_DATA_ERRORS as $key => $errorMsg) {
            if (empty($additionalData[$key])) {
                $this->adyenLogger->error($errorMsg);
                return false;
            }
        }

        return true;
    }

    /**
     * @param $expirationDate
     * @return string
     * @throws Exception
     */
    private function getExpirationDate($expirationDate)
    {
        $expirationDate = explode('/', (string) $expirationDate);

        $expDate = new DateTime(
        //add leading zero to month
            sprintf("%s-%02d-01 00:00:00", $expirationDate[1], $expirationDate[0]),
            new DateTimeZone('UTC')
        );

        // add one month
        $expDate->add(new DateInterval('P1M'));
        return $expDate->format('Y-m-d H:i:s');
    }

    /**
     * Get payment extension attributes
     *
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * @param string $recurringProcessingModel
     * @return bool
     */
    public function validateRecurringProcessingModel(string $recurringProcessingModel): bool
    {
        return in_array($recurringProcessingModel, self::getRecurringTypes());
    }

    public function isAdyenPaymentCode(string $paymentCode): bool
    {
        return str_contains($paymentCode, 'adyen_');
    }

    public function paymentMethodSupportsRpm(PaymentMethodInterface|MethodInterface $paymentMethod, string $rpm): bool
    {
        if (($rpm === Vault::SUBSCRIPTION && $paymentMethod->supportsSubscription()) ||
            ($rpm === Vault::CARD_ON_FILE && $paymentMethod->supportsCardOnFile()) ||
            ($rpm === Vault::UNSCHEDULED_CARD_ON_FILE && $paymentMethod->supportsUnscheduledCardOnFile())) {
            return true;
        }

        return false;
    }

    /**
     * @param Payment $payment
     * @param array $response
     * @return void
     * @throws LocalizedException
     */
    public function handlePaymentResponseRecurringDetails(Payment $payment, array $response): void
    {
        $paymentMethodInstance = $payment->getMethodInstance();

        if ($this->hasRecurringDetailReference($response)) {
            $storeId = $paymentMethodInstance->getStore();
            $canStorePaymentMethods = $this->getPaymentMethodRecurringActive(
                $paymentMethodInstance->getCode(),
                $storeId
            );

            $cardTokenizationEnabled = $this->getPaymentMethodRecurringActive(
                AdyenCcConfigProvider::CODE,
                $storeId
            );

            // If payment method is NOT card
            // Else if card
            if ($canStorePaymentMethods && $paymentMethodInstance instanceof PaymentMethodInterface) {
                try {
                    $this->saveRecurringDetails($payment, $response['additionalData']);
                } catch (PaymentMethodException $e) {
                    $this->adyenLogger->error(sprintf(
                        'Unable to create payment method with tx variant %s in details handler',
                        $response['additionalData']['paymentMethod']
                    ));
                }
            } elseif ($cardTokenizationEnabled && $paymentMethodInstance->getCode() === AdyenCcConfigProvider::CODE) {
                $this->saveRecurringCardDetails($payment, $response['additionalData']);
            }
        }
    }
}
