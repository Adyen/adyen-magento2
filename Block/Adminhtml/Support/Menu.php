<?php declare(strict_types=1);
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Template;

class Menu extends Template
{
    const MENU_SECTIONS = [
        'orderprocessing' => 'orderprocessing',
        'orderprocessingform' => 'orderprocessing',
        'configurationsettings' => 'configurationsettings',
        'configurationsettingsform' => 'configurationsettings',
    ];

    public function orderProcessingUrl()
    {
        return $this->getUrl('adyen/support/orderprocessing');
    }

    public function configurationSettingsUrl()
    {
        return $this->getUrl('adyen/support/configurationsettings');
    }

    public function getCurrentSection()
    {
        return self::MENU_SECTIONS[$this->getRequest()->getActionName()];
    }
}
