<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Exception\PaymentMethodException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\Method\PaymentMethodInterface;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Model\Ui\AdyenOneclickConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Model\Ui\VaultConfigProvider;

class VaultDetailsHandler implements HandlerInterface
{
    private Vault $vaultHelper;
    private Config $configHelper;
    private AdyenLogger $adyenLogger;

    public function __construct(
        Vault $vaultHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->vaultHelper = $vaultHelper;
        $this->configHelper = $configHelper;
        $this->adyenLogger = $adyenLogger;
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
        $paymentInstanceCode = $paymentMethodInstance->getCode();

        if ($this->vaultHelper->hasRecurringDetailReference($response) && $paymentInstanceCode !== AdyenOneclickConfigProvider::CODE) {
            $storeId = $paymentMethodInstance->getStore();
            $storePaymentMethods = $this->configHelper->isStoreAlternativePaymentMethodEnabled($storeId);
            $cardVaultEnabled = $this->vaultHelper->isCardVaultEnabled($storeId);

            // If payment method is NOT card
            // Else if card
            if ($storePaymentMethods && $paymentMethodInstance instanceof PaymentMethodInterface) {
                try {
                    $payment->setAdditionalInformation(VaultConfigProvider::IS_ACTIVE_CODE, true);
                    $this->vaultHelper->saveRecurringDetails($payment, $response['additionalData']);
                } catch (PaymentMethodException $e) {
                    $this->adyenLogger->error(sprintf(
                        'Unable to create payment method with tx variant %s in details handler',
                        $response['additionalData']['paymentMethod']
                    ));
                }
            } elseif ($cardVaultEnabled && $paymentInstanceCode === AdyenCcConfigProvider::CODE) {
                $this->vaultHelper->saveRecurringCardDetails($payment, $response['additionalData']);
            }
        }
    }
}
