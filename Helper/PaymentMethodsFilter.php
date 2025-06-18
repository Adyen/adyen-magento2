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

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\App\RequestInterface;

class PaymentMethodsFilter
{
    const ADYEN_PREFIX = 'adyen_';
    const VAULT_SUFFIX = '_vault';
    const ADYEN_PAYMENT_METHODS = 'adyen_payment_methods_response';
    const PAYMENT_METHOD_MAP = [
        'cc' => 'scheme',
        'boleto' => 'boletobancario'
    ];
    const EXCLUDED_PAYMENT_METHODS_FROM_FILTERING = [
        AdyenPosCloudConfigProvider::CODE,
        AdyenPayByLinkConfigProvider::CODE
    ];

    private PaymentMethods $paymentMethods;
    private RequestInterface $request;

    public function __construct(
        PaymentMethods $paymentMethods,
        RequestInterface $request
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->request = $request;
    }

    public function sortAndFilterPaymentMethods(array $magentoPaymentMethods, CartInterface $quote): array
    {
        $channel = $this->request->getParam('channel');
        $adyenPaymentMethodsResponse = $this->paymentMethods->getPaymentMethods(
            $quote->getId(),
            $quote->getBillingAddress()->getCountryId(),
            null,
            $channel
        );

        $adyenPaymentMethodsDecoded = json_decode($adyenPaymentMethodsResponse, true);

        if (!empty($adyenPaymentMethodsDecoded)) {
            $adyenPaymentMethods = $adyenPaymentMethodsDecoded['paymentMethodsResponse']['paymentMethods'];

            $magentoPaymentMethods = $this->filterPaymentMethods($magentoPaymentMethods, $adyenPaymentMethods);
            $magentoPaymentMethods = $this->sortPaymentMethodsList($magentoPaymentMethods, $adyenPaymentMethods);
        }

        return [$magentoPaymentMethods, $adyenPaymentMethodsResponse];
    }

    private function filterPaymentMethods(array $magentoPaymentMethods, array $adyenPaymentMethods): array
    {
        foreach ($magentoPaymentMethods as $key => $paymentMethod) {
            if (in_array($paymentMethod->getCode(), self::EXCLUDED_PAYMENT_METHODS_FROM_FILTERING)) {
                continue;
            }

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

            if ( $txVariant &&
                !array_filter(
                    $adyenPaymentMethods,
                    function ($method) use ($txVariant) {
                        return isset($method['type']) && strcasecmp($method['type'], $txVariant) === 0;
                    }
                )
            ) {
                unset($magentoPaymentMethods[$key]);
            }

        }

        return $magentoPaymentMethods;
    }

    private function sortPaymentMethodsList(array $magentoPaymentMethods, array $adyenPaymentMethods): array
    {
        usort($magentoPaymentMethods, function ($a, $b) use ($adyenPaymentMethods) {
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

            $aSort = array_search($aTxVariant, array_column($adyenPaymentMethods, 'type'), true);
            $bSort = array_search($bTxVariant, array_column($adyenPaymentMethods, 'type'), true);

            return ($aSort === false ? PHP_INT_MAX : $aSort) <=> ($bSort === false ? PHP_INT_MAX : $bSort);
        });

        return $magentoPaymentMethods;
    }

    private function paymentMethodTypeReplace(string $type): string
    {
        return self::PAYMENT_METHOD_MAP[$type] ?? $type;
    }
}
