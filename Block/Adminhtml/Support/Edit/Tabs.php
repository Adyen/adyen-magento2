<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support\Edit;

class Tabs extends \Magento\Backend\Block\Widget\Tabs
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('support_form_tabs');
        $this->setDestElementId('support_form');
        $this->setTitle(__('Troubleshoot an issue'));
    }
}
