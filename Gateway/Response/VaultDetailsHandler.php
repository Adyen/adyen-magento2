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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2019 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

class VaultDetailsHandler implements HandlerInterface
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
     * @var PaymentTokenFactoryInterface
     */
    protected $paymentTokenFactory;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Data
     */
    private $adyenHelper;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var
     */
    private $paymentTokenRepository;

    /**
     * VaultDetailsHandler constructor.
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param AdyenLogger $adyenLogger
     * @param Data $adyenHelper
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        AdyenLogger $adyenLogger,
        Data $adyenHelper,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        /** @var PaymentDataObject $orderPayment */
        $orderPayment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $payment = $orderPayment->getPayment();

        if ($this->adyenHelper->isCreditCardVaultEnabled($payment->getOrder()->getStoreId())) {
            // add vault payment token entity to extension attributes
            $paymentToken = $this->getVaultPaymentToken($response, $payment);

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
    }

    /**
     * Get vault payment token entity
     *
     * @param array $response
     * @param $payment
     * @return PaymentTokenInterface|null
     */
    private function getVaultPaymentToken(array $response, $payment)
    {

        if (empty($response['additionalData'])) {
            return null;
        }

        $additionalData = $response['additionalData'];

        $paymentToken = null;

        foreach (self::ADDITIONAL_DATA_ERRORS as $key => $errorMsg) {
            if (empty($additionalData[$key])) {
                $this->adyenLogger->error($errorMsg);
                return null;
            }
        }

        try {
            // Check if paymentToken exists already
            $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
                $additionalData[self::RECURRING_DETAIL_REFERENCE],
                $payment->getMethodInstance()->getCode(),
                $payment->getOrder()->getCustomerId()
            );

            $paymentTokenSaveRequired = false;

            // In case the payment token does not exist, create it based on the additionalData
            if (is_null($paymentToken)) {
                /** @var PaymentTokenInterface $paymentToken */
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

            $details = [
                'type' => $additionalData[self::PAYMENT_METHOD],
                'maskedCC' => $additionalData[self::CARD_SUMMARY],
                'expirationDate' => $additionalData[self::EXPIRY_DATE]
            ];

            $paymentToken->setTokenDetails(json_encode($details));

            // If the token is updated, it needs to be saved to keep the changes
            if ($paymentTokenSaveRequired) {
                $this->paymentTokenRepository->save($paymentToken);
            }
        } catch (\Exception $e) {
            $this->adyenLogger->error(print_r($e, true));
        }

        return $paymentToken;
    }

    /**
     * @param $expirationDate
     * @return string
     * @throws \Exception
     */
    private function getExpirationDate($expirationDate)
    {
        $expirationDate = explode('/', $expirationDate);

        //add leading zero to month
        $month = sprintf('%02d', $expirationDate[0]);

        $expDate = new \DateTime(
            $expirationDate[1]
            . '-'
            . $month
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );

        // add one month
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * Get payment extension attributes
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
