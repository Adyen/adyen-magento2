<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Template;

class Menu extends Template
{
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
        return $this->getRequest()->getActionName();
    }
}
