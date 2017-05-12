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

namespace Adyen\Payment\Gateway\Command;

use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\CommandInterface;

class PayByMailCommand implements CommandInterface
{

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
     * PayByMailCommand constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_resolver = $resolver;
        $this->_adyenLogger = $adyenLogger;
    }
    /**
     * @param array $commandSubject
     * @return $this
     */
    public function execute(array $commandSubject)
    {
        $stateObject = \Magento\Payment\Gateway\Helper\SubjectReader::readStateObject($commandSubject);
        $payment =\Magento\Payment\Gateway\Helper\SubjectReader::readPayment($commandSubject);
        $payment = $payment->getPayment();

        // do not let magento set status to processing
        $payment->setIsTransactionPending(true);

        // generateUrl
        $payment->setAdditionalInformation('payment_url', $this->_generatePaymentUrl($payment));

        // update status and state
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus($this->_adyenHelper->getAdyenAbstractConfigData('order_status'));
        $stateObject->setIsNotified(false);
        
        return $this;
    }

    /**
     * @param $payment
     * @return string
     */
    protected function _generatePaymentUrl($payment)
    {
        $url = $this->getFormUrl();
        $fields = $this->getFormFields($payment);

        $count = 1;
        $size = count($fields);
        foreach ($fields as $field => $value) {
            if ($count == 1) {
                $url .= "?";
            }
            $url .= urlencode($field) . "=" . urlencode($value);

            if ($count != $size) {
                $url .= "&";
            }

            ++$count;
        }
        return $url;
    }

    /**
     * @return string
     */
    public function getFormUrl()
    {
        if ($this->_adyenHelper->isDemoMode()) {
            $url = 'https://test.adyen.com/hpp/pay.shtml';
        } else {
            $url = 'https://live.adyen.com/hpp/pay.shtml';
        }
        return $url;
    }


    /**
     * @param $payment
     * @return array
     */
    protected function getFormFields($payment)
    {
        $order = $payment->getOrder();

        $realOrderId       = $order->getRealOrderId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();

        // check if paybymail has it's own skin
        $skinCode          = trim($this->_adyenHelper->getAdyenPayByMailConfigData('skin_code'));
        if ($skinCode == "") {
            // use HPP skin and HMAC
            $skinCode = $this->_adyenHelper->getAdyenHppConfigData('skin_code');
            $hmacKey           = $this->_adyenHelper->getHmac();
        } else {
            // use pay_by_mail skin and hmac
            $hmacKey = $this->_adyenHelper->getHmacPayByMail();
        }

        $amount            = $this->_adyenHelper->formatAmount($order->getGrandTotal(), $orderCurrencyCode);
        $merchantAccount   = trim($this->_adyenHelper->getAdyenAbstractConfigData('merchant_account'));
        $shopperEmail      = $order->getCustomerEmail();
        $customerId        = $order->getCustomerId();
        $shopperLocale     = trim($this->_adyenHelper->getAdyenHppConfigData('shopper_locale'));
        $shopperLocale     = (!empty($shopperLocale)) ? $shopperLocale : $this->_resolver->getLocale();
        $countryCode       = trim($this->_adyenHelper->getAdyenHppConfigData('country_code'));
        $countryCode       = (!empty($countryCode)) ? $countryCode : false;

        // if directory lookup is enabled use the billingadress as countrycode
        if ($countryCode == false) {
            if (is_object($order->getBillingAddress()) && $order->getBillingAddress()->getCountry() != "") {
                $countryCode = $order->getBillingAddress()->getCountry();
            } else {
                $countryCode = "";
            }
        }

        $deliveryDays                   = $this->_adyenHelper->getAdyenHppConfigData('delivery_days');
        $deliveryDays                   = (!empty($deliveryDays)) ? $deliveryDays : 5;

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
        if ($countryCode != "") {
            $formFields['countryCode']       = $countryCode;
        }

        $formFields['shopperEmail']      = $shopperEmail;
        // recurring
        $recurringType                   = trim($this->_adyenHelper->getAdyenAbstractConfigData('recurring_type'));
        
        $sessionValidity = $this->_adyenHelper->getAdyenPayByMailConfigData('session_validity');

        if ($sessionValidity == "") {
            $sessionValidity = 3;
        }

        $formFields['sessionValidity'] = date("c", strtotime("+". $sessionValidity. " days"));

        if ($customerId > 0) {
            $formFields['recurringContract'] = $recurringType;
            $formFields['shopperReference']  = $customerId;
        }

        // Sort the array by key using SORT_STRING order
        ksort($formFields, SORT_STRING);

        // Generate the signing data string
        $signData = implode(":", array_map([$this, 'escapeString'],
            array_merge(array_keys($formFields), array_values($formFields))));

        $merchantSig = base64_encode(hash_hmac('sha256', $signData, pack("H*", $hmacKey), true));

        $formFields['merchantSig']      = $merchantSig;

        $this->_adyenLogger->addAdyenDebug(print_r($formFields, true));

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
}