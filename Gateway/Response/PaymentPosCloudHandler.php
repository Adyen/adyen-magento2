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
 * Copyright (c) 2018 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;


use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Setup\Exception;

class PaymentPosCloudHandler implements HandlerInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

    /**
     * @var
     */
    private $adyenLogger;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * Handles response
     *
     * @param array $handlingSubject
     * @param array $paymentResponse
     * @return void
     * @throws Exception
     */
    public function handle(array $handlingSubject, array $paymentResponse)
    {
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var OrderPaymentInterface $payment */
        $payment = $payment->getPayment();

        // set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // do not send order confirmation mail
        $payment->getOrder()->setCanSendNewEmailFlag(false);

        // set transaction(status)
        if (!empty($paymentResponse) && !empty($paymentResponse['PaymentResult']['PaymentAcquirerData']['AcquirerTransactionID']['TransactionID'])) {
            $pspReference = $paymentResponse['PaymentResult']['PaymentAcquirerData']['AcquirerTransactionID']['TransactionID'];
            $payment->setTransactionId($pspReference);
            // set transaction(payment)
        } else {
            $this->adyenLogger->error("Missing POS Transaction ID");
            throw new Exception("Missing POS Transaction ID");
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
