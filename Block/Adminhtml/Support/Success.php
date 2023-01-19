<?php declare(strict_types=1);
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Template;
use Magento\Backend\Helper\Data;

class Success extends Template
{
    /**
     * @var Data
     */
    private $backendDataHelper;

    /**
     * @param Template\Context $context
     * @param Data $backendDataHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $backendDataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->backendDataHelper = $backendDataHelper;
    }

    /**
     * @return string
     */
    public function getSuccessMessage(): string
    {
        return "Your ticket was sent to Adyen’s support team!<br>You will get a response within 1 working day.<br><br>
You can track your ticket using the link we sent in the confirmation email.";
    }

    /**
     * @return string
     */
    public function getSuccessTitle(): string
    {
        return "Adyen Support";
    }

    /**
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return $this->backendDataHelper->getHomePageUrl();
    }
}
