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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class VaultDetailsHandler implements HandlerInterface
{

    /**
     * @var CreditCardTokenFactory
     */
    protected $paymentTokenFactory;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $_adyenLogger;

    /**
     * VaultDetailsHandler constructor.
     *
     * @param CreditCardTokenFactory $paymentTokenFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        CreditCardTokenFactory $paymentTokenFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->_adyenLogger = $adyenLogger;
        $this->paymentTokenFactory = $paymentTokenFactory;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDataObject->getPayment();

        // add vault payment token entity to extension attributes
        $paymentToken = $this->getVaultPaymentToken($response);

        if (null !== $paymentToken) {
            $extensionAttributes = $this->getExtensionAttributes($payment);
            $extensionAttributes->setVaultPaymentToken($paymentToken);
        }
    }


    /**
     * Get vault payment token entity
     *
     * @param array $response
     * @return PaymentTokenInterface|null
     */
    private function getVaultPaymentToken(array $response)
    {
        if (empty($response['additionalData'])) {
            $this->_adyenLogger->error(
                "Missing Response additionalData set please send email to magento@adyen.com to enable vaulting"
            );
            return null;
        }
        $additionalData = $response['additionalData'];

        if (empty($additionalData['recurring.recurringDetailReference'])) {
            $this->_adyenLogger->error(
                "Missing Token in Result please send email to magento@adyen.com
                 to enable the rechargeSynchronousStoreDetails property"
            );
            return null;
        }
        $token = $additionalData['recurring.recurringDetailReference'];


        if (empty($additionalData['cardSummary'])) {
            $this->_adyenLogger->error(
                "Missing cardSummary in Result please login to the adyen portal
                 and go to Settings -> API and Response and enable the Card summary property"
            );
            return null;
        }
        $cardSummary = $additionalData['cardSummary'];

        if (empty($additionalData['expiryDate'])) {
            $this->_adyenLogger->error(
                "Missing expiryDate in Result please login to the adyen portal
                 and go to Settings -> API and Response and enable the Expiry date property"
            );
            return null;
        }
        $expirationDate = $additionalData['expiryDate'];

        if (empty($additionalData['paymentMethod'])) {
            $this->_adyenLogger->error(
                "Missing paymentMethod in Result please login to the 
                adyen portal and go to Settings -> API and Response and enable the Variant property"
            );
            return null;
        }

        $cardType = $additionalData['paymentMethod'];

        try {
            /** @var PaymentTokenInterface $paymentToken */
            $paymentToken = $this->paymentTokenFactory->create();
            $paymentToken->setGatewayToken($token);
            $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));

            $details = [
                'type' => $cardType,
                'maskedCC' => $cardSummary,
                'expirationDate' => $expirationDate
            ];

            $paymentToken->setTokenDetails(json_encode($details));
        } catch (\Exception $e) {
            $this->_adyenLogger->error(print_r($e, true));
        }
        return $paymentToken;
    }


    /**
     * @param $expirationDate
     * @return string
     */
    private function getExpirationDate($expirationDate)
    {
        $expirationDate = explode('/', $expirationDate);

        //add leading zero to month
        $month = sprintf("%02d", $expirationDate[0]);

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
     * @param OrderPaymentInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(OrderPaymentInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();

        return $extensionAttributes;
    }
}
