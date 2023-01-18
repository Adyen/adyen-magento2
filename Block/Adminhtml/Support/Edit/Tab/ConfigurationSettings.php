<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Design\Theme\LabelFactory;
use Magento\Store\Model\System\Store;

class ConfigurationSettings extends Generic implements TabInterface
{
    const HEADLESS_YES = 1;
    const HEADLESS_NO = 0;

    /**
     * @var Store
     */
    protected $_store;

    /**
     * @var LabelFactory
     */
    protected $_themeLabelFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Store $store
     * @param LabelFactory $themeLabelFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Store $store,
        LabelFactory $themeLabelFactory,
        array $data = []
    ) {
        $this->_store = $store;
        $this->_themeLabelFactory = $themeLabelFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setActive(true);
    }

    /**
     * Prepare label for tab
     */
    public function getTabLabel()
    {
        return __('Configuration settings');
    }

    /**
     * Prepare title for tab
     */
    public function getTabTitle()
    {
        return __('Configuration settings');
    }

    /**
     * Returns status flag about this tab can be shown or not
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     */
    public function isHidden()
    {
        return false;
    }
}
