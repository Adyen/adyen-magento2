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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Checkout;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;

class Success extends Template
{

    /**
     * @var Order $order
     */
    protected $order;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;


    /**
     * @var Data
     */
    protected $adyenHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Success constructor.
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Data $adyenHelper
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        Data $adyenHelper,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Render with the checkout component on the success page for the following cases:
     * PresentToShopper e.g. Multibanco
     * Received e.g. Bank Transfer IBAN
     * @return bool
     */
    public function renderAction()
    {
        if (
            !empty($this->getOrder()->getPayment()->getAdditionalInformation('resultCode')) &&
            !empty($this->getOrder()->getPayment()->getAdditionalInformation('action')) &&
            (
            in_array($this->getOrder()->getPayment()->getAdditionalInformation('resultCode'),
                [
                    PaymentResponseHandler::PRESENT_TO_SHOPPER,
                    PaymentResponseHandler::RECEIVED
                ]
            )
            )
        ) {
            return true;
        }
        return false;
    }

    public function getAction()
    {
        return json_encode($this->getOrder()->getPayment()->getAdditionalInformation('action'));
    }

    public function getLocale()
    {
        return $this->adyenHelper->getCurrentLocaleCode(
            $this->storeManager->getStore()->getId()
        );
    }

    public function getClientKey()
    {
        return $this->adyenHelper->getClientKey();
    }

    public function getEnvironment()
    {
        return $this->adyenHelper->getCheckoutEnvironment(
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        if ($this->order == null) {
            $this->order = $this->orderFactory->create()->load($this->checkoutSession->getLastOrderId());
        }
        return $this->order;
    }

}
