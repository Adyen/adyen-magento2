<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data as MagentoPaymentDataHelper;
use Magento\Store\Model\ScopeInterface;

class PaymentMethodType implements OptionSourceInterface
{
    private const PAYMENT_METHOD_MAP = [
        'cc'     => 'scheme',
        'boleto' => 'boletobancario',
    ];

    private const EXCLUDED_CODES = [
        'adyen_hpp',
        'adyen_hpp_vault',
        'adyen_oneclick',
        'adyen_pos_cloud',
        'adyen_pay_by_link',
        'adyen_moto',
        'adyen_abstract',
    ];

    /**
     * @param MagentoPaymentDataHelper $paymentHelper
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly MagentoPaymentDataHelper $paymentHelper,
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        $paymentMethods = $this->paymentHelper->getPaymentMethodList();

        foreach (array_keys($paymentMethods) as $code) {
            if (!str_starts_with((string) $code, 'adyen_')) {
                continue;
            }
            if (in_array($code, self::EXCLUDED_CODES, true)) {
                continue;
            }
            if (str_ends_with((string) $code, '_vault')) {
                continue;
            }

            $txVariant = substr((string) $code, strlen('adyen_'));
            $txVariant = self::PAYMENT_METHOD_MAP[$txVariant] ?? $txVariant;

            $title = (string) $this->scopeConfig->getValue(
                'payment/' . $code . '/title',
                ScopeInterface::SCOPE_STORE
            );

            $label = $title !== '' ? $title . ' (' . $txVariant . ')' : $txVariant;

            $options[] = ['value' => $txVariant, 'label' => $label];
        }

        usort($options, static fn($a, $b) => strcmp((string) $a['label'], (string) $b['label']));

        return $options;
    }
}
