<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Adyen\Payment\Model\Ui\AdyenPosCloudConfigProvider;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class PaymentMethodsStatus extends Value
{
    protected PaymentMethods $paymentMethodsHelper;
    private WriterInterface $configWriter;

    /*
     * Following payment methods should be enabled with their own configuration path.
     */
    const EXCLUDED_PAYMENT_METHODS = [
        AdyenPayByLinkConfigProvider::CODE,
        AdyenPosCloudConfigProvider::CODE,
        AdyenMotoConfigProvider::CODE
    ];

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        PaymentMethods $paymentMethodsHelper,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        $this->paymentMethodsHelper = $paymentMethodsHelper;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function afterSave(): PaymentMethodsStatus
    {
        $value = $this->getValue();
        $adyenPaymentMethods = $this->paymentMethodsHelper->getAdyenPaymentMethods();

        foreach ($adyenPaymentMethods as $adyenPaymentMethod) {
            // Exclude following payment methods. They need to use their own configuration path.
            if (in_array($adyenPaymentMethod, self::EXCLUDED_PAYMENT_METHODS)) {
                continue;
            }

            $this->configWriter->save(
                'payment/' . $adyenPaymentMethod . '/active',
                $value,
            );
        }

        return $this;
    }
}
