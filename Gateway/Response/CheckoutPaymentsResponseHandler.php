<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Method\TxVariantFactory;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class CheckoutPaymentsResponseHandler implements HandlerInterface
{
    /**
     * @param Vault $vaultHelper
     * @param PaymentResponseHandler $paymentResponseHandler
     * @param OrdersApi $ordersApi
     * @param TxVariantFactory $txVariantFactory
     */
    public function __construct(
        private readonly Vault $vaultHelper,
        private readonly PaymentResponseHandler $paymentResponseHandler,
        private readonly OrdersApi $ordersApi
    ) { }

    /**
     * @param array $handlingSubject
     * @param array $responseCollection
     * @return void
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $responseCollection): void
    {
        // Always get the last item in the response collection and ignore the partial payments
        $response = end($responseCollection);

        $paymentDataObject = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();

        $payment->getOrder()->setAdyenResulturlEventCode($response['resultCode']);
        $payment->setAdditionalInformation('3dActive', false);

        // Set the transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);
        // Do not close the transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        // Do not close the parent transaction
        $payment->setShouldCloseParentTransaction(false);

        $this->paymentResponseHandler->setPaymentAdditionalInformation($payment, $response);

        if (!empty($response['pspReference'])) {
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);
            $payment->setTransactionId($response['pspReference']);
        }

        // Store Checkout API Order data to be used in case of cancellation after `/payments/details` call.
        if (!empty($this->ordersApi->getCheckoutApiOrder()) &&
            in_array($response['resultCode'], PaymentResponseHandler::ACTION_REQUIRED_STATUSES)) {
            $payment->setAdditionalInformation(
                OrdersApi::DATA_KEY_CHECKOUT_API_ORDER,
                $this->ordersApi->getCheckoutApiOrder()
            );
        }

        // Handle recurring payment details
        $this->vaultHelper->handlePaymentResponseRecurringDetails($payment, $response);

        if ($payment->getMethod() != PaymentMethods::ADYEN_BOLETO) {
            $payment->getOrder()->setCanSendNewEmailFlag(false);
        }
    }
}
