<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Form;

class Cc extends \Magento\Payment\Block\Form
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::form/cc.phtml';

    /**
     * Payment config model
     *
     * @var \Magento\Payment\Model\Config
     */
    protected $_paymentConfig;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_paymentConfig = $paymentConfig;
        $this->_adyenHelper = $adyenHelper;
        $this->_appState = $context->getAppState();
        $this->_checkoutSession = $checkoutSession;
    }


    public function getCsePublicKey()
    {
        // get storeId for admin
        if (!$this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {
            $storeId = $this->_storeManager->getStore()->getId();
        } else {
            $storeId = null;
        }

        $demoMode = $this->_adyenHelper->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);

        if($demoMode) {
            $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_test', $storeId);
        } else {
            $cseKey = $this->_adyenHelper->getAdyenCcConfigData('cse_publickey_live', $storeId);
        }

        return $cseKey;

    }

    /**
     * Retrieve availables credit card types
     *
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getCcAvailableTypes()
    {
        $types = $this->_paymentConfig->getCcTypes();
        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code => $name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        if ($months === null) {
            $months[0] = __('Month');
            $months = array_merge($months, $this->_paymentConfig->getMonths());
            $this->setData('cc_months', $months);
        }
        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if ($years === null) {
            $years = $this->_paymentConfig->getYears();
            $years = [0 => __('Year')] + $years;
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    /**
     * Retrieve has verification configuration
     *
     * @return bool
     */
    public function hasVerification()
    {
        // if backend order and moto payments is turned on don't show cvc
        if ($this->_appState->getAreaCode() === \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE) {

            $this->getCheckoutSession();
            $store = $this->_checkoutSession->getQuote()->getStore();
            $enableMoto = $this->_adyenHelper->getAdyenCcConfigDataFlag('enable_moto', $store->getId());
            if($enableMoto) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether switch/solo card type available
     *
     * @return bool
     */
    public function hasSsCardType()
    {
        $availableTypes = explode(',', $this->getMethod()->getConfigData('cctypes'));
        $ssPresenations = array_intersect(['SS', 'SM', 'SO'], $availableTypes);
        if ($availableTypes && count($ssPresenations) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Solo/switch card start year
     *
     * @return array
     */
    public function getSsStartYears()
    {
        $years = [];
        $first = date("Y");

        for ($index = 5; $index >= 0; $index--) {
            $year = $first - $index;
            $years[$year] = $year;
        }
        $years = [0 => __('Year')] + $years;
        return $years;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        $this->_eventManager->dispatch('payment_form_block_to_html_before', ['block' => $this]);
        return parent::_toHtml();
    }


}