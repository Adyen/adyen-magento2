<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory;
use Magento\Framework\View\Element\Template;

class PaymentMethodInfo extends AbstractInfo
{
    private ChargedCurrency $chargedCurrency;
    protected $_template = 'Adyen_Payment::info/adyen_pm.phtml';

    public function __construct(
        CollectionFactory $adyenOrderPaymentCollectionFactory,
        Config $configHelper,
        Template\Context $context,
        ChargedCurrency $chargedCurrency,
        array $data = []
    ) {
        parent::__construct($configHelper, $adyenOrderPaymentCollectionFactory, $context, $data);

        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * Get all Banktransfer related data
     */
    public function getBankTransferData(): array
    {
        $result = [];
        if (!empty($this->getInfo()->getOrder()) &&
            !empty($this->getInfo()->getOrder()->getPayment()) &&
            !empty($this->getInfo()->getOrder()->getPayment()->getAdditionalInformation('bankTransfer.owner'))
        ) {
            $result = $this->getInfo()->getOrder()->getPayment()->getAdditionalInformation();
        }

        return $result;
    }

    /**
     * Get all Multibanco related data
     */
    public function getMultibancoData(): array
    {
        $result = [];
        if (!empty($this->getInfo()->getOrder()) &&
            !empty($this->getInfo()->getOrder()->getPayment()) &&
            !empty($this->getInfo()->getOrder()->getPayment()->getAdditionalInformation('comprafacil.entity'))
        ) {
            $result = $this->getInfo()->getOrder()->getPayment()->getAdditionalInformation();
        }

        return $result;
    }

    public function getOrder(): mixed
    {
        return $this->getInfo()->getOrder();
    }

    public function getOrderAmountCurrency(): AdyenAmountCurrency
    {
        return $this->chargedCurrency->getOrderAmountCurrency($this->getInfo()->getOrder(), false);
    }
}
