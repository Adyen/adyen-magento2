<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class AbstractInfo extends \Magento\Payment\Block\Info
{

    /**
     * @var \Adyen\Payment\Helper\Config
     */
    protected $_configHelper;

    /**
     * @var \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory
     */
    protected $_adyenOrderPaymentCollectionFactory;

    /**
     * @param Config $configHelper
     * @param CollectionFactory $adyenOrderPaymentCollectionFactory
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        \Adyen\Payment\Helper\Config $configHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->_configHelper = $configHelper;
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAdyenPspReference()
    {
        return $this->getInfo()->getAdyenPspReference();
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isDemoMode()
    {
        $storeId = $this->getInfo()->getOrder()->getStoreId();
        return $this->_configHelper->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);
    }
}
