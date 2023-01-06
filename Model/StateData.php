<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\StateDataInterface;
use Magento\Framework\Model\AbstractModel;

class StateData extends AbstractModel implements StateDataInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel\StateData::class);
    }

    public function getQuoteId(): int
    {
        return $this->getData(self::QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): StateDataInterface
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getStateData(): ?string
    {
        return $this->getData(self::STATE_DATA);
    }

    public function setStateData(string $stateData): StateDataInterface
    {
        return $this->setData(self::STATE_DATA, $stateData);
    }
}
