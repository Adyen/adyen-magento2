<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
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

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    public function saveRecurringDetails($payment, array $additionalData)
    {
        if (!$this->adyenHelper->isCreditCardVaultEnabled($payment->getOrder()->getStoreId()) &&
            !$this->adyenHelper->isHppVaultEnabled($payment->getOrder()->getStoreId())) {
            return;
        }

        if (!$this->validateAdditionalData($additionalData)) {
            return;
        }

        try {
            $paymentToken = $this->getVaultPaymentToken($payment, $additionalData);
        } catch (Exception $exception) {
            $this->adyenLogger->error(print_r($exception, true));
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

            if (strpos($additionalData[self::PAYMENT_METHOD], "paywithgoogle") !== false
                && !empty($additionalData['paymentMethodVariant'])) {
                $additionalData[self::PAYMENT_METHOD] = $additionalData['paymentMethodVariant'];
                $paymentToken->setIsVisible(false);
            }
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
