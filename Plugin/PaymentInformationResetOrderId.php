<?php


namespace Adyen\Payment\Plugin;


use mysql_xdevapi\Exception;

class PaymentInformationResetOrderId
{
    /**
     * @var \Magento\Checkout\Model\Session
     *
     */
    protected $checkoutSession;
    /**
     * @var \Adyen\Payment\Logger\AdyenLogger
     */
    protected $adyenLogger;
    /**
     * PaymentInformationResetOrderId constructor.
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Adyen\Payment\Logger\AdyenLogger $adyenLogger
     */
    public function __construct(
        \Adyen\Payment\Logger\AdyenLogger $adyenLogger,
        \Magento\Checkout\Model\Session $checkoutSession
    )
    {
        $this->adyenLogger = $adyenLogger;
        $this->checkoutSession = $checkoutSession;
    }

    public function beforeSavePaymentInformationAndPlaceOrder()
    {
        try {
            $this->checkoutSession->getQuote()->setReservedOrderId(null);
        } catch (\Exception $e) {
            $this->adyenLogger->error($e->getMessage());
        }
    }
}
