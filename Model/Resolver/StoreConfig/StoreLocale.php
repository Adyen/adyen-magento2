<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2024 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */
declare(strict_types=1);

namespace Adyen\Payment\Model\Resolver\StoreConfig;

use Adyen\Payment\Helper\Data;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\Store\Api\Data\StoreInterface;

class StoreLocale implements ResolverInterface
{
    protected Data $adyenHelper;

    /**
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        Data $adyenHelper
    ) {
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @param Context $context
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?string {
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();
        return $this->adyenHelper->getStoreLocale((int)$store->getId());
    }
}
