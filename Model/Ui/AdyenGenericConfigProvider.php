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
    protected $adyenHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * AdyenGenericConfigProvider constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
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
        if ($this->showLogos()) {
            $config['payment']['adyen']['showLogo'] = true;
        } else {
            $config['payment']['adyen']['showLogo'] = false;
        }

        $config['payment']['adyen']['clientKey'] = $this->adyenHelper->getClientKey();
        $config['payment']['adyen']['checkoutEnvironment'] = $this->adyenHelper->getCheckoutEnvironment(
            $this->storeManager->getStore()->getId()
        );
        $config['payment']['adyen']['locale'] = $this->adyenHelper->getStoreLocale(
            $this->storeManager->getStore()->getId()
        );

        return $config;
    }

    /**
     * @return bool
     */
    protected function showLogos()
    {
        $showLogos = $this->adyenHelper->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == \Adyen\Payment\Model\Config\Source\RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }
}
