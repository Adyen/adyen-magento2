<?php


namespace Adyen\Payment\Controller\Process;


class Redirect extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = false;




    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;




    protected $checkoutFactory;


    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    protected $_order;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context
//        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
//        $this->_customerSession = $customerSession;
    }


    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function execute()
    {

//        $session->clearQuote();
        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();

        // \Magento\Quote\Model\QuoteManagement $quoteManagement
        //$this->_checkout->place($this->_initToken());
//        $order = $this->quoteManagement->submit($this->_quote);


//        $orderId = $this->_getCheckout()->getLastOrderId();
//
//        $order = $this->_getOrder();
//
//        $payment = $order->getPayment()->getMethodInstance();
//
//        echo $payment->getCode();die();
//
//
//echo $order->getId();
//        die();
//
//        $quote->collectTotals();
//
////        print_r($quote->getPayment());die();
//
//        //$this->_quote->collectTotals();
////        $order = $this->quoteManagement->submit($this->_quote);
////\Magento\Quote\Model\QuoteManagement $quoteManagement
//
////        $this->_getQuoteManagement()->submit($quote);
//
//
////echo 'test';
////        print_r($this->_getQuote()->getBillingAddress()->getFirstname());die();
//
////        echo $quote->getBillingAddress()->getFirstname();
//////        echo $quote->getShippingAddress()->getFirstname();
////        die();
//
//        $this->_getQuoteManagement()->placeOrder($quote->getId());
//
//        // $this->cartManagement->placeOrder($this->_getCheckout()->getQuote()->getId());
//
//
////        $this->getOrder();
//
////        echo $quote->getId();
////        echo 'hier;';die();
////        $this->_quote->collectTotals();
////
////echo 'hier';die();
//        $url = "http://www.google.com";
//        $this->getResponse()->setRedirect($url);
//        return;




//        $this->_view->loadLayout();
//        $this->_view->getLayout()->initMessages();
//        $this->_view->renderLayout();
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    protected function _getQuote()
    {
        return $this->_objectManager->get('Magento\Quote\Model\Quote');
    }

    protected function _getQuoteManagement()
    {
        return $this->_objectManager->get('\Magento\Quote\Model\QuoteManagement');
    }

}