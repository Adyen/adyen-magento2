<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;

class FilterSortPaymentMethods
{
    const ADYEN_PREFIX = 'adyen_';
    const VAULT_SUFFIX = '_vault';
    const ADYEN_PAYMENT_METHODS = 'adyen_payment_methods_response';
    const PAYMENT_METHOD_MAP = [
        'cc' => 'scheme'
    ];

    private PaymentMethods $paymentMethods;
    private AdyenLogger $adyenLogger;
    private array $fetchPaymentMethodsResponse;
    private array $magentoPaymentMethods;

    public function __construct(
        PaymentMethods $paymentMethods,
        AdyenLogger $adyenLogger
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->adyenLogger = $adyenLogger;
        $this->fetchPaymentMethodsResponse = [];
    }

    public function afterGetAvailableMethods(
        MethodList $methodListObject,
        array $result,
        CartInterface $quote = null
    ): array {
        if (!empty($result)) {
            $this->magentoPaymentMethods = $result;
        } else {
            return $result;
        }

        $this->fetchPaymentMethods($quote);

        if (!empty($this->fetchPaymentMethodsResponse)) {
            $this->filterPaymentMethods();
            $this->sortPaymentMethodsList();
        }

        return $this->magentoPaymentMethods;
    }

    private function filterPaymentMethods(): void
    {
        $adyenPaymentMethods = $this->getAdyenPaymentMethods();

        foreach ($this->magentoPaymentMethods as $key => $paymentMethod) {
            $txVariant = $this->paymentMethodTypeReplace(
                str_starts_with($paymentMethod->getCode(), self::ADYEN_PREFIX) ?
                    substr($paymentMethod->getCode(), strlen(self::ADYEN_PREFIX)) :
                    false
            );

            if ($txVariant && str_ends_with($txVariant, self::VAULT_SUFFIX)) {
                $needle = self::VAULT_SUFFIX;
                $txVariant = $this->paymentMethodTypeReplace(
                    preg_replace("/$needle$/", '', $txVariant)
                );
            }

            if ($txVariant &&
                !in_array($txVariant, array_column($adyenPaymentMethods, 'type'), true)) {
                unset($this->magentoPaymentMethods[$key]);
            }
        }
    }

    private function sortPaymentMethodsList(): void
    {
        usort($this->magentoPaymentMethods, function ($a, $b) {
            $adyenPaymentMethods = $this->getAdyenPaymentMethods();

            $aTxVariant = $this->paymentMethodTypeReplace(
                str_starts_with($a->getCode(), self::ADYEN_PREFIX) ?
                substr($a->getCode(), strlen(self::ADYEN_PREFIX)) :
                false
            );

            $bTxVariant = $this->paymentMethodTypeReplace(
                str_starts_with($b->getCode(), self::ADYEN_PREFIX) ?
                    substr($b->getCode(), strlen(self::ADYEN_PREFIX)) :
                    false
            );

            if (!$aTxVariant && !$bTxVariant) {
                return 0;
            } elseif ($aTxVariant && $bTxVariant) {
                $aSort = array_search($aTxVariant, array_column($adyenPaymentMethods, 'type'), true);
                $bSort = array_search($bTxVariant, array_column($adyenPaymentMethods, 'type'), true);

                return ($aSort ?: -1) <=> ($bSort ?: -1);
            } elseif ($aTxVariant) {
                return -1;
            } elseif ($bTxVariant) {
                return 1;
            }

            return 0;
        });
    }

    private function fetchPaymentMethods(CartInterface $quote): void
    {
        try {
            $paymentMethods = $this->paymentMethods->getPaymentMethods(
                $quote->getId(),
                $quote->getBillingAddress()->getCountryId()
            );

            $paymentMethodsArray = json_decode($paymentMethods, true);
        } catch (Exception $e) {
            $this->adyenLogger->error(
                'There was an error while fetching payment methods: ' . $e->getMessage()
            );
        }

        if (!empty($paymentMethodsArray)) {
            $this->fetchPaymentMethodsResponse = $paymentMethodsArray;
        }
    }

    private function getAdyenPaymentMethods(): ?array
    {
        return $this->fetchPaymentMethodsResponse['paymentMethodsResponse']['paymentMethods'] ?? null;
    }

    private function paymentMethodTypeReplace(string $type): string
    {
        return self::PAYMENT_METHOD_MAP[$type] ?? $type;
    }
}
