<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class PartialPayments extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_partial_payments.phtml';

    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(Template\Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * @return Collection|null
     * @throws LocalizedException
     */
    public function getPartialPayments(): ?Collection
    {
        $infoBlock = $this->getInfoBlock();

        if ($infoBlock instanceof AbstractInfo) {
            return $infoBlock->getPartialPayments();
        } else {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isDemoMode(): bool
    {
        $infoBlock = $this->getInfoBlock();

        if ($infoBlock instanceof AbstractInfo) {
            return $this->getInfoBlock()->isDemoMode();
        } else {
            return false;
        }
    }
}
