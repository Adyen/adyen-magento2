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
    private $adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * VaultDetailsHandler constructor.
     *
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
        $this->paymentTokenFactory = $paymentTokenFactory;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var \Adyen\Payment\Api\Data\OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        if ($this->adyenHelper->isCreditCardVaultEnabled($payment->getOrder()->getStoreId())) {
            // add vault payment token entity to extension attributes
            $paymentToken = $this->getVaultPaymentToken($response);

            if (null !== $paymentToken) {
                $extensionAttributes = $this->getExtensionAttributes($payment);
                $extensionAttributes->setVaultPaymentToken($paymentToken);
            }
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
        $paymentToken = null;

        if (!empty($response['additionalData'])) {
            $additionalData = $response['additionalData'];


            if (empty($additionalData['recurring.recurringDetailReference'])) {
                $this->adyenLogger->error(
                    'Missing Token in Result please enable in ' .
                    'Settings -> API URLs and Response menu in the Adyen Customer Area Recurring details setting'
                );
                return null;
            }
            $token = $additionalData['recurring.recurringDetailReference'];


            if (empty($additionalData['cardSummary'])) {
                $this->adyenLogger->error(
                    'Missing cardSummary in Result please login to the adyen portal ' .
                    'and go to Settings -> API URLs and Response and enable the Card summary property'
                );
                return null;
            }
            $cardSummary = $additionalData['cardSummary'];

            if (empty($additionalData['expiryDate'])) {
                $this->adyenLogger->error(
                    'Missing expiryDate in Result please login to the adyen portal and go to ' .
                    'Settings -> API URLs and Response and enable the Expiry date property'
                );
                return null;
            }
            $expirationDate = $additionalData['expiryDate'];

            if (empty($additionalData['paymentMethod'])) {
                $this->adyenLogger->error(
                    'Missing paymentMethod in Result please login to the adyen portal and go to ' .
                    'Settings -> API URLs and Response and enable the Variant property'
                );
                return null;
            }

            $cardType = $additionalData['paymentMethod'];

            try {
                /** @var PaymentTokenInterface $paymentToken */
                $paymentToken = $this->paymentTokenFactory->create(
                    PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                );

                if (strpos($cardType, "paywithgoogle") !== false && !empty($additionalData['paymentMethodVariant'])) {
                    $cardType = $additionalData['paymentMethodVariant'];
                    $paymentToken->setIsVisible(false);
                }
                $paymentToken->setGatewayToken($token);
                $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));

                $details = [
                    'type' => $cardType,
                    'maskedCC' => $cardSummary,
                    'expirationDate' => $expirationDate
                ];

                $paymentToken->setTokenDetails(json_encode($details));
            } catch (\Exception $e) {
                $this->adyenLogger->error(print_r($e, true));
            }
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