<?php

namespace Adyen\Payment\Plugin;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethodsFilter;
use Adyen\Payment\Model\Method\Adapter;

/**
 * Applies the payment method title overrides to the Magento payment method title, so the configured title
 * is also used outside the checkout (order view, emails, invoices, admin, GraphQL).
 */
class PaymentMethodTitleOverride
{
    /**
     * @param Config $configHelper
     */
    public function __construct(
        private readonly Config $configHelper
    ) {}

    /**
     * @param Adapter $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetTitle(Adapter $subject, mixed $result): mixed
    {
        $code = (string) $subject->getCode();

        if (!str_starts_with($code, PaymentMethodsFilter::ADYEN_PREFIX)) {
            return $result;
        }

        $txVariant = substr($code, strlen(PaymentMethodsFilter::ADYEN_PREFIX));
        $txVariant = PaymentMethodsFilter::PAYMENT_METHOD_MAP[$txVariant] ?? $txVariant;
        $overrides = $this->configHelper->getPaymentMethodTitleOverrides($subject->getStore());
        $title = $overrides[$txVariant] ?? null;

        return is_string($title) && trim($title) !== '' ? $title : $result;
    }
}
