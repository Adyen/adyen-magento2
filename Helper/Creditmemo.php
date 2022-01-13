<?php


namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo as CreditmemoResourceModel;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;

use Magento\Sales\Model\Order;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order\CreditmemoFactory as MagentoCreditMemoFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as MagentoCreditmemoResourceModel;


class Creditmemo extends AbstractHelper
{
    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var OrderPaymentResourceModel
     */
    protected $orderPaymentResourceModel;

    /**
     * @var CreditmemoResourceModel
     */
    private $adyenCreditmemoResourceModel;

    /**
     * @var CreditmemoFactory
     */
    private $adyenCreditmemoFactory;

    /**
     * Creditmemo constructor.
     * @param Context $context
     * @param Data $adyenDataHelper
     * @param CreditmemoFactory $adyenCreditmemoFactory
     * @param CreditmemoResourceModel $adyenCreditmemoResourceModel
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     */
    public function __construct(
        Context $context,
        Data $adyenDataHelper,
        CreditmemoFactory $adyenCreditmemoFactory,
        CreditmemoResourceModel $adyenCreditmemoResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel
    )
    {
        parent::__construct($context);
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenCreditmemoFactory = $adyenCreditmemoFactory;
        $this->adyenCreditmemoResourceModel = $adyenCreditmemoResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * @param Order\Payment $payment
     * @param string $pspReference
     * @param string $originalReference
     * @param int $refundAmount
     * @return \Adyen\Payment\Model\Creditmemo
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function createAdyenCreditmemo(
        Order\Payment $payment,
        string $pspReference,
        string $originalReference,
        int $refundAmount
    ): \Adyen\Payment\Model\Creditmemo
    {
        $order = $payment->getOrder();

        $refundAmountInCents = $refundAmount * 100;

        /** @var \Adyen\Payment\Api\Data\OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $payment->getEntityId());

        /** @var \Adyen\Payment\Model\Creditmemo $adyenCreditmemo */
        $adyenCreditmemo = $this->adyenCreditmemoFactory->create();
        $adyenCreditmemo->setPspreference($pspReference);
        $adyenCreditmemo->setAdyenPaymentOrderId($adyenOrderPayment[\Adyen\Payment\Api\Data\OrderPaymentInterface::ENTITY_ID]);
        $adyenCreditmemo->setAmount($this->adyenDataHelper->originalAmount($refundAmountInCents, $order->getBaseCurrencyCode()));
        // TODO: Add credit memo status here
        $this->adyenCreditmemoResourceModel->save($adyenCreditmemo);

        return $adyenCreditmemo;
    }


}