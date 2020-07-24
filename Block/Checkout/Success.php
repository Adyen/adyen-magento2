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

namespace Adyen\Payment\Block\Checkout;

/**
 * Billing agreement information on Order success page
 */
class Success extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Sales\Model\Order $order
     */
    protected $order;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Checkout\Model\OrderFactory
     */
    protected $orderFactory;


    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Success constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->priceHelper = $priceHelper;
        $this->adyenHelper = $adyenHelper;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Return Boleto PDF url
     *
     * @return string
     */
    protected function _toHtml()
    {
        if ($this->isBoletoPayment()) {
            $this->addData(
                [
                    'boleto_pdf_url' => $this->getBoletoPdfUrl()
                ]
            );
        }
        return parent::_toHtml();
    }

    /**
     * Detect if Boleto is used as payment method
     *
     * @return bool
     */
    public function isBoletoPayment()
    {
        if ($this->getOrder()->getPayment() &&
            $this->getOrder()->getPayment()->getMethod() == \Adyen\Payment\Model\Ui\AdyenBoletoConfigProvider::CODE) {
            return true;
        }
        return false;
    }

    /**
     * @return null|\string[]
     */
    public function getBoletoData()
    {
        if ($this->isBoletoPayment()) {
            return $this->getOrder()->getPayment()->getAdditionalInformation('action');
        }
        return null;
    }

    /**
     * Get Banktransfer additional data
     *
     * @return array|string[]
     */
    public function getBankTransferData()
    {
        $result = [];
        if (!empty($this->getOrder()->getPayment()) &&
            !empty($this->getOrder()->getPayment()->getAdditionalInformation('bankTransfer.owner'))
        ) {
            $result = $this->getOrder()->getPayment()->getAdditionalInformation();
        }

        return $result;
    }

    /**
     * Get multibanco additional data
     *
     * @return array|string[]
     */
    public function getMultibancoData()
    {
        $result = [];
        if (empty($this->getOrder()->getPayment())) {
            return $result;
        }
        $action = $this->getOrder()->getPayment()->getAdditionalInformation('action');
        if (!empty($action["paymentMethodType"]) &&
            (strcmp($action["paymentMethodType"], 'multibanco') === 0)
        ) {
            $result = $action;
        }

        return $result;
    }

    /**
     * If PresentToShopper resultCode and action has provided render this with the checkout component on the success page
     * @return bool
     */
    public function renderAction()
    {
        if (
            !empty($this->getOrder()->getPayment()->getAdditionalInformation('resultCode')) &&
            $this->getOrder()->getPayment()->getAdditionalInformation('resultCode') == 'PresentToShopper' &&
            !empty($this->getOrder()->getPayment()->getAdditionalInformation('action'))
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

    public function getOriginKey()
    {
        return $this->adyenHelper->getOriginKeyForBaseUrl();
    }

    public function getEnvironment()
    {
        return $this->adyenHelper->getCheckoutEnvironment(
            $this->storeManager->getStore()->getId()
        );
    }


    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        if ($this->order == null) {
            $this->order = $this->orderFactory->create()->load($this->checkoutSession->getLastOrderId());
        }
        return $this->order;
    }
}
