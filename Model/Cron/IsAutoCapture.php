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

declare(strict_types=1);

namespace Adyen\Payment\Model\Cron;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\ManualCaptureMethods;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;

class IsAutoCapture
{
    private $adyenHelper;
    private $adyenLogger;
    private $scopeConfig;
    private $manualCaptureMethods;

    public function __construct(
        Data $adyenHelper,
        AdyenLogger $adyenLogger,
        ScopeConfigInterface $scopeConfig,
        ManualCaptureMethods $manualCaptureMethods
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
        $this->scopeConfig = $scopeConfig;
        $this->manualCaptureMethods = $manualCaptureMethods;
    }

    /**
     * Validate if this payment methods allows manual capture
     * This is a default can be forced differently to overrule on acquirer level
     *
     * @param string $paymentMethod
     * @return bool
     */
    private function manualCaptureAllowed(string $paymentMethod): bool
    {
        // For all openinvoice methods manual capture is the default
        if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod)) {
            return true;
        }

        return in_array($paymentMethod, array_map(function ($method) {
            return $method['value'];
        }, $this->manualCaptureMethods->toOptionArray()));
    }

    private function getConfigData(string $field, string $paymentMethodCode, int $storeId)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function isManualCapturePaypal(int $storeId): bool
    {
        return (bool) $this->getConfigData('paypal_capture_mode', 'adyen_abstract', $storeId);
    }

    private function isCaptureModeOpenInvoice(int $storeId): bool
    {
        return (bool) $this->getConfigData('auto_capture_openinvoice', 'adyen_abstract', $storeId);
    }

    private function getCaptureMode(int $storeId): string
    {
        return trim($this->getConfigData('capture_mode', 'adyen_abstract', $storeId));
    }

    private function getSepaFlow(int $storeId): string
    {
        return trim($this->getConfigData('sepa_flow', 'adyen_abstract', $storeId));
    }

    public function check(OrderInterface $order, string $paymentMethod): bool
    {
        // validate if payment methods allows manual capture
        if ($this->manualCaptureAllowed($paymentMethod)) {
            $storeId = (int) $order->getStoreId();
            $sepaFlow = $this->getSepaFlow($storeId);

            /*
             * if you are using authcap the payment method is manual.
             * There will be a capture send to indicate if payment is successful
             */
            if ($paymentMethod === 'sepadirectdebit' && $sepaFlow === 'authcap') {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Manual Capture is applied for sepa because it is in authcap flow'
                );
                return false;
            }

            $paymentCode = $order->getPayment()->getMethod();

            // payment method ideal, cash adyen_boleto has direct capture
            if ($paymentMethod === 'sepadirectdebit' && $sepaFlow !== 'authcap') {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'This payment method does not allow manual capture.(2) paymentCode:' .
                    $paymentCode . ' paymentMethod:' . $paymentMethod . ' sepaFLow:' . $sepaFlow
                );
                return true;
            }

            if ($paymentCode === 'adyen_pos_cloud') {
                $captureModePos = $this->adyenHelper->getAdyenPosCloudConfigData(
                    'capture_mode_pos',
                    $storeId
                );
                if (strcmp($captureModePos, 'auto') === 0) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as auto capture '
                    );
                    return true;
                } elseif (strcmp($captureModePos, 'manual') === 0) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is POS Cloud and configured to be working as manual capture '
                    );
                    return false;
                }
            }

            // if auto capture mode for openinvoice is turned on then use auto capture
            if ($this->isCaptureModeOpenInvoice($storeId) &&
                $this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod)
            ) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'This payment method is configured to be working as auto capture '
                );
                return true;
            }

            // if PayPal capture modues is different from the default use this one
            if (strcmp($paymentMethod, 'paypal') === 0) {
                if ($this->isManualCapturePaypal($storeId)) {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as manual capture'
                    );
                    return false;
                } else {
                    $this->adyenLogger->addAdyenNotificationCronjob(
                        'This payment method is paypal and configured to work as auto capture'
                    );
                    return true;
                }
            }
            if (strcmp($this->getCaptureMode($storeId), 'manual') === 0) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Capture mode for this payment is set to manual'
                );
                return false;
            }

            /*
             * online capture after delivery, use Magento backend to online invoice
             * (if the option auto capture mode for openinvoice is not set)
             */
            if ($this->adyenHelper->isPaymentMethodOpenInvoiceMethod($paymentMethod)) {
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Capture mode for klarna is by default set to manual'
                );
                return false;
            }

            $this->adyenLogger->addAdyenNotificationCronjob('Capture mode is set to auto capture');
            return true;
        } else {
            // does not allow manual capture so is always immediate capture
            $this->adyenLogger->addAdyenNotificationCronjob(
                'This payment method does not allow manual capture'
            );
            return true;
        }
    }
}
