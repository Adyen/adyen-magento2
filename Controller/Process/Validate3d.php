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

namespace Adyen\Payment\Controller\Process;

class Validate3d extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $_adyenLogger;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * Validate3d constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
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

    /**
     * Validate 3D secure payment
     */
    public function execute()
    {
        $active = null;

        // check if 3d is active
        $order = $this->_getOrder();

        if ($order->getPayment()) {
            $active = $order->getPayment()->getAdditionalInformation('3dActive');
        }

        // check if 3D secure is active. If not just go to success page
        if ($active) {
            $this->_adyenLogger->addAdyenResult("3D secure is active");

            // check if it is already processed
            if ($this->getRequest()->isPost()) {

                $this->_adyenLogger->addAdyenResult("Process 3D secure payment");
                $requestMD = $this->getRequest()->getPost('MD');
                $requestPaRes = $this->getRequest()->getPost('PaRes');
                $md = $order->getPayment()->getAdditionalInformation('md');

                if ($requestMD == $md) {

                    $order->getPayment()->setAdditionalInformation('paResponse', $requestPaRes);

                    try {
                        $result = $order->getPayment()->getMethodInstance()->authorise3d($order->getPayment());
                    } catch (\Exception $e) {
                        $this->_adyenLogger->addAdyenResult("Process 3D secure payment was refused");
                        $result = 'Refused';
                    }

                    $this->_adyenLogger->addAdyenResult("Process 3D secure payment result is: " . $result);

                    // check if authorise3d was successful
                    if ($result == 'Authorised') {
                        $order->addStatusHistoryComment(__('3D-secure validation was successful'))->save();
                        $this->_redirect('checkout/onepage/success');
                    } else {
                        $order->addStatusHistoryComment(__('3D-secure validation was unsuccessful.'))->save();
                        $this->_adyenHelper->cancelOrder($order);
                        $this->messageManager->addErrorMessage("3D-secure validation was unsuccessful");
                        
                        // reactivate the quote
                        $session = $this->_getCheckout();

                        // restore the quote
                        $session->restoreQuote();

                        $this->_redirect('checkout/cart');
                    }
                }
            } else {
                $this->_adyenLogger->addAdyenResult("Customer was redirected to bank for 3D-secure validation.");
                $order->addStatusHistoryComment(
                    __('Customer was redirected to bank for 3D-secure validation.')
                )->save();

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