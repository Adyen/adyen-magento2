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

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Pos extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{

    const METHOD_CODE = 'adyen_pos';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'customer_';

    protected $_infoBlockType = 'Adyen\Payment\Block\Info\Pos';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = false;


    /**
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;


    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * Currency factory
     *
     * @var CurrencyFactory
     */
    protected $_currencyFactory;

    /**
     * Request object
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
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
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
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
        $this->_paymentRequest = $paymentRequest;
        $this->_urlBuilder = $urlBuilder;
        $this->_adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        $this->resolver = $resolver;
        $this->_adyenLogger = $adyenLogger;
        $this->_request = $request;
        $this->_currencyFactory = $currencyFactory;
    }

    protected $_paymentMethodType = 'hpp';
    public function getPaymentMethodType() {
        return $this->_paymentMethodType;
    }

    public function initialize($paymentAction, $stateObject)
    {
        /*
         * do not send order confirmation mail after order creation wait for
         * Adyen AUTHORIISATION notification
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus($this->_adyenHelper->getAdyenAbstractConfigData('order_status'));
        $stateObject->setIsNotified(false);
    }

    /**
     * Assign data to info model instance
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        parent::assignData($data);
        return $this;
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('adyen/process/redirectPos',['_secure' => $this->_getRequest()->isSecure()]);
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
     * Post request to gateway and return response
     *
     * @param Object $request
     * @param ConfigInterface $config
     *
     * @return Object
     *
     * @throws \Exception
     */
    public function postRequest(DataObject $request, ConfigInterface $config)
    {
        // Implement postRequest() method.
    }


    public function getLaunchLink()
    {
        $paymentInfo = $this->getInfoInstance();
        $order = $paymentInfo->getOrder();

        $realOrderId            = $order->getRealOrderId();
        $orderCurrencyCode      = $order->getOrderCurrencyCode();
        $amount                 = $this->_adyenHelper->formatAmount($order->getGrandTotal(), $orderCurrencyCode);
        $shopperEmail           = $order->getCustomerEmail();
        $customerId             = $order->getCustomerId();
        $callbackUrl            = $this->_urlBuilder->getUrl('adyen/process/resultpos',['_secure' => $this->_getRequest()->isSecure()]);
        $addReceiptOrderLines   = $this->_adyenHelper->getAdyenPosConfigData("add_receipt_order_lines");
        $recurringContract      = $this->_adyenHelper->getAdyenPosConfigData('recurring_type');
        $currencyCode           = $orderCurrencyCode;
        $paymentAmount          = $amount;
        $merchantReference      = $realOrderId;
        $shopperReference       = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;
        $shopperEmail           = $shopperEmail;

        $recurringParams = "";
        if($order->getPayment()->getAdditionalInformation("store_cc") != "") {
            $recurringParams = "&recurringContract=".urlencode($recurringContract)."&shopperReference=".urlencode($shopperReference). "&shopperEmail=".urlencode($shopperEmail);
        }

        $receiptOrderLines = "";
        if($addReceiptOrderLines) {
            $orderLines = base64_encode($this->getReceiptOrderLines($order));
            $receiptOrderLines = "&receiptOrderLines=" . urlencode($orderLines);
        }

        // extra parameters so that you alway's return these paramters from the application
        $extra_paramaters   = urlencode("/?originalCustomCurrency=".$currencyCode."&originalCustomAmount=".$paymentAmount. "&originalCustomMerchantReference=".$merchantReference . "&originalCustomSessionId=".session_id());
        $launchlink         = "adyen://payment?sessionId=".session_id()."&amount=".$paymentAmount."&currency=".$currencyCode."&merchantReference=".$merchantReference. $recurringParams . $receiptOrderLines .  "&callback=".$callbackUrl . $extra_paramaters;

        $this->_adyenLogger->debug(print_r($launchlink, true));

        return $launchlink;
    }

    private function getReceiptOrderLines($order) {

        $myReceiptOrderLines = "";

        // temp
        $currency = $order->getOrderCurrencyCode();

        $formattedAmountValue = $this->_currencyFactory->create()->format(
            $order->getGrandTotal(),
            array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
            false
        );

        $taxAmount = $order->getTaxAmount();
        $formattedTaxAmount = $this->_currencyFactory->create()->format(
            $taxAmount,
            array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
            false
        );

        $paymentAmount = "1000";

        $myReceiptOrderLines .= "---||C\n".
            "====== YOUR ORDER DETAILS ======||CB\n".
            "---||C\n".
            " No. Description |Piece  Subtotal|\n";

        foreach ($order->getItemsCollection() as $item) {
            //skip dummies
            if ($item->isDummy()) continue;
            $singlePriceFormat = $this->_currencyFactory->create()->format(
                $item->getPriceInclTax(),
                array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
                false
            );

            $itemAmount = $item->getPriceInclTax() * (int) $item->getQtyOrdered();
            $itemAmountFormat = $this->_currencyFactory->create()->format(
                $itemAmount,
                array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
                false
            );

            $myReceiptOrderLines .= "  " . (int) $item->getQtyOrdered() . "  " . trim(substr($item->getName(),0, 25)) . "| " . $currency . " " . $singlePriceFormat . "  " . $currency . " " . $itemAmountFormat . "|\n";
        }

        //discount cost
        if($order->getDiscountAmount() > 0 || $order->getDiscountAmount() < 0)
        {
            $discountAmountFormat = $this->_currencyFactory->create()->format(
                $order->getDiscountAmount(),
                array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $this->__('Total Discount') . "| " . $currency . " " . $discountAmountFormat ."|\n";
        }

        //shipping cost
        if($order->getShippingAmount() > 0 || $order->getShippingTaxAmount() > 0)
        {
            $shippingAmountFormat = $this->_currencyFactory->create()->format(
                $order->getShippingAmount(),
                array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $order->getShippingDescription() . "| " . $currency . " " . $shippingAmountFormat ."|\n";

        }

        if($order->getPaymentFeeAmount() > 0) {
            $paymentFeeAmount = $this->_currencyFactory->create()->format(
                $order->getPaymentFeeAmount(),
                array('display'=>\Magento\Framework\Currency::NO_SYMBOL),
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $this->__('Payment Fee') . "| " . $currency . " " . $paymentFeeAmount ."|\n";

        }

        $myReceiptOrderLines .=    "|--------|\n".
            "|Order Total:  ".$currency." ".$formattedAmountValue."|B\n".
            "|Tax:  ".$currency." ".$formattedTaxAmount."|B\n".
            "||C\n";

        //Cool new header for card details section! Default location is After Header so simply add to Order Details as separator
        $myReceiptOrderLines .= "---||C\n".
            "====== YOUR PAYMENT DETAILS ======||CB\n".
            "---||C\n";


        return $myReceiptOrderLines;

    }


    /**
     * Capture on Adyen
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::capture($payment, $amount);
        $this->_paymentRequest->capture($payment, $amount);
        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        // get pspReference
        $pspReference = $payment->getAdyenPspReference();

        $order = $payment->getOrder();
        // if amount is a full refund send a refund/cancelled request so if it is not captured yet it will cancel the order
        $grandTotal = $order->getGrandTotal();

        if($grandTotal == $amount) {
            $this->_paymentRequest->cancelOrRefund($payment);
        } else {
            $this->_paymentRequest->refund($payment, $amount);
        }

        return $this;
    }

}