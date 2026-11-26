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
        $ccType = $this->getInfo()->getCcType();
        if (!isset($ccType)) {
            return null;
        }

        $types = $this->adyenHelper->getAdyenCcTypes();
        if (isset($types[$ccType])) {
            return $types[$ccType]['name'];
        }

        // Card payments store the Adyen brand (e.g. "mc", "visa"), which is the code_alt of the card type.
        $typesByAltCode = $this->adyenHelper->getCcTypesAltData();
        if (isset($typesByAltCode[$ccType])) {
            return $typesByAltCode[$ccType]['name'];
        }

        return $ccType;
    }
}
