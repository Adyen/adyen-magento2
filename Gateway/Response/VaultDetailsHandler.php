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

use Adyen\Payment\Helper\Vault;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class VaultDetailsHandler implements HandlerInterface
{
    /**
     * @var Vault
     */
    private $vaultHelper;

    /**
     * VaultDetailsHandler constructor.
     * @param Vault $vaultHelper
     */
    public function __construct(Vault $vaultHelper)
    {
        $this->vaultHelper = $vaultHelper;
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
            if ($paymentToken === null) {
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
                'type' => $additionalData[self::PAYMENT_METHOD]
            ];

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
        /** @var PaymentDataObject $orderPayment */
        $orderPayment = SubjectReader::readPayment($handlingSubject);
        $this->vaultHelper->saveRecurringDetails($orderPayment->getPayment(), $response['additionalData']);
    }
}
