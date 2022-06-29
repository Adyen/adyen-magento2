<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CheckoutPaymentsDetailsHandler implements HandlerInterface
{
    /** @var Data  */
    protected $adyenHelper;

    /** @var Recurring */
    private $recurringHelper;

    /** @var Vault */
    private $vaultHelper;

    /** @var Config */
    private $configHelper;

    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var PaymentMethods */
    private $paymentMethodsHelper;

    public function __construct(
        Data $adyenHelper,
        Recurring $recurringHelper,
        Vault $vaultHelper,
        Config $configHelper,
        PaymentMethodFactory $paymentMethodFactory,
        AdyenLogger $adyenLogger,
        PaymentMethods $paymentMethodsHelper
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->recurringHelper = $recurringHelper;
        $this->vaultHelper = $vaultHelper;
        $this->configHelper = $configHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->adyenLogger = $adyenLogger;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
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

        // Email sending is set at CheckoutDataBuilder for Boleto
        // Otherwise, we don't want to send a confirmation email
        if ($payment->getMethod() != AdyenBoletoConfigProvider::CODE) {
            $payment->getOrder()->setCanSendNewEmailFlag(false);
        }

        if (!empty($response['pspReference'])) {
            // set pspReference as transactionId
            $payment->setCcTransId($response['pspReference']);
            $payment->setLastTransId($response['pspReference']);

            // set transaction
            $payment->setTransactionId($response['pspReference']);
        }

        if ($this->vaultHelper->hasRecurringDetailReference($response) && $payment->getMethodInstance()->getCode() !== AdyenOneclickConfigProvider::CODE) {
            $isCard = $this->paymentMethodsHelper->isCcTypeACardType($payment);
            $storeId = $payment->getMethodInstance()->getStore();
            $storePaymentMethods = $this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId);
            // If store alternative payment method is enabled and this is an alternative payment method AND ccType is NOT a card type
            // ElseIf store alternative payment method is enabled and this is an alternative payment method AND ccType is a card type
            // Else create entry in paypal_billing_agreement
            if ($storePaymentMethods && $payment->getMethodInstance()->getCode() === AdyenHppConfigProvider::CODE && !$isCard) {
                $this->vaultHelper->saveRecurringPaymentMethodDetails($payment, $response['additionalData']);
            } elseif ($storePaymentMethods && $payment->getMethodInstance()->getCode() === AdyenHppConfigProvider::CODE && $isCard) {
                $this->vaultHelper->saveRecurringCardDetails($payment, $response['additionalData']);
            } else {
                $order = $payment->getOrder();
                $this->recurringHelper->createAdyenBillingAgreement($order, $response['additionalData'], $payment->getAdditionalInformation());
            }
        }

        // do not close transaction so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);
    }
}
