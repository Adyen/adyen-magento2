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
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;

class AdyenHppConfigProvider implements ConfigProviderInterface
{

    const CODE = 'adyen_hpp';

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
    protected $storeManager;

    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
		\Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->adyenHelper = $adyenHelper;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->customerSession = $customerSession;
        $this->session = $session;
        $this->storeManager = $storeManager;
    }

    /**
     * Set configuration for AdyenHPP payment method
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
                        'adyen/process/redirect',
                        ['_secure' => $this->getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        $gender = "";
        $dob = "";

        // get customer
        if ($this->customerSession->isLoggedIn()) {
            $gender = \Adyen\Payment\Model\Gender::getAdyenGenderFromMagentoGender(
                $this->customerSession->getCustomerData()->getGender()
            );

            // format to calendar date
            $dob = $this->customerSession->getCustomerData()->getDob();
            if ($dob) {
                $dob = strtotime($dob);
                $dob = date('m/d/Y', $dob);
            }
        }

		$config['payment']['adyenHpp']['locale'] = $this->adyenHelper->getCurrentLocaleCode($this->storeManager->getStore()->getId());

        // add to config
        $config['payment'] ['adyenHpp']['gender'] = $gender;
        $config['payment'] ['adyenHpp']['dob'] = $dob;

        // gender types
        $config['payment'] ['adyenHpp']['genderTypes'] = \Adyen\Payment\Model\Gender::getGenderTypes();

        $config['payment'] ['adyenHpp']['ratePayId'] = $this->adyenHelper->getRatePayId();
        $config['payment'] ['adyenHpp']['deviceIdentToken'] = hash("sha256",$this->session->getQuoteId() . date('c'));
        $config['payment'] ['adyenHpp']['nordicCountries'] = ['SE', 'NO', 'DK', 'FI'];

        return $config;
    }

    /**
     * Retrieve request object
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function getRequest()
    {
        return $this->request;
    }
}
