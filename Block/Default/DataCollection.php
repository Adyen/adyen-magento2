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

namespace Adyen\Payment\Block\Default;

use Adyen\Payment\Helper\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class DataCollection extends Template
{
    public function __construct(
        private readonly Config $configHelper,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Returns the environment based on the demo mode.
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getEnvironment(): string
    {
        $isDemoMode = $this->configHelper->isDemoMode(
            $this->_storeManager->getStore()->getId()
        );

        return $isDemoMode ? 'test' : 'live';
    }

    /**
     * Checks whether the data collection feature is enabled or not.
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnabled(): bool
    {
        return $this->configHelper->isOutsideCheckoutDataCollectionEnabled(
            $this->_storeManager->getStore()->getId()
        );
    }
}
