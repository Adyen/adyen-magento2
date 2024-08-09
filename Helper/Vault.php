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

use Adyen\AdyenException;
use Adyen\Payment\Exception\InvalidAdditionalDataException;
use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\PaymentMethods\AbstractWalletPaymentMethod;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class Vault
{
    const RECURRING_DETAIL_REFERENCE = 'recurring.recurringDetailReference';
    const CARDHOLDER_NAME= 'cardHolderName';
    const CARD_SUMMARY = 'cardSummary';
    const EXPIRY_DATE = 'expiryDate';
    const PAYMENT_METHOD = 'paymentMethod';
    const TOKEN_TYPE = 'tokenType';
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

    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Config $config,
        PaymentMethods $paymentMethodsHelper,
        PaymentMethodFactory $paymentMethodFactory
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->config = $config;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
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

    public function saveRecurringCardDetails(
        $payment,
        array $additionalData,
        AbstractWalletPaymentMethod $paymentMethod = null
    ) {
        if (!$this->isCardVaultEnabled($payment->getOrder()->getStoreId()) &&
            !$this->adyenHelper->isHppVaultEnabled($payment->getOrder()->getStoreId())) {
            return;
        }

        if (!$this->validateAdditionalData($additionalData)) {
            return;
        }

        try {
            $paymentToken = $this->getVaultPaymentToken($payment, $additionalData, $paymentMethod);
        } catch (Exception $exception) {
            $this->adyenLogger->error(json_encode($exception));
            return;
        }

        if (null !== $paymentToken) {
            $extensionAttributes = $this->getExtensionAttributes($payment);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        } else {
            $this->adyenLogger->error(
                sprintf(
                    'Failure trying to save credit card token in vault for order %s',
                    $payment->getOrder()->getIncrementId()
                )
            );
        }
    }

    /**
     * Save recurring details related to the payment method.
     *
     * @param $payment
     * @param array $additionalData
     * @return PaymentTokenInterface|null
     */
    public function saveRecurringPaymentMethodDetails($payment, array $additionalData): ?PaymentTokenInterface
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
     * @param int $storeId
     * @param $brand
     * @param $payment
     * @return array
     */
    public function buildPaymentMethodRecurringData(int $storeId, $brand, $payment): array
    {
        $request = [];
        if (!$this->config->isStoreAlternativePaymentMethodEnabled()) {
            return $request;
        }
        try {
            $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($brand);
            $allowRecurring = $this->allowRecurringOnPaymentMethod($adyenPaymentMethod, $storeId);
        } catch (PaymentMethodException $exception) {
            $this->adyenLogger->error(sprintf('Unable to create payment method with tx variant %s', $brand));
            return $request;
        } catch (NoSuchEntityException $exception) {
            $this->adyenLogger->error(sprintf('Unable to find payment method with tx variant %s', $brand));
            return $request;
        }

        if (!$allowRecurring) {
            return $request;
        }

        $recurringProcessingModel = $payment->getAdditionalInformation('recurringProcessingModel');
        if (isset($recurringProcessingModel)) {
            $request['storePaymentMethod'] = true;
            $request['recurringProcessingModel'] = $recurringProcessingModel;
        } else {
            $recurringProcessingModel = $this->config->getAlternativePaymentMethodTokenType($storeId);
            if (isset($recurringProcessingModel)) {
                $request['storePaymentMethod'] = true;
                $request['recurringProcessingModel'] = $recurringProcessingModel;
            }
        }

        return $request;
    }

    /**
     * Check if recurring should be allowed for payment method by checking:
     * What type of recurring is currently enabled AND if the payment method supports that specific type recurring
     * AND if the admin has enabled recurring for this payment method
     *
     * @param PaymentMethodInterface $adyenPaymentMethod
     * @param int|null $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function allowRecurringOnPaymentMethod(PaymentMethodInterface $adyenPaymentMethod, ?int $storeId): bool
    {
        $currentRecurringTokenSetting = $this->config->getAlternativePaymentMethodTokenType($storeId);
        if ($currentRecurringTokenSetting === Recurring::CARD_ON_FILE) {
            $methodSupportsRecurring = $adyenPaymentMethod->supportsCardOnFile();
        } elseif ($currentRecurringTokenSetting === Recurring::UNSCHEDULED_CARD_ON_FILE) {
            $methodSupportsRecurring = $adyenPaymentMethod->supportsUnscheduledCardOnFile();
        } else {
            $methodSupportsRecurring = $adyenPaymentMethod->supportsSubscription();
        }

        $tokenizedPaymentMethods = $this->config->getTokenizedPaymentMethods($storeId);

        if (isset($tokenizedPaymentMethods)) {
            $tokenizedPaymentMethods = array_map(
                'trim',
                explode(',', $tokenizedPaymentMethods)
            );
            $shouldTokenize = in_array($adyenPaymentMethod->getTxVariant(), $tokenizedPaymentMethods);

            return $methodSupportsRecurring && $shouldTokenize;
        } else {
            return false;
        }
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
     * Before doing this, validate the additionalData sent by Adyen, based on the params required by the payment method
     *
     * @throws InvalidAdditionalDataException
     */
    private function createVaultAccountToken($payment, array $additionalData): PaymentTokenInterface
    {
        // Check if paymentToken exists already
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $additionalData[self::RECURRING_DETAIL_REFERENCE],
            $payment->getMethodInstance()->getCode(),
            $payment->getOrder()->getCustomerId()
        );

        // In case the payment token does not exist, create it based on the additionalData
        if (is_null($paymentToken)) {
            $recurringProcessingModel = $payment->getAdditionalInformation('recurringProcessingModel');
            if (is_null($recurringProcessingModel)) {
                $storeId = $payment->getOrder()->getStoreId();
                $recurringProcessingModel = $this->config->getAlternativePaymentMethodTokenType($storeId);
            }

            $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT);
            $paymentToken->setGatewayToken($additionalData[self::RECURRING_DETAIL_REFERENCE]);
            $expiryDate = new DateTime();
            $expiryDate->add(new DateInterval('P1Y'));
            $paymentToken->setExpiresAt($expiryDate);
            $details = [
                'type' => $payment->getCcType(),
                self::TOKEN_TYPE => $recurringProcessingModel
            ];

            $paymentToken->setTokenDetails(json_encode($details, JSON_FORCE_OBJECT));
        }

        return $paymentToken;
    }

    /**
     * @throws Exception
     */
    private function getVaultPaymentToken(
        $payment,
        array $additionalData,
        AbstractWalletPaymentMethod $paymentMethod = null
    ): PaymentTokenInterface {
        // Check if paymentToken exists already
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $additionalData[self::RECURRING_DETAIL_REFERENCE],
            $payment->getMethodInstance()->getCode(),
            $payment->getOrder()->getCustomerId()
        );

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

        if (isset($paymentMethod)) {
            $details = [
                'type' => $paymentMethod->getCardScheme(),
                'walletType' => $paymentMethod->getTxVariant()
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

        // Set token type (alternative payment methods) for card tokens created using googlepay, applepay.
        // This will be done for all card tokens once all vault changes are implemented
        if ($payment->getAdditionalInformation('recurringProcessingModel')) {
            $recurringModel = $payment->getAdditionalInformation('recurringProcessingModel');
        } elseif ($payment->getMethodInstance()->getCode() === AdyenHppConfigProvider::CODE) {
            $storeId = $payment->getOrder()->getStoreId();
            $recurringModel = $this->config->getAlternativePaymentMethodTokenType($storeId);
        } elseif ($payment->getMethodInstance()->getCode() === AdyenCcConfigProvider::CODE) {
            $storeId = $payment->getOrder()->getStoreId();
            $recurringModel = $this->config->getCardRecurringType($storeId);
        }

        if (isset($recurringModel)) {
            $details[self::TOKEN_TYPE] = $recurringModel;
            // Set token cardHolderName for new Visa compliance requirements
            if ($additionalData[self::CARDHOLDER_NAME] !== null) {
                $details[self::CARDHOLDER_NAME] = $additionalData[self::CARDHOLDER_NAME];
            }
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
        $expirationDate = explode('/', $expirationDate);

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
}
