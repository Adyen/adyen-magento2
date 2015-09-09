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

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession
    ) {
        parent::__construct($context);
        $this->_customerSession = $customerSession;
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
        $this->_getQuote();
        echo 'hier;';die();
        $this->_quote->collectTotals();

echo 'hier';die();
        $url = "http://www.google.com";
        $this->getResponse()->setRedirect($url);
        return;




//        $this->_view->loadLayout();
//        $this->_view->getLayout()->initMessages();
//        $this->_view->renderLayout();
    }

    /**
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }
}