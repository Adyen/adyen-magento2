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

class Menu extends Template
{
    const MENU_SECTIONS = [
        'orderprocessing' => 'orderprocessing',
        'orderprocessingform' => 'orderprocessing',
        'configurationsettings' => 'configurationsettings',
        'configurationsettingsform' => 'configurationsettings',
        'othertopicsform' => 'othertopicsform',
        'success' => 'success'
    ];

    /**
     * @return string
     */
    public function orderProcessingUrl(): string
    {
        return $this->getUrl('adyen/support/orderprocessing');
    }

    /**
     * @return string
     */
    public function configurationSettingsUrl(): string
    {
        return $this->getUrl('adyen/support/configurationsettings');
    }

    /**
     * @return string
     */
    public function otherTopicsFormUrl(): string
    {
        return $this->getUrl('adyen/support/othertopicsform');
    }

    /**
     * @return string
     */
    public function getCurrentSection(): string
    {
        return self::MENU_SECTIONS[$this->getRequest()->getActionName()];
    }
}
