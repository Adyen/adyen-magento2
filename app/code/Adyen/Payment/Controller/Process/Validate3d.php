<?php


namespace Adyen\Payment\Controller\Process;


class Validate3d extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    protected $_order;

    protected $_adyenLogger;

    protected $_adyenHelper;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper
    ) {
        parent::__construct($context);
        $this->_adyenLogger = $adyenLogger;
        $this->_adyenHelper = $adyenHelper;
    }

    public function execute()
    {
        $this->_adyenLogger->critical("IN Execute validate3d:");
        // check if 3d is active
        $order = $this->_getOrder();

        $active = $order->getPayment()->getAdditionalInformation('3dActive');

        $md = $order->getPayment()->getAdditionalInformation('md');

        // check if 3D secure is active. If not just go to success page
        if($active) {
            // check if it is already processed
            if ($this->getRequest()->isPost()) {

                $requestMD = $this->getRequest()->getPost('MD');
                $requestPaRes = $this->getRequest()->getPost('PaRes');

                if ($requestMD == $md) {

                    $order->getPayment()->setAdditionalInformation('paResponse', $requestPaRes);

                    try {
                        $result = $order->getPayment()->getMethodInstance()->authorise3d($order->getPayment());
                    } catch (Exception $e) {
                        $result = 'Refused';
                    }

                    // check if authorise3d was successful
                    if ($result == 'Authorised') {
                        $order->addStatusHistoryComment(__('3D-secure validation was successful'))->save();
                        $this->_redirect('checkout/onepage/success');
                    }
                    else {
                        $order->addStatusHistoryComment(__('3D-secure validation was unsuccessful.'))->save();
                        $this->_adyenHelper->cancelOrder($order);
                    }
                }
            } else {
                $order->addStatusHistoryComment(__('Customer was redirected to bank for 3D-secure validation.'))->save();

                $this->_view->loadLayout();
                $this->_view->getLayout()->initMessages();
                $this->_view->renderLayout();
            }
        } else {
            $this->_redirect('checkout/onepage/success/');
        }
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
}