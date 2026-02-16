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
     * @param PaymentMethods $paymentMethodsHelper
     * @param OrdersApi $ordersApi
     * @param TxVariantFactory $txVariantFactory
     */
    public function __construct(
        private readonly Vault $vaultHelper,
        private readonly PaymentMethods $paymentMethodsHelper,
        private readonly OrdersApi $ordersApi,
        private readonly TxVariantFactory $txVariantFactory
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

        // `ccType` is set on card or wallet payments only.
        if (!empty($response['paymentMethod'])) {
            if ($this->paymentMethodsHelper->isWalletPaymentMethod($paymentMethodInstance)) {
                // Extract the scheme card brand from the wallet payment response
                $txVariant = $this->txVariantFactory->create([
                    'txVariant' => $response['paymentMethod']['brand']
                ]);

                $ccType = $txVariant->getCard();
            } elseif (in_array($payment->getMethod(), [PaymentMethods::ADYEN_CC, PaymentMethods::ADYEN_CC_VAULT])) {
                // `brand` always refers to the scheme card brand, use it as is
                $ccType = $response['paymentMethod']['brand'];
            }

            if (isset($ccType)) {
                $payment->setAdditionalInformation(AdyenCcDataAssignObserver::CC_TYPE, $ccType);
                $payment->setCcType($ccType);
            } else {
                // Cleanup ccType if not set, this might be inherited from the previous payment attempt
                $payment->unsAdditionalInformation(AdyenCcDataAssignObserver::CC_TYPE);
                $payment->setCcType(null);
            }
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
