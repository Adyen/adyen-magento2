<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Menu extends Template
{
    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

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
