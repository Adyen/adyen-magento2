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
 * Adyen Payment Module
 *
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class AdyenGooglePayConfigProvider implements ConfigProviderInterface
{
    const CODE = 'adyen_google_pay';
    const GOOGLE_PAY_VAULT_CODE = 'adyen_google_pay_vault';
    const PRODUCTION = 'production';

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * AdyenGooglePayConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        // set to active
        $config = [
            'payment' => [
                self::CODE => [
                    'isActive' => true,
                    'redirectUrl' => $this->urlBuilder->getUrl(
                        'adyen/process/redirect/',
                        ['_secure' => $this->_getRequest()->isSecure()]
                    ),
                    'successUrl' => $this->urlBuilder->getUrl(
                        'checkout/onepage/success/',
                        ['_secure' => $this->_getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        $adyenGooglePayConfig['active'] = (bool)$this->adyenHelper->isAdyenGooglePayEnabled(
            $this->storeManager->getStore()->getId()
        );
        $adyenGooglePayConfig['checkoutEnvironment'] = $this->getGooglePayEnvironment(
            $this->storeManager->getStore()->getId()
        );
        $adyenGooglePayConfig['locale'] = $this->adyenHelper->getStoreLocale(
            $this->storeManager->getStore()->getId()
        );
        $adyenGooglePayConfig['merchantAccount'] = $this->adyenHelper->getAdyenMerchantAccount(
            "adyen_google_pay",
            $this->storeManager->getStore()->getId()
        );

        $quote = $this->checkoutSession->getQuote();
        $currency = $quote->getCurrency();
        $adyenGooglePayConfig['format'] = $this->adyenHelper->decimalNumbers($currency);

        $adyenGooglePayConfig['merchantIdentifier'] = $this->adyenHelper->getAdyenGooglePayMerchantIdentifier($this->storeManager->getStore()->getId());

        $config['payment']['adyenGooglePay'] = $adyenGooglePayConfig;
        return $config;
    }

    /**
     * Retrieve request object
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    private function getGooglePayEnvironment($storeId = null)
    {
        if ($this->adyenHelper->isDemoMode($storeId)) {
            return \Adyen\Payment\Helper\Data::TEST;
        }

        return self::PRODUCTION;
    }
}
