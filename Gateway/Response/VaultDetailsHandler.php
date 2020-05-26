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
use Magento\Vault\Model\PaymentTokenManagement;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;

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
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        PaymentTokenFactoryInterface $paymentTokenFactory,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
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
        /** @var @var PaymentDataObject $orderPayment */
        $orderPayment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $payment = $orderPayment->getPayment();

        if ($this->adyenHelper->isCreditCardVaultEnabled($payment->getOrder()->getStoreId())) {
            // add vault payment token entity to extension attributes
            $paymentToken = $this->getVaultPaymentToken($response, $payment);

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
     * @param $payment
     * @return PaymentTokenInterface|null
     */
    private function getVaultPaymentToken(array $response, $payment)
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

                // Check if paymentToken exists already
                $paymentToken = $this->paymentTokenManagement->getByGatewayToken($token, $payment->getMethodInstance()->getCode(), $payment->getOrder()->getCustomerId());

                $paymentTokenSaveRequired = false;

                // In case the payment token does not exist, create it based on the additionalData
                if (is_null($paymentToken))  {
                    /** @var PaymentTokenInterface $paymentToken */
                    $paymentToken = $this->paymentTokenFactory->create(
                        PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD
                    );

                    $paymentToken->setGatewayToken($token);

                    if (strpos($cardType, "paywithgoogle") !== false && !empty($additionalData['paymentMethodVariant'])) {
                        $cardType = $additionalData['paymentMethodVariant'];
                        $paymentToken->setIsVisible(false);
                    }
                } else {
                    $paymentTokenSaveRequired = true;
                }

                $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));

                $details = [
                    'type' => $cardType,
                    'maskedCC' => $cardSummary,
                    'expirationDate' => $expirationDate
                ];

                $paymentToken->setTokenDetails(json_encode($details));

                // If the token is updated, it needs to be saved to keep the changes
                if ($paymentTokenSaveRequired) {
                    $this->paymentTokenRepository->save($paymentToken);
                }
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