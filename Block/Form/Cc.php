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

class Cc extends \Magento\Payment\Block\Form\Cc
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
     * Cc constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $paymentConfig);
        $this->_adyenHelper = $adyenHelper;
        $this->_appState = $context->getAppState();
        $this->_checkoutSession = $checkoutSession;
    }


    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
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
}