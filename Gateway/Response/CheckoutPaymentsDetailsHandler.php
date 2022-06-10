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

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Vault;
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

    public function __construct(
        Data $adyenHelper,
        Recurring $recurringHelper,
        Vault $vaultHelper,
        Config $configHelper,
        PaymentMethodFactory $paymentMethodFactory
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->recurringHelper = $recurringHelper;
        $this->vaultHelper = $vaultHelper;
        $this->configHelper = $configHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
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
            $storeId = $payment->getMethodInstance()->getStore();
            // If store alternative payment method is enabled and this is an alternative payment method
            // Else create entry in paypal_billing_agreement
            if ($this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId) &&
                $payment->getMethodInstance()->getCode() === AdyenHppConfigProvider::CODE) {
                $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($payment->getCcType());
                $this->vaultHelper->saveRecurringPaymentDetails($payment, $response['additionalData'], $adyenPaymentMethod);
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
