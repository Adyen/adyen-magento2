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

use Magento\Payment\Gateway\Response\HandlerInterface;

class CheckoutPaymentsDetailsHandler implements HandlerInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * This is being used for all checkout methods (adyen hpp payment method)
     *
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        $payment = $paymentDataObject->getPayment();

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // no not send order confirmation mail
        $payment->getOrder()->setCanSendNewEmailFlag(false);

        if (!empty($response['pspReference'])) {
            // set pspReference as transactionId
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);

            // set transaction
            $payment->setTransactionId($response['pspReference']);
        }

        if (!empty($response['additionalData']['recurring.recurringDetailReference']) &&
            !$this->adyenHelper->isCreditCardVaultEnabled() &&
            $payment->getMethodInstance()->getCode() !== \Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider::CODE
        ) {
            $order = $payment->getOrder();
            $this->adyenHelper->createAdyenBillingAgreement($order, $response['additionalData']);
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
