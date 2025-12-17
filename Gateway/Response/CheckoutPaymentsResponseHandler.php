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
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class CheckoutPaymentsResponseHandler implements HandlerInterface
{
    /**
     * @param Vault $vaultHelper
     * @param PaymentMethods $paymentMethodsHelper
     * @param OrdersApi $ordersApi
     */
    public function __construct(
        private readonly Vault $vaultHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
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
        $paymentMethodInstance = $payment->getMethodInstance();

        $payment->getOrder()->setAdyenResulturlEventCode($response['resultCode']);
        $payment->setAdditionalInformation('resultCode', $response['resultCode']);
        $payment->setAdditionalInformation('3dActive', false);

        // Set the transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);
        // Do not close the transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        // Do not close the parent transaction
        $payment->setShouldCloseParentTransaction(false);

        if (!empty($response['pspReference'])) {
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);
            $payment->setTransactionId($response['pspReference']);
            $payment->setAdditionalInformation('pspReference', $response['pspReference']);
        }

        $ccType = $payment->getAdditionalInformation('cc_type');

        $isWalletPaymentMethod = $this->paymentMethodsHelper->isWalletPaymentMethod($paymentMethodInstance);
        $isCardPaymentMethod = $payment->getMethod() === PaymentMethods::ADYEN_CC ||
            $payment->getMethod() === PaymentMethods::ADYEN_CC_VAULT;

        if (!empty($response['additionalData']['paymentMethod']) &&
            is_null($ccType) &&
            ($isWalletPaymentMethod || $isCardPaymentMethod)
        ) {
            $ccType = $response['additionalData']['paymentMethod'];
            $payment->setAdditionalInformation('cc_type', $ccType);
            $payment->setCcType($ccType);
        }

        if (!empty($response['action'])) {
            $payment->setAdditionalInformation('action', $response['action']);
        }

        if (!empty($response['additionalData'])) {
            $payment->setAdditionalInformation('additionalData', $response['additionalData']);
        }

        if (!empty($response['details'])) {
            $payment->setAdditionalInformation('details', $response['details']);
        }

        if (!empty($response['donationToken'])) {
            $payment->setAdditionalInformation('donationToken', $response['donationToken']);
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
