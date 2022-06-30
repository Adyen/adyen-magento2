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
use Adyen\Payment\Observer\AdyenHppDataAssignObserver;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

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
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($handlingSubject);

        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();
        $paymentMethodInstance = $payment->getMethodInstance();

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

        if ($this->vaultHelper->hasRecurringDetailReference($response) && $paymentMethodInstance->getCode() !== AdyenOneclickConfigProvider::CODE) {
            $storeId = $paymentMethodInstance->getStore();
            $paymentInstanceCode = $paymentMethodInstance->getCode();
            $storePaymentMethods = $this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId);

            if ($storePaymentMethods && $paymentInstanceCode === AdyenHppConfigProvider::CODE) {
                $brand = $payment->getAdditionalInformation(AdyenHppDataAssignObserver::BRAND_CODE);
                try {
                    $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($brand);
                    if ($adyenPaymentMethod->isWalletPaymentMethod()) {
                        $this->vaultHelper->saveRecurringCardDetails($payment, $response['additionalData']);
                    } else {
                        $this->vaultHelper->saveRecurringPaymentMethodDetails($payment, $response['additionalData']);
                    }
                } catch (PaymentMethodException $e) {
                    $this->adyenLogger->error(sprintf('Unable to create payment method with tx variant %s in details handler', $brand));
                }
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
