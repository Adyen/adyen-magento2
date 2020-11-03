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

use Adyen\Payment\Helper\ChargedCurrency;
use Magento\Payment\Gateway\CommandInterface;

class PayByMailCommand implements CommandInterface
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var ChargedCurrency
     */
    protected $chargedCurrency;

    /**
     * PayByMailCommand constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param ChargedCurrency $chargedCurrency
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        ChargedCurrency $chargedCurrency
    ) {
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * @param array $commandSubject
     * @return $this
     */
    public function execute(array $commandSubject)
    {
        $stateObject = \Magento\Payment\Gateway\Helper\SubjectReader::readStateObject($commandSubject);
        $payment = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($commandSubject);
        $payment = $payment->getPayment();

        // do not let magento set status to processing
        $payment->setIsTransactionPending(true);

        // generateUrl
        $payment->setAdditionalInformation('payment_url', $this->generatePaymentUrl($payment));

        // update status and state
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus($this->_adyenHelper->getAdyenAbstractConfigData('order_status'));
        $stateObject->setIsNotified(false);

        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param float|bool $paymentAmount
     * @return string
     */
    public function generatePaymentUrl($payment, $paymentAmount = false)
    {
        $url = $this->getFormUrl();
        $fields = $this->getFormFields($payment, $paymentAmount);

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
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param float|bool $paymentAmount
     * @return array
     */
    protected function getFormFields($payment, $paymentAmount = false)
    {
        $order = $payment->getOrder();

        $realOrderId = $order->getRealOrderId();
        $storeId = $order->getStore()->getId();

        // check if paybymail has it's own skin
        $skinCode = trim($this->_adyenHelper->getAdyenPayByMailConfigData('skin_code'));
        if ($skinCode == "") {
            // use HPP skin and HMAC
            $skinCode = $this->_adyenHelper->getAdyenHppConfigData('skin_code');
            $hmacKey = $this->_adyenHelper->getHmac();
            $shopperLocale = trim($this->_adyenHelper->getAdyenHppConfigData('shopper_locale', $storeId));
            $countryCode = trim($this->_adyenHelper->getAdyenHppConfigData('country_code', $storeId));
        } else {
            // use pay_by_mail skin and hmac
            $hmacKey = $this->_adyenHelper->getHmacPayByMail();
        }

        $adyenAmount = $this->chargedCurrency->getOrderAmountCurrency($order);
        $orderCurrencyCode = $adyenAmount->getCurrencyCode();

        //Use the $paymentAmount arg as total if this is a partial payment, full amount if not
        $amount = $this->_adyenHelper->formatAmount(
            $paymentAmount ?: $adyenAmount->getAmount(),
            $orderCurrencyCode
        );

        $merchantAccount = trim($this->_adyenHelper->getAdyenAbstractConfigData('merchant_account', $storeId));
        $shopperEmail = $order->getCustomerEmail();
        $customerId = $order->getCustomerId();

        // get locale from store
        $shopperLocale = (!empty($shopperLocale)) ? $shopperLocale : $this->_adyenHelper->getStoreLocale($storeId);
        $countryCode = (!empty($countryCode)) ? $countryCode : false;

        // if directory lookup is enabled use the billingadress as countrycode
        if ($countryCode == false) {
            if (is_object($order->getBillingAddress()) && $order->getBillingAddress()->getCountryId() != "") {
                $countryCode = $order->getBillingAddress()->getCountryId();
            } else {
                $countryCode = "";
            }
        }

        $deliveryDays = $this->_adyenHelper->getAdyenHppConfigData('delivery_days', $storeId);
        $deliveryDays = (!empty($deliveryDays)) ? $deliveryDays : 5;

        $formFields = [];
        $formFields['merchantAccount'] = $merchantAccount;
        $formFields['merchantReference'] = $realOrderId;
        $formFields['paymentAmount'] = (int)$amount;
        $formFields['currencyCode'] = $orderCurrencyCode;
        $formFields['shipBeforeDate'] = date(
            "Y-m-d",
            mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $deliveryDays, date("Y"))
        );
        $formFields['skinCode'] = $skinCode;
        $formFields['shopperLocale'] = $shopperLocale;
        if ($countryCode != "") {
            $formFields['countryCode'] = $countryCode;
        }

        $formFields['shopperEmail'] = $shopperEmail;
        // recurring
        $recurringType = $this->_adyenHelper->getRecurringTypeFromOneclickRecurringSetting($storeId);

        $sessionValidity = $this->_adyenHelper->getAdyenPayByMailConfigData('session_validity', $storeId);

        if ($sessionValidity == "") {
            $sessionValidity = 3;
        }

        $formFields['sessionValidity'] = date("c", strtotime("+" . $sessionValidity . " days"));

        if ($customerId > 0) {
            $formFields['recurringContract'] = $recurringType;
            $formFields['shopperReference'] = $customerId;
        }

        // Sign request using secret key
        $merchantSig = \Adyen\Util\Util::calculateSha256Signature($hmacKey, $formFields);
        $formFields['merchantSig'] = $merchantSig;

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
