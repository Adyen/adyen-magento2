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
    public function __construct(
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        $this->_adyenLogger = $adyenLogger;
        $this->_adyenHelper = $adyenHelper;
    }

	/**
	 * @param array $handlingSubject
	 * @param array $response
	 */
	public function handle(array $handlingSubject, array $response)
	{
		$payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

		/** @var OrderPaymentInterface $payment */
		$payment = $payment->getPayment();

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

		if (!empty($response['additionalData']) && !empty($response['additionalData']['recurring.recurringDetailReference'])){
            $order = $payment->getOrder();
		   
		    $this->_adyenHelper->createAdyenBillingAgreement($order, $response['additionalData']);
        }

		// do not close transaction so you can do a cancel() and void
		$payment->setIsTransactionClosed(false);
		$payment->setShouldCloseParentTransaction(false);

	}
}
