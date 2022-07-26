<?php

namespace Adyen\Payment\Model\Config\Adminhtml;

use Adyen\Payment\Helper\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class ConfigurationWizard extends Field
{
    protected $_template = 'Adyen_Payment::config/configuration_wizard.phtml';

    /**
     * @var Config
     */
    private $configHelper;

    public function __construct(
        Context $context,
        Config $configHelper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
        $this->configHelper = $configHelper;
    }

    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getNextButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configuration_action',
                'label' => __('Next'),
                'class' => 'primary'
            ]
        );

        return $button->toHtml();
    }

    public function getResetButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'adyen_configuration_action_reset',
                'label' => __('Reconfigure'),
            ]
        );

        return $button->toHtml();
    }

    public function getMerchantAccountsUrl()
    {
        return $this->getUrl('adyen/configuration/merchantaccounts');
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    public function testConfigured() {
        $merchantAccount = boolval($this->configHelper->getMerchantAccount());
        $clientKeyTest = boolval($this->configHelper->getClientKey('test', $this->getStoreId()));
        $notificationUsername = boolval($this->configHelper->getNotificationsUsername($this->getStoreId()));
        $notificationPassword = boolval($this->configHelper->getNotificationsPassword($this->getStoreId()));

        return $merchantAccount || $clientKeyTest || $notificationUsername || $notificationPassword;
    }

    public function liveConfigured() {
        $merchantAccount = boolval($this->configHelper->getMerchantAccount());
        $livePrefixUrl = boolval(
            $this->configHelper
                ->getConfigData('live_endpoint_url_prefix', Config::XML_ADYEN_ABSTRACT_PREFIX, $this->getStoreId())
        );
        $clientKeyLive = boolval($this->configHelper->getClientKey('live', $this->getStoreId()));
        $notificationUsername = boolval($this->configHelper->getNotificationsUsername($this->getStoreId()));
        $notificationPassword = boolval($this->configHelper->getNotificationsPassword($this->getStoreId()));

        return $merchantAccount || $livePrefixUrl || $clientKeyLive || $notificationUsername || $notificationPassword;
    }
}
