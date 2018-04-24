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

use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Model\ResourceModel\Order\Payment as OrderPaymentResource;

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
     * @var \Adyen\Payment\Model\Api\PaymentRequest
     */
    protected $_paymentRequest;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var PaymentTokenFactoryInterface
     */
    private $paymentTokenFactory;

    /**
     * @var OrderPaymentExtensionInterfaceFactory
     */
    private $paymentExtensionFactory;

    /**
     * @var OrderPaymentResource
     */
    private $orderPaymentResource;

    /**
     * Validate3d constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param OrderPaymentResource $orderPaymentResource
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        OrderPaymentResource $orderPaymentResource
    ) {
        parent::__construct($context);
        $this->_adyenLogger = $adyenLogger;
        $this->_adyenHelper = $adyenHelper;
        $this->_paymentRequest = $paymentRequest;
        $this->_orderRepository = $orderRepository;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->orderPaymentResource = $orderPaymentResource;
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
            $success = $order->getPayment()->getAdditionalInformation('3dSuccess');
        }

        // check if 3D secure is active. If not just go to success page
        if ($active && $success != true) {

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
                        /**
                         * Magento should allow this.
                         * https://github.com/magento/magento2/issues/5819
                         */
//                        $result = $order->getPayment()->getMethodInstance()->executeCommand(
//                            'authorise_3d',
//                            ['payment' => $order->getPayment(), 'amount' => $order->getGrandTotal()]
//                        );
                        // old fashion way:
                        $result = $this->_authorise3d($order->getPayment());
                        $resultCode = $result['resultCode'];
                    } catch (\Exception $e) {
                        $this->_adyenLogger->addAdyenResult("Process 3D secure payment was refused");
                        $resultCode = 'Refused';
                    }

                    $this->_adyenLogger->addAdyenResult("Process 3D secure payment result is: " . $resultCode);

                    // check if authorise3d was successful
                    if ($resultCode == 'Authorised') {
                        $order->addStatusHistoryComment(__('3D-secure validation was successful'))->save();
                        // set back to false so when pressed back button on the success page it will reactivate 3D secure
                        $order->getPayment()->setAdditionalInformation('3dActive', '');
                        $order->getPayment()->setAdditionalInformation('3dSuccess', true);
                        $this->_orderRepository->save($order);

                        try {
                            $additionalData = $result['additionalData'];
                            $token = $additionalData['recurring.recurringDetailReference'];
                            $expirationDate = $additionalData['expiryDate'];
                            $cardType = $additionalData['paymentMethod'];
                            $cardSummary = $additionalData['cardSummary'];

                            /** @var PaymentTokenInterface $paymentToken */
                            $paymentToken = $this->paymentTokenFactory->create(PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD);
                            $paymentToken->setGatewayToken($token);
                            $paymentToken->setExpiresAt($this->getExpirationDate($expirationDate));
                            $details = [
                                'type' => $cardType,
                                'maskedCC' => $cardSummary,
                                'expirationDate' => $expirationDate
                            ];
                            $paymentToken->setTokenDetails(json_encode($details));

                            $extensionAttributes = $this->getExtensionAttributes($order->getPayment());
                            $extensionAttributes->setVaultPaymentToken($paymentToken);
                            $orderPayment = $order->getPayment()->setExtensionAttributes($extensionAttributes);

                            $add = unserialize($orderPayment->getAdditionalData());
                            $add['force_save'] = true;
                            $orderPayment->setAdditionalData(serialize($add));

                            $this->orderPaymentResource->save($orderPayment);
                        } catch(\Exception $e) {
                            $this->_adyenLogger->error((string)$e->getMessage());
                        }

                        $this->_redirect('checkout/onepage/success', ['_query' => ['utm_nooverride' => '1']]);
                    } else {
                        $order->addStatusHistoryComment(__('3D-secure validation was unsuccessful.'))->save();

                        // Move the order from PAYMENT_REVIEW to NEW, so that can be cancelled
                        $order->setState(\Magento\Sales\Model\Order::STATE_NEW);
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
                    __('Customer was redirected to bank for 3D-secure validation. Once the shopper authenticated, the order status will be updated accordingly. 
                        <br />Make sure that your notifications are being processed! 
                        <br />If the order is stuck on this status, the shopper abandoned the session. The payment can be seen as unsuccessful. 
                        <br />The order can be automatically cancelled based on the OFFER_CLOSED notification. Please contact Adyen Support to enable this.'))->save();
                $this->_view->loadLayout();
                $this->_view->getLayout()->initMessages();
                $this->_view->renderLayout();
            }
        } else {
            $this->_redirect('checkout/onepage/success', ['_query' => ['utm_nooverride' => '1']]);
        }
    }

    /**
     * Get payment extension attributes
     * @param InfoInterface $payment
     * @return OrderPaymentExtensionInterface
     */
    private function getExtensionAttributes(InfoInterface $payment)
    {
        $extensionAttributes = $payment->getExtensionAttributes();
        if (null === $extensionAttributes) {
            $extensionAttributes = $this->paymentExtensionFactory->create();
            $payment->setExtensionAttributes($extensionAttributes);
        }
        return $extensionAttributes;
    }

    /**
     * @param $expirationDate
     * @return string
     */
    private function getExpirationDate($expirationDate)
    {
        $expirationDate = explode('/', $expirationDate);

        //add leading zero to month
        $month = sprintf("%02d", $expirationDate[0]);

        $expDate = new \DateTime(
            $expirationDate[1]
            . '-'
            . $month
            . '-'
            . '01'
            . ' '
            . '00:00:00',
            new \DateTimeZone('UTC')
        );

        // add one month
        $expDate->add(new \DateInterval('P1M'));
        return $expDate->format('Y-m-d 00:00:00');
    }

    /**
     * Called by validate3d controller when cc payment has 3D secure
     *
     * @param $payment
     * @return mixed
     * @throws \Exception
     */
    protected function _authorise3d($payment)
    {
        try {
            $response = $this->_paymentRequest->authorise3d($payment);
        } catch(\Exception $e) {
            throw $e;
        }
        return $response;
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
