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

class Pos extends \Magento\Payment\Block\Form
{

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
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * Currency factory
     *
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $_currencyFactory;

    /**
     * Pos constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = [],
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_checkoutSession = $checkoutSession;
        parent::__construct($context, $data);

        $this->_request = $context->getRequest();
        $this->_adyenHelper = $adyenHelper;
        $this->_adyenLogger = $adyenLogger;
        $this->_currencyFactory = $currencyFactory;

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
    public function getLaunchLink()
    {
        $launchlink = "";
        try {
            if ($this->_order->getPayment())
            {

                $realOrderId            = $this->_order->getRealOrderId();
                $orderCurrencyCode      = $this->_order->getOrderCurrencyCode();
                $amount                 = $this->_adyenHelper->formatAmount(
                    $this->_order->getGrandTotal(), $orderCurrencyCode
                );
                $shopperEmail           = $this->_order->getCustomerEmail();
                $customerId             = $this->_order->getCustomerId();
                $callbackUrl            = $this->_urlBuilder->getUrl('adyen/process/resultpos',
                    ['_secure' => $this->_getRequest()->isSecure()]);
                $addReceiptOrderLines   = $this->_adyenHelper->getAdyenPosConfigData("add_receipt_order_lines");
                $recurringContract      = $this->_adyenHelper->getAdyenPosConfigData('recurring_type');
                $currencyCode           = $orderCurrencyCode;
                $paymentAmount          = $amount;
                $merchantReference      = $realOrderId;

                $recurringParams = "";
                if ($this->_order->getPayment()->getAdditionalInformation("store_cc") != ""
                    && $customerId > 0
                ) {
                    $recurringParams = "&recurringContract=" . urlencode($recurringContract) . "&shopperReference=" .
                        urlencode($customerId) . "&shopperEmail=" . urlencode($shopperEmail);
                }

                $receiptOrderLines = "";
                if ($addReceiptOrderLines) {
                    $orderLines = base64_encode($this->_getReceiptOrderLines($this->_order));
                    $receiptOrderLines = "&receiptOrderLines=" . urlencode($orderLines);
                }

                // extra parameters so that you alway's return these paramters from the application
                $extraParamaters = urlencode("/?originalCustomCurrency=".$currencyCode."&originalCustomAmount=".
                    $paymentAmount. "&originalCustomMerchantReference=".
                    $merchantReference . "&originalCustomSessionId=".session_id());

                // Cash you can trigger by adding transactionType=CASH
                $launchlink = "adyen://payment?sessionId=".session_id() .
                    "&amount=".$paymentAmount."&currency=".$currencyCode."&merchantReference=".$merchantReference .
                    $recurringParams . $receiptOrderLines .  "&callback=".$callbackUrl . $extraParamaters;

                // cash not working see ticket
                // https://youtrack.is.adyen.com/issue/IOS-130#comment=102-20285
                // . "&transactionType=CASH";

                $this->_adyenLogger->addAdyenDebug(print_r($launchlink, true));
            }
        } catch(Exception $e) {
            // do nothing for now
            throw($e);
        }

        return $launchlink;
    }


    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    protected function _getReceiptOrderLines(\Magento\Sales\Model\Order $order)
    {
        $myReceiptOrderLines = "";

        $currency = $order->getOrderCurrencyCode();

        $formattedAmountValue = $this->_currencyFactory->create()->format(
            $order->getGrandTotal(),
            ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
            false
        );

        $taxAmount = $order->getTaxAmount();
        $formattedTaxAmount = $this->_currencyFactory->create()->format(
            $taxAmount,
            ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
            false
        );

        $myReceiptOrderLines .= "---||C\n".
            "====== YOUR ORDER DETAILS ======||CB\n".
            "---||C\n".
            " No. Description |Piece  Subtotal|\n";

        foreach ($order->getItemsCollection() as $item) {
            //skip dummies
            if ($item->isDummy()) {
                continue;
            };
            $singlePriceFormat = $this->_currencyFactory->create()->format(
                $item->getPriceInclTax(),
                ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
                false
            );

            $itemAmount = $item->getPriceInclTax() * (int) $item->getQtyOrdered();
            $itemAmountFormat = $this->_currencyFactory->create()->format(
                $itemAmount,
                ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
                false
            );

            $myReceiptOrderLines .= "  " . (int) $item->getQtyOrdered() . "  " . trim(substr($item->getName(), 0, 25)) .
                "| " . $currency . " " . $singlePriceFormat . "  " . $currency . " " . $itemAmountFormat . "|\n";
        }

        //discount cost
        if ($order->getDiscountAmount() > 0 || $order->getDiscountAmount() < 0) {
            $discountAmountFormat = $this->_currencyFactory->create()->format(
                $order->getDiscountAmount(),
                ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $this->__('Total Discount') . "| " .
                $currency . " " . $discountAmountFormat ."|\n";
        }

        //shipping cost
        if ($order->getShippingAmount() > 0 || $order->getShippingTaxAmount() > 0) {
            $shippingAmountFormat = $this->_currencyFactory->create()->format(
                $order->getShippingAmount(),
                ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $order->getShippingDescription() . "| " .
                $currency . " " . $shippingAmountFormat ."|\n";

        }

        if ($order->getPaymentFeeAmount() > 0) {
            $paymentFeeAmount = $this->_currencyFactory->create()->format(
                $order->getPaymentFeeAmount(),
                ['display'=>\Magento\Framework\Currency::NO_SYMBOL],
                false
            );
            $myReceiptOrderLines .= "  " . 1 . " " . $this->__('Payment Fee') . "| " .
                $currency . " " . $paymentFeeAmount ."|\n";

        }

        $myReceiptOrderLines .=    "|--------|\n".
            "|Order Total:  ".$currency." ".$formattedAmountValue."|B\n".
            "|Tax:  ".$currency." ".$formattedTaxAmount."|B\n".
            "||C\n";

        /*
         * New header for card details section!
         * Default location is After Header so simply add to Order Details as separator
         */
        $myReceiptOrderLines .= "---||C\n".
            "====== YOUR PAYMENT DETAILS ======||CB\n".
            "---||C\n";


        return $myReceiptOrderLines;
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