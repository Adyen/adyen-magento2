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

namespace Adyen\Payment\Block\Redirect;

use Symfony\Component\Config\Definition\Exception\Exception;

class Redirect extends \Magento\Payment\Block\Form
{

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var  \Magento\Checkout\Model\Order
     */
    protected $_order;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var ResolverInterface
     */
    protected $_resolver;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * Redirect constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);

        $this->_adyenHelper = $adyenHelper;
        $this->_resolver = $resolver;
        $this->_adyenLogger = $adyenLogger;

        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
    }


    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        $url = "";
        try {
            if ($this->_order->getPayment()) {
                $paymentRoutine = $this->_adyenHelper->getAdyenHppConfigData('payment_routine');

                switch ($this->_adyenHelper->isDemoMode()) {
                    case true:
                        if ($paymentRoutine == 'single' && $this->getPaymentMethodSelectionOnAdyen()) {
                            $url = 'https://test.adyen.com/hpp/pay.shtml';
                        } else {
                            $url = ($this->getPaymentMethodSelectionOnAdyen())
                                ? 'https://test.adyen.com/hpp/select.shtml'
                                : "https://test.adyen.com/hpp/details.shtml";
                        }
                        break;
                    default:
                        if ($paymentRoutine == 'single' && $this->getPaymentMethodSelectionOnAdyen()) {
                            $url = 'https://live.adyen.com/hpp/pay.shtml';
                        } else {
                            $url = ($this->getPaymentMethodSelectionOnAdyen())
                                ? 'https://live.adyen.com/hpp/select.shtml'
                                : "https://live.adyen.com/hpp/details.shtml";
                        }
                        break;
                }
            }
        } catch(Exception $e) {
            // do nothing for now
            throw($e);
        }

        return $url;
    }

    /**
     * @return mixed
     */
    public function getPaymentMethodSelectionOnAdyen()
    {
        return $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');
    }

    /**
     * @return array
     */
    public function getFormFields()
    {
        $formFields = [];
        try {
            if ($this->_order->getPayment()) {

                $realOrderId       = $this->_order->getRealOrderId();
                $orderCurrencyCode = $this->_order->getOrderCurrencyCode();
                $skinCode          = trim($this->_adyenHelper->getAdyenHppConfigData('skin_code'));
                $amount            = $this->_adyenHelper->formatAmount(
                    $this->_order->getGrandTotal(), $orderCurrencyCode
                );
                $merchantAccount   = trim($this->_adyenHelper->getAdyenAbstractConfigData('merchant_account'));
                $shopperEmail      = $this->_order->getCustomerEmail();
                $customerId        = $this->_order->getCustomerId();
                $shopperIP         = $this->_order->getRemoteIp();
                $browserInfo       = $_SERVER['HTTP_USER_AGENT'];
                $deliveryDays      = $this->_adyenHelper->getAdyenHppConfigData('delivery_days');
                $shopperLocale     = trim($this->_adyenHelper->getAdyenHppConfigData('shopper_locale'));
                $shopperLocale     = (!empty($shopperLocale)) ? $shopperLocale : $this->_resolver->getLocale();
                $countryCode       = trim($this->_adyenHelper->getAdyenHppConfigData('country_code'));
                $countryCode       = (!empty($countryCode)) ? $countryCode : false;

                // if directory lookup is enabled use the billingadress as countrycode
                if ($countryCode == false) {
                    if ($this->_order->getBillingAddress() &&
                        $this->_order->getBillingAddress()->getCountryId() != "") {
                        $countryCode = $this->_order->getBillingAddress()->getCountryId();
                    }
                }

                $formFields = [];
                $formFields['merchantAccount']   = $merchantAccount;
                $formFields['merchantReference'] = $realOrderId;
                $formFields['paymentAmount']     = (int)$amount;
                $formFields['currencyCode']      = $orderCurrencyCode;
                $formFields['shipBeforeDate']    = date(
                    "Y-m-d",
                    mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $deliveryDays, date("Y"))
                );
                $formFields['skinCode']          = $skinCode;
                $formFields['shopperLocale']     = $shopperLocale;
                $formFields['countryCode']       = $countryCode;
                $formFields['shopperIP']         = $shopperIP;
                $formFields['browserInfo']       = $browserInfo;
                $formFields['sessionValidity']   = date(
                    DATE_ATOM,
                    mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
                );
                $formFields['shopperEmail']      = $shopperEmail;
                // recurring
                $recurringType                   = trim($this->_adyenHelper->getAdyenAbstractConfigData(
                    'recurring_type')
                );
                $brandCode                       = $this->_order->getPayment()->getAdditionalInformation("brand_code");

                // Paypal does not allow ONECLICK,RECURRING only RECURRING
                if ($brandCode == "paypal" && $recurringType == 'ONECLICK,RECURRING') {
                    $recurringType = "RECURRING";
                }

                $formFields['recurringContract'] = $recurringType;
                $formFields['shopperReference']  = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;
                //blocked methods
                $formFields['blockedMethods']    = "";

                $baseUrl = $this->_storeManager->getStore($this->getStore())
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);

                $formFields['resURL']            = $baseUrl . 'adyen/process/result';
                $hmacKey                         = $this->_adyenHelper->getHmac();


                if ($brandCode) {
                    $formFields['brandCode']     = $brandCode;
                }

                $issuerId = $this->_order->getPayment()->getAdditionalInformation("issuer_id");
                if ($issuerId) {
                    $formFields['issuerId']      = $issuerId;
                }

                // Sort the array by key using SORT_STRING order
                ksort($formFields, SORT_STRING);

                // Generate the signing data string
                $signData = implode(":", array_map([$this, 'escapeString'],
                    array_merge(array_keys($formFields), array_values($formFields))));

                $merchantSig = base64_encode(hash_hmac('sha256', $signData, pack("H*", $hmacKey), true));

                $formFields['merchantSig']      = $merchantSig;

                $this->_adyenLogger->addAdyenDebug(print_r($formFields, true));

            }

        } catch(Exception $e) {
            // do nothing for now
        }
        return $formFields;
    }
    /**
     * The character escape function is called from the array_map function in _signRequestParams
     *
     * @param $val
     * @return mixed
     */
    protected function escapeString($val)
    {
        return str_replace(':', '\\:', str_replace('\\', '\\\\', $val));
    }

    /**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
    }
}