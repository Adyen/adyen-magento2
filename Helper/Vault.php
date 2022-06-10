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

use Adyen\Payment\Api\Data\AdyenPaymentMethodRepositoryInterface;
use Adyen\Payment\Exception\InvalidAdditionalDataException;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodInterface;
use Adyen\Payment\Helper\PaymentMethods\PayPalPaymentMethod;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
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
    const CARD_SUMMARY = 'cardSummary';
    const EXPIRY_DATE = 'expiryDate';
    const PAYMENT_METHOD = 'paymentMethod';
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

    /** @var AdyenPaymentMethodRepositoryInterface */
    private $adyenPaymentMethodRepo;

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
        AdyenPaymentMethodRepositoryInterface $adyenPaymentMethodRepo,
        PaymentMethodFactory $paymentMethodFactory
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->config = $config;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->adyenPaymentMethodRepo = $adyenPaymentMethodRepo;
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
        if (array_key_exists('additionalData', $response) && array_key_exists(self::RECURRING_DETAIL_REFERENCE, $response['additionalData'])) {
            return true;
        }

        return false;
    }

    /**
     * @param $payment
     * @param array $additionalData
     */
    public function saveRecurringDetails($payment, array $additionalData)
    {
        if (!$this->isCardVaultEnabled($payment->getOrder()->getStoreId()) &&
            !$this->adyenHelper->isHppVaultEnabled($payment->getOrder()->getStoreId())) {
            return;
        }

        if (!$this->validateAdditionalData($additionalData)) {
            return;
        }

        try {
            $paymentToken = $this->getVaultPaymentToken($payment, $additionalData);
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
     * Before doing this, validate the additionalData sent by Adyen, based on the params required by the payment method
     *
     * @param $payment
     * @param array $additionalData
     * @param PaymentMethodInterface $adyenPaymentMethod
     * @return PaymentTokenInterface|void|null
     */
    public function saveRecurringPaymentDetails($payment, array $additionalData, PaymentMethodInterface $adyenPaymentMethod)
    {
        try {
            $paymentToken = $this->getVaultPaymentMethodToken($payment, $additionalData, $adyenPaymentMethod);
        } catch (Exception $exception) {
            $this->adyenLogger->error($exception->getMessage());

            return;
        }

        $extensionAttributes = $this->getExtensionAttributes($payment);
        $extensionAttributes->setVaultPaymentToken($paymentToken);

        return $paymentToken;
    }

    /**
     * Build the recurring data when payment is done trough a payment method (not card)
     *
     * @param int $storeId
     * @param $payment
     * @return array
     */
    public function buildPaymentMethodRecurringData(int $storeId, $payment): array
    {
        $request = [];
        $brand = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);

        if (!$this->config->isStoreAlternativePaymentMethodEnabled()) {
            return $request;
        }

        $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($brand);
        try {
            $allowRecurring = $this->allowRecurringOnPaymentMethod($adyenPaymentMethod);
        } catch (NoSuchEntityException $exception) {
            $this->adyenLogger->warning(sprintf('Unable to create object with tx variant %s', $brand));
            return $request;
        }

        if (!$allowRecurring) {
            return $request;
        }

        $recurringModel = $this->config->getAlternativePaymentMethodTokenType($storeId);
        if (isset($recurringModel)) {
            $request['storePaymentMethod'] = true;
            $request['recurringProcessingModel'] = $recurringModel;
        }

        return $request;
    }

    /**
     * Check if recurring should be allowed for payment method by checking if the payment method supports recurring
     * AND if the admin has enabled recurring for this payment method
     *
     * @param PaymentMethodInterface $adyenPaymentMethod
     * @return bool
     * @throws NoSuchEntityException
     */
    public function allowRecurringOnPaymentMethod(PaymentMethodInterface $adyenPaymentMethod): bool
    {
        $methodSupportsRecurring = $adyenPaymentMethod->supportsRecurring();
        $paymentMethodModel = $this->adyenPaymentMethodRepo->getByPaymentMethodName($adyenPaymentMethod->getTxVariant());

        return $methodSupportsRecurring && $paymentMethodModel->getEnableRecurring();
    }

    /**
     * @param $payment
     * @param array $additionalData
     * @param PaymentMethodInterface $adyenPaymentMethod
     * @return PaymentTokenInterface|null
     * @throws InvalidAdditionalDataException
     */
    private function getVaultPaymentMethodToken($payment, array $additionalData, PaymentMethodInterface $adyenPaymentMethod): PaymentTokenInterface
    {
        $requiredAdditionalData = $adyenPaymentMethod->getRequiredAdditionalData();
        $details = [];
        if (!$this->validatePaymentMethodAdditionalData($additionalData, $requiredAdditionalData)) {
            throw new InvalidAdditionalDataException(__('Unable to validate additionalData received for order ' . $payment->getOrder()->getIncrementId()));
        }

        // Check if paymentToken exists already
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $additionalData[self::RECURRING_DETAIL_REFERENCE],
            $payment->getMethodInstance()->getCode(),
            $payment->getOrder()->getCustomerId()
        );

        $paymentTokenSaveRequired = false;

        // In case the payment token does not exist, create it based on the additionalData
        if ($paymentToken === null) {
            $paymentToken = $this->paymentTokenFactory->create(PayPalPaymentMethod::TX_VARIANT);
            $paymentToken->setGatewayToken($additionalData[self::RECURRING_DETAIL_REFERENCE]);
        } else {
            $paymentTokenSaveRequired = true;
        }

        if (array_key_exists(self::EXPIRY_DATE, $additionalData)) {
            $paymentToken->setExpiresAt($this->getExpirationDate($additionalData[self::EXPIRY_DATE]));
        }

        foreach ($requiredAdditionalData as $key) {
            $details[$key] = $additionalData[$key];
        }

        $paymentToken->setTokenDetails(json_encode($details, JSON_FORCE_OBJECT));

        // If the token is updated, it needs to be saved to keep the changes
        if ($paymentTokenSaveRequired) {
            $this->paymentTokenRepository->save($paymentToken);
        }
        return $paymentToken;
    }

    /**
     * @param $payment
     * @param array $additionalData
     * @return PaymentTokenInterface|null
     * @throws Exception
     */
    private function getVaultPaymentToken($payment, array $additionalData): PaymentTokenInterface
    {
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

        $details = ['type' => $additionalData[self::PAYMENT_METHOD]];

        if (!empty($additionalData[self::CARD_SUMMARY])) {
            $details['maskedCC'] =  $additionalData[self::CARD_SUMMARY];
        }

        if (!empty($additionalData[self::EXPIRY_DATE])) {
            $details['expirationDate'] =  $additionalData[self::EXPIRY_DATE];
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
     * @param array $keys
     * @return bool
     */
    private function validatePaymentMethodAdditionalData(array $additionalData, array $keys): bool
    {
        // If additionalData is empty, but some keys are required
        if (empty($additionalData) && !empty($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            if (empty($additionalData[$key])) {
                $this->adyenLogger->error(sprintf('%s not found in additionalData', $key));
                return false;
            }
        }

        return true;
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
}
