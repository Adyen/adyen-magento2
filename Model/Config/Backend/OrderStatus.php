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
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class OrderStatus extends Value
{
    /**
     * @var PaymentMethods
     */
    protected $paymentMethodsHelper;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
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
