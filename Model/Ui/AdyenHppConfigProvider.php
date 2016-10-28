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
    protected $_paymentHelper;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;


    /**
     * AdyenHppConfigProvider constructor.
     *
     * @param PaymentHelper $paymentHelper
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->_paymentHelper = $paymentHelper;
        $this->_adyenHelper = $adyenHelper;
        $this->_request = $request;
        $this->_urlBuilder = $urlBuilder;
        $this->_customerSession = $customerSession;
    }

    /**
     * Set configuration for AdyenHPP payemnt method
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
                    'redirectUrl' => $this->_urlBuilder->getUrl(
                        'adyen/process/redirect', ['_secure' => $this->_getRequest()->isSecure()])
                ]
            ]
        ];

        // get customer
        if ($this->_customerSession->isLoggedIn()) {

            $gender = \Adyen\Payment\Model\Gender::getAdyenGenderFromMagentoGender(
                $this->_customerSession->getCustomerData()->getGender()
            );

            // format to calendar date
            $dob = $this->_customerSession->getCustomerData()->getDob();
            $dob = strtotime($dob);
            $dob = date('m/d/Y', $dob);
        } else {
            $gender = "";
            $dob = "";
        }
        
        // add to config
        $config['payment'] ['adyenHpp']['gender'] = $gender;
        $config['payment'] ['adyenHpp']['dob'] = $dob;

        // gender types
        $config['payment'] ['adyenHpp']['genderTypes'] = \Adyen\Payment\Model\Gender::getGenderTypes();

        $paymentMethodSelectionOnAdyen =
            $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');

        $config['payment'] ['adyenHpp']['isPaymentMethodSelectionOnAdyen'] = $paymentMethodSelectionOnAdyen;
        $config['payment'] ['adyenHpp']['showGender'] = $this->_adyenHelper->getAdyenHppConfigDataFlag('show_gender');
        $config['payment'] ['adyenHpp']['showDob'] = $this->_adyenHelper->getAdyenHppConfigDataFlag('show_dob');
        $config['payment'] ['adyenHpp']['showTelephone'] =  $this->_adyenHelper->getAdyenHppConfigDataFlag(
            'show_telephone'
        );



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
}