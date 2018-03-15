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

use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;

class VaultDetailsHandler implements HandlerInterface
{

    /**
     * @var PaymentTokenInterfaceFactory
     */
    protected $paymentTokenFactory;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    private $_adyenLogger;

    /**
     * VaultDetailsHandler constructor.
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    )
    {
        $this->_adyenLogger = $adyenLogger;

        $this->_adyenLogger->error("Constructor1");
        $this->_adyenLogger->addNotificationLog("Constructor1");

        $this->_adyenLogger->error("Constructo2r");
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->_adyenLogger->error("Constructor2");
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {

        $this->_adyenLogger->error("Test handle Adyen NEWWW");

//        $this->_adyenLogger->error(print_r($response, true));

        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $this->_adyenLogger->error("Class Payment" . get_class($payment));


        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        $this->_adyenLogger->error("Class Payment2" . get_class($payment));

        $this->_adyenLogger->error("Test handle Adyen 2");

        // add vault payment token entity to extension attributes
        $paymentToken = $this->getVaultPaymentToken($response);


        if (null !== $paymentToken) {
            $this->_adyenLogger->error("Test handle Adyen 3");
            $extensionAttributes = $this->getExtensionAttributes($payment);
            $this->_adyenLogger->error("Test handle Adyen 4");
            $extensionAttributes->setVaultPaymentToken($paymentToken);
            $this->_adyenLogger->error("Test handle Adyen 5");
        }


        $this->_adyenLogger->error("Test handle Adyen 6");
    }


    /**
     * Get vault payment token entity
     *
     * @param array $response
     * @return PaymentTokenInterface|null
     */
    private function getVaultPaymentToken(array $response)
    {
        $this->_adyenLogger->error("getVaultPaymentToken");
        $additionalData = $response['additionalData'];

        if(empty($additionalData['recurring.recurringDetailReference'])) {
            $this->_adyenLogger->error("Missing Token in Result please send email to magento@adyen.com to enable the rechargeSynchronousStoreDetails property");
            return null;
        }
        $token = $additionalData['recurring.recurringDetailReference'];


        if (empty($additionalData['cardSummary'])) {
            $this->_adyenLogger->error("Missing cardSummary in Result please login to the adyen portal and go to Settings -> API and Response and enable the Card summary property");
            return null;
        }
        $cardSummary = $additionalData['cardSummary'];

        if (empty($additionalData['expiryDate'])) {
            $this->_adyenLogger->error("Missing expiryDate in Result please login to the adyen portal and go to Settings -> API and Response and enable the Expiry date property");
            return null;
        }
        $expirationDate = $additionalData['expiryDate'];

        if (empty($additionalData['paymentMethod'])) {
            $this->_adyenLogger->error("Missing paymentMethod in Result please login to the adyen portal and go to Settings -> API and Response and enable the Variant property");
            return null;
        }

        // do we need to convert this ???
        $cardType = $additionalData['paymentMethod'];

        //additional data then recurring.recurringDetailReference

        $this->_adyenLogger->error("getVaultPaymentToken2");

        try {

            $this->_adyenLogger->error("getVaultPaymentToken3");

            /** @var PaymentTokenInterface $paymentToken */
            $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
            $paymentToken->setGatewayToken($token);
            $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));

            $this->_adyenLogger->error("getVaultPaymentToken4");

            $details = [
                'type' => $cardType,
                'maskedCC' => $cardSummary,
                'expirationDate' => $expirationDate
            ];

            $this->_adyenLogger->error(print_r($details, true));

            $this->_adyenLogger->error("getVaultPaymentToken5");

            $paymentToken->setTokenDetails(json_encode($details));

            $this->_adyenLogger->error(json_encode($details));

            $this->_adyenLogger->error("getVaultPaymentToken6");
        } catch(Exception $e) {
            $this->_adyenLogger->error("EXCEPTION!");
            $this->_adyenLogger->error(print_r($e, true));
        }

        $this->_adyenLogger->error("getVaultPaymentToken before end");
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
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $this->_adyenLogger->error("getExtensionAttributes 1");
        $extensionAttributes = $payment->getExtensionAttributes();
        $this->_adyenLogger->error("getExtensionAttributes 2");
        if (null === $extensionAttributes) {
            $this->_adyenLogger->error("getExtensionAttributes 3");
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $this->_adyenLogger->error("getExtensionAttributes 4");
            $payment->setExtensionAttributes($extensionAttributes);
            $this->_adyenLogger->error("getExtensionAttributes 5");
        }
        return $extensionAttributes;
    }


}