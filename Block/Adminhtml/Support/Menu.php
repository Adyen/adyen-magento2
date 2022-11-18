<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Menu extends Template
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function orderProcessingUrl()
    {
        return $this->getUrl('adyen/support/orderprocessing/');
    }

    public function configurationSettingsUrl()
    {
        return $this->getUrl('adyen/support/settings');
    }
}
