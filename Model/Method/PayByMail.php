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

namespace Adyen\Payment\Model\Method;

/**
 * Class Sepa
 * @package Adyen\Payment\Model\Method
 */
class PayByMail extends \Magento\Payment\Model\Method\AbstractMethod
{

    const METHOD_CODE = 'adyen_pay_by_mail';

    /**
     * @var string
     */
    protected $_formBlockType = 'Adyen\Payment\Block\Form\PayByMail';

    /**
     * @var string
     */
    protected $_infoBlockType = 'Adyen\Payment\Block\Info\PayByMail';

    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'customer_';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * @var ResolverInterface
     */
    protected $_resolver;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canCaptureOnce = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * PayByMail constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_adyenLogger = $adyenLogger;
        $this->_adyenHelper = $adyenHelper;
        $this->_resolver = $resolver;
        $this->_storeManager = $storeManager;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
        }

        // do not let magento set status to processing
        $payment->setLastTransId($this->getTransactionId())->setIsTransactionPending(true);

        $fields = $this->getFormFields();

        $url = $this->getFormUrl();

        $count = 0;
        $size = count($fields);
        foreach ($fields as $field => $value) {

            if ($count == 0) {
                $url .= "?";
            }
            $url .= urlencode($field) . "=" . urlencode($value);

            if ($count != $size) {
                $url .= "&";
            }

            ++$count;
        }

        $payment->setAdditionalInformation('payment_url', $url);
        return $this;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFormFields()
    {

        $paymentInfo = $this->getInfoInstance();
        $order = $paymentInfo->getOrder();

        $realOrderId       = $order->getRealOrderId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();

        // check if paybymail has it's own skin
        $skinCode          = trim($this->getConfigData('skin_code'));
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
        $shopperLocale     = trim($this->getConfigData('shopper_locale'));
        $shopperLocale     = (!empty($shopperLocale)) ? $shopperLocale : $this->_resolver->getLocale();
        $countryCode       = trim($this->getConfigData('country_code'));
        $countryCode       = (!empty($countryCode)) ? $countryCode : false;

        // if directory lookup is enabled use the billingadress as countrycode
        if ($countryCode == false) {
            if (is_object($order->getBillingAddress()) && $order->getBillingAddress()->getCountry() != "") {
                $countryCode = $order->getBillingAddress()->getCountry();
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
        $formFields['countryCode']       = $countryCode;
        $formFields['sessionValidity']   = date(
            DATE_ATOM,
            mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
        );
        $formFields['shopperEmail']      = $shopperEmail;
        // recurring
        $recurringType                   = trim($this->_adyenHelper->getAdyenAbstractConfigData('recurring_type'));

        $formFields['recurringContract'] = $recurringType;


        $sessionValidity = $this->_adyenHelper->getAdyenPayByMailConfigData('session_validity');

        if ($sessionValidity == "") {
            $sessionValidity = 3;
        }

        $formFields['sessionValidity']   = date(
            DATE_ATOM,
            mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y"))
        );

        $adyFields['sessionValidity'] = date("c", strtotime("+". $sessionValidity. " days"));
        $formFields['shopperReference']  = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;

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
}