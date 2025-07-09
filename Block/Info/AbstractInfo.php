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
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;

class AbstractInfo extends Info
{
    protected Config $configHelper;
    protected CollectionFactory $adyenOrderPaymentCollectionFactory;

    public function __construct(
        Config            $configHelper,
        CollectionFactory $adyenOrderPaymentCollectionFactory,
        Template\Context  $context,
        array             $data = []
    ) {
        parent::__construct($context, $data);

        $this->adyenOrderPaymentCollectionFactory = $adyenOrderPaymentCollectionFactory;
        $this->configHelper = $configHelper;
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function getAdyenPspReference()
    {
        return $this->getInfo()->getAdyenPspReference();
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function isDemoMode()
    {
        $storeId = $this->getInfo()->getOrder()->getStoreId();
        return $this->configHelper->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);
    }

    /**
     * @return Collection
     * @throws LocalizedException
     */
    public function getPartialPayments(): Collection
    {
        return $this->adyenOrderPaymentCollectionFactory
            ->create()
            ->addPaymentFilterAscending($this->getInfo()->getId());
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function renderPartialPaymentsHtml(): string
    {
        return $this->getLayout()
            ->createBlock("Adyen\Payment\Block\Info\PartialPayments")
            ->setInfoBlock($this)
            ->toHtml();
    }
}
