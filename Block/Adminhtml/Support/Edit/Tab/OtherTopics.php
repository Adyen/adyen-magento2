<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support\Edit\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\View\Design\Theme\LabelFactory;
use Magento\Store\Model\System\Store;

class OtherTopics extends Generic implements TabInterface
{
    /**
     * @var Store
     */
    protected $store;

    /**
     * @var LabelFactory
     */
    protected $themeLabelFactory;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Store $store
     * @param LabelFactory $themeLabelFactory
     * @param array $data
     */
    public function __construct(
        Context      $context,
        Registry     $registry,
        FormFactory  $formFactory,
        Store        $store,
        LabelFactory $themeLabelFactory,
        array        $data = []
    )
    {
        $this->store = $store;
        $this->themeLabelFactory = $themeLabelFactory;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Internal constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setActive(true);
    }

    /**
     * Prepare label for tab
     *
     * @return Phrase
     */
    public function getTabLabel()
    {
        return __('Other topics');
    }

    /**
     * Prepare title for tab
     *
     * @return Phrase
     */
    public function getTabTitle()
    {
        return __('Other topics');
    }

    /**
     * Returns status flag about this tab can be shown or not
     *
     * @return true
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
