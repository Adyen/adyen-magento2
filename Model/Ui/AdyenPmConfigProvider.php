<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Ui;

use Adyen\Payment\Helper\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;

class AdyenPmConfigProvider implements ConfigProviderInterface
{

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var Config
     */
    protected $configHelper;

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
     * @var \Adyen\Payment\Model\Gender
     */
    protected $gender;

    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $session
     * @param \Adyen\Payment\Model\Gender
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Adyen\Payment\Model\Gender $gender,
        Config $configHelper
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->adyenHelper = $adyenHelper;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->customerSession = $customerSession;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->gender = $gender;
        $this->configHelper = $configHelper;
    }

    public function getConfig()
    {
        $storeId = $this->storeManager->getStore()->getId();

        // set to active
        $config = [
            'payment' => [
                'adyen_pm' => [
                    'vaultCode' => 'adyen_pm_vault',
                    'isActive' => true,
                    'successPage' => $this->urlBuilder->getUrl(
                        'checkout/onepage/success',
                        ['_secure' => $this->getRequest()->isSecure()]
                    )
                ]
            ]
        ];

        $genderConfig = "";

        // get customer
        if ($this->customerSession->isLoggedIn()) {
            $genderConfig = $this->gender->getAdyenGenderFromMagentoGender(
                $this->customerSession->getCustomerData()->getGender()
            );

            // format to calendar date
            $dob = $this->customerSession->getCustomerData()->getDob();
            if ($dob) {
                $dob = strtotime($dob);
                $dob = date('m/d/Y', $dob);
            }
        }

        $adyenPmConfig['locale'] = $this->adyenHelper->getCurrentLocaleCode(
            $storeId
        );

        $adyenPmConfig['gender'] = $genderConfig;
        $adyenPmConfig['genderTypes'] =  $this->gender->getGenderTypes();

        $adyenPmConfig['ratePayId'] = $this->configHelper->getRatePayId($storeId);
        $adyenPmConfig['deviceIdentToken'] = hash("sha256", $this->session->getQuoteId() . date('c'));

        $config['payment']['adyenPm'] = $adyenPmConfig;
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
