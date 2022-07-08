<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\PaymentMethods;
use Magento\Framework\App\Config\Storage\WriterInterface;

class OrderStatus extends \Magento\Framework\App\Config\Value
{
    /**
     * @var PaymentMethods
     */
    protected $paymentMethodsHelper;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param WriterInterface $configWriter
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        WriterInterface $configWriter,
        PaymentMethods $paymentMethodsHelper,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        $this->paymentMethodsHelper = $paymentMethodsHelper;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this|OrderStatus
     */
    public function afterSave()
    {
        $value = $this->getValue();
        $adyenPaymentMethods = $this->paymentMethodsHelper->getAdyenPaymentMethods();

        foreach ($adyenPaymentMethods as $adyenPaymentMethod) {
            $this->configWriter->save(
                'payment/' . $adyenPaymentMethod . '/order_status',
                $value,
            );
        }

        return $this;
    }
}
