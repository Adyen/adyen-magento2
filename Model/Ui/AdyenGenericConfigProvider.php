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
namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;

class AdyenGenericConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_abstract';

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * AdyenGenericConfigProvider constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper) {
        $this->_adyenHelper = $adyenHelper;
    }
    /**
     * Define foreach payment methods the RedirectUrl
     *
     * @return array
     */
    public function getConfig()
    {
        $config = [
            'payment' => []
        ];
        // show logos turned on by default
        if ($this->_showLogos()) {
            $config['payment']['adyen']['showLogo'] = true;
        } else {
            $config['payment']['adyen']['showLogo'] = false;
        }
        return $config;
    }
    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        return $this->_methods[$code]->getCheckoutRedirectUrl();
    }

    /**
     * @return bool
     */
    protected function _showLogos()
    {
        $showLogos = $this->_adyenHelper->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == \Adyen\Payment\Model\Config\Source\RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }
}