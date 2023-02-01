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
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

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

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var Config
     */
    private $config;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Config $config,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->config = $config;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
    }

    /**
     * Check if one click is enabled AND Magento Vault is set
     * intval() is required since "" is returned if config doesn't exist
     *
     * @param null $storeId
     * @return bool
     */
    public function isCardVaultEnabled($storeId = null): bool
    {
        return intval($this->config->getCardRecurringActive($storeId)) && ($this->config->getCardRecurringMode($storeId) === Recurring::MODE_MAGENTO_VAULT);
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
        if (!$this->isCardVaultEnabled($payment->getOrder()->getStoreId()) &&
            !$this->adyenHelper->isHppVaultEnabled($payment->getOrder()->getStoreId())) {
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
     * TODO: Still to add, check configuration to see if pm is actually enabled for tokenization
     *
     */
    public function buildPaymentMethodRecurringData(InfoInterface $payment, int $storeId): array
    {
        $request = [];
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();
        if (!$this->config->isStoreAlternativePaymentMethodEnabled()) {
            return $request;
        }

        $requestRpm = $payment->getAdditionalInformation('recurringProcessingModel');
        $configuredRpm = $this->config->getAlternativePaymentMethodTokenType($storeId);
        $recurringProcessingModel = $requestRpm ?? $configuredRpm;

        if (isset($recurringProcessingModel) && $this->allowRecurringOnPaymentMethod($paymentMethod, $recurringProcessingModel, $storeId)) {
            $request['storePaymentMethod'] = true;
            $request['recurringProcessingModel'] = $recurringProcessingModel;
        }

        return $request;
    }

    /**
     * If payment method supports that specific type recurring
     * AND if the admin has enabled recurring for this payment method
     */
    public function allowRecurringOnPaymentMethod(PaymentMethodInterface $adyenPaymentMethod, string $rpm, ?int $storeId): bool
    {
        $pmSupportsRpm = $this->paymentMethodSupportsRpm($adyenPaymentMethod, $rpm);
        $tokenizablePaymentMethods = array_map(
            'trim',
            explode(',', (string) $this->config->getTokenizedPaymentMethods($storeId))
        );

        $configuredToTokenize = in_array($adyenPaymentMethod->getTxVariant(), $tokenizablePaymentMethods);

        return $pmSupportsRpm && $configuredToTokenize;
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
     * @throws InvalidAdditionalDataException|\Magento\Framework\Exception\LocalizedException
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
            $configuredRpm = $this->config->getAlternativePaymentMethodTokenType($storeId);
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
        // Else, depending if card payment or not, get rpm from the configuration
        if (isset($requestRpm)) {
            $recurringProcessingModel = $payment->getAdditionalInformation('recurringProcessingModel');
        } else {
            $storeId = $payment->getOrder()->getStoreId();
            if ($paymentMethodCode === AdyenCcConfigProvider::CODE) {
                $recurringProcessingModel = $this->config->getCardRecurringType($storeId);
            } elseif ($paymentMethodInstance instanceof PaymentMethodInterface) {
                $recurringProcessingModel = $this->config->getAlternativePaymentMethodTokenType($storeId);
            }
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
        return in_array($recurringProcessingModel, Recurring::getRecurringTypes());
    }

    public function isAdyenPaymentCode(string $paymentCode): bool
    {
        return str_contains($paymentCode, 'adyen_');
    }

    private function paymentMethodSupportsRpm(PaymentMethodInterface $paymentMethod, string $rpm): bool
    {
        if (($rpm === Recurring::SUBSCRIPTION && $paymentMethod->supportsSubscription()) ||
            ($rpm === Recurring::CARD_ON_FILE && $paymentMethod->supportsCardOnFile()) ||
            ($rpm === Recurring::UNSCHEDULED_CARD_ON_FILE && $paymentMethod->supportsUnscheduledCardOnFile())) {
            return true;
        }

        return false;
    }
}
