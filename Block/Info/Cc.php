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
use Magento\Framework\Exception\LocalizedException;
use Adyen\Payment\Helper\Data;
use Magento\Framework\View\Element\Template;

class Cc extends AbstractInfo
{
    protected Data $adyenHelper;

    public function __construct(
        Data $adyenHelper,
        Config $configHelper,
        CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($configHelper, $adyenOrderPaymentCollectionFactory, $context, $data);
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_cc.phtml';

    /**
     * Return credit card type
     *
     * @return string|null
     * @throws LocalizedException
     */
    public function getCcTypeName(): ?string
    {
        $types = $this->adyenHelper->getAdyenCcTypes();
        $ccType = $this->getInfo()->getCcType();

        if (isset($types[$ccType])) {
            return $types[$ccType]['name'];
        } else {
            return $ccType;
        }
    }
}
