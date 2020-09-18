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

use Adyen\Payment\Api\AdyenThreeDSProcessInterface;
use Magento\Framework\App\Request\Http as Http;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterface;
use Magento\Sales\Api\Data\OrderPaymentExtensionInterfaceFactory;
use Magento\Sales\Model\ResourceModel\Order\Payment as OrderPaymentResource;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;

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

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

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
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var AdyenThreeDSProcessInterface
     */
    private $adyenThreeDSProcess;

    /**
     * Redirect constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param PaymentTokenFactoryInterface $paymentTokenFactory
     * @param OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory
     * @param OrderPaymentResource $orderPaymentResource
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param AdyenThreeDSProcessInterface $adyenThreeDSProcess
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        PaymentTokenFactoryInterface $paymentTokenFactory,
        OrderPaymentExtensionInterfaceFactory $paymentExtensionFactory,
        OrderPaymentResource $orderPaymentResource,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        AdyenThreeDSProcessInterface $adyenThreeDSProcess
    ) {
        parent::__construct($context);
        $this->_adyenLogger = $adyenLogger;
        $this->_adyenHelper = $adyenHelper;
        $this->_paymentRequest = $paymentRequest;
        $this->_orderRepository = $orderRepository;
        $this->paymentTokenFactory = $paymentTokenFactory;
        $this->paymentExtensionFactory = $paymentExtensionFactory;
        $this->orderPaymentResource = $orderPaymentResource;
        $this->serializer = $serializer;
        $this->adyenThreeDSProcess = $adyenThreeDSProcess;

        if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
            $request = $this->getRequest();
            if ($request instanceof Http && $request->isPost()) {
                $request->setParam('isAjax', true);
                $request->getHeaders()->addHeaderLine('X_REQUESTED_WITH', 'XMLHttpRequest');
            }
        }
    }

    /**
     * Validate 3D secure payment
     */
    public function execute()
    {
        $active = null;

        $order = $this->_getOrder();
        $requestMD = $this->getRequest()->getParam('MD');
        $requestPaRes = $this->getRequest()->getParam('PaRes');

        $authorizationResult = $this->adyenThreeDSProcess->authorize(
            $order,
            $requestMD,
            $requestPaRes
        );

        switch ($authorizationResult) {
            case AdyenThreeDSProcessInterface::AUTHORIZED:
            case AdyenThreeDSProcessInterface::ALREADY_SUCCESSFUL:
                $this->_redirect('checkout/onepage/success', ['_query' => ['utm_nooverride' => '1']]);
                return;
            case AdyenThreeDSProcessInterface::UNSUCCESSFUL:
                $this->messageManager->addErrorMessage("3D-secure validation was unsuccessful");
                // reactivate the quote
                $session = $this->_getCheckout();
                // restore the quote
                $session->restoreQuote();
                $this->_redirect($this->_adyenHelper->getAdyenAbstractConfigData('return_path'));
                return;
            case AdyenThreeDSProcessInterface::NEEDS_REDIRECT:
            default:
                $this->_view->loadLayout();
                $this->_view->getLayout()->initMessages();
                $this->_view->renderLayout();
        }
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

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_orderFactory = $this->_objectManager->get(\Magento\Sales\Model\OrderFactory::class);
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_objectManager->get(\Magento\Checkout\Model\Session::class);
    }

    /**
     * @return mixed
     */
    protected function _getQuote()
    {
        return $this->_objectManager->get(\Magento\Quote\Model\Quote::class);
    }

    /**
     * @return mixed
     */
    protected function _getQuoteManagement()
    {
        return $this->_objectManager->get(\Magento\Quote\Model\QuoteManagement::class);
    }
}
