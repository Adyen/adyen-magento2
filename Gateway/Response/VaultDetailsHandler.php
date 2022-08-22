<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods\AbstractWalletPaymentMethod;
use Adyen\Payment\Helper\PaymentMethods\PaymentMethodFactory;
use Adyen\Payment\Helper\Recurring;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenHppConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class VaultDetailsHandler implements HandlerInterface
{
    /** @var Vault  */
    private $vaultHelper;

    /** @var PaymentMethodFactory */
    private $paymentMethodFactory;

    /** @var Config */
    private $configHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var Recurring */
    private $recurringHelper;

    public function __construct(
        Vault $vaultHelper,
        PaymentMethodFactory $paymentMethodFactory,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        Recurring $recurringHelper
    ) {
        $this->vaultHelper = $vaultHelper;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
        $this->recurringHelper = $recurringHelper;
    }

    /**
     * @inheritdoc
     * @throws PaymentMethodException|LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (empty($response['additionalData'])) {
            return;
        }
        /** @var PaymentDataObject $orderPayment */
        $orderPayment = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $orderPayment->getPayment();
        $paymentMethodInstance = $payment->getMethodInstance();

        if ($this->vaultHelper->hasRecurringDetailReference($response) && $paymentMethodInstance->getCode() !== AdyenOneclickConfigProvider::CODE) {
            $storeId = $paymentMethodInstance->getStore();
            $paymentInstanceCode = $paymentMethodInstance->getCode();
            $storePaymentMethods = $this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId);
            $cardVaultEnabled = $this->vaultHelper->isCardVaultEnabled($storeId);
            $adyenTokensEnabled = $this->recurringHelper->areAdyenTokensEnabled($storeId);

            // If payment method is HPP and hpp config enabled
            // Else if payment method is card and vault is enabled
            // Else if payment method is card and vault is disabled and adyen tokens are enabled
            if ($storePaymentMethods && $paymentInstanceCode === AdyenHppConfigProvider::CODE) {
                $paymentMethod = $response['additionalData']['paymentMethod'];
                try {
                    $payment->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, true);
                    $adyenPaymentMethod = $this->paymentMethodFactory::createAdyenPaymentMethod($paymentMethod);
                    if ($adyenPaymentMethod instanceof AbstractWalletPaymentMethod) {
                        $this->vaultHelper->saveRecurringCardDetails($payment, $response['additionalData'], $adyenPaymentMethod);
                    } else {
                        $this->vaultHelper->saveRecurringPaymentMethodDetails($payment, $response['additionalData']);
                    }
                } catch (PaymentMethodException $e) {
                    $this->adyenLogger->error(sprintf('Unable to create payment method with tx variant %s in details handler', $paymentMethod));
                }
            } elseif ($cardVaultEnabled && $paymentInstanceCode === AdyenCcConfigProvider::CODE) {
                $this->vaultHelper->saveRecurringCardDetails($payment, $response['additionalData']);
            } elseif (!$cardVaultEnabled && $adyenTokensEnabled && $paymentInstanceCode === AdyenCcConfigProvider::CODE) {
                $order = $payment->getOrder();
                $this->recurringHelper->createAdyenBillingAgreement($order, $response['additionalData'], $payment->getAdditionalInformation());
            }
        }
    }
}
