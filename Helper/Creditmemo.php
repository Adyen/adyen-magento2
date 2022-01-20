<?php


namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo as CreditmemoResourceModel;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\Creditmemo as AdyenCreditmemoModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo as MagentoCreditmemoModel;
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
     * @throws AlreadyExistsException
     */
    public function createAdyenCreditmemo(
        Order\Payment $payment,
        string $pspReference,
        string $originalReference,
        int $refundAmountInCents
    ): \Adyen\Payment\Model\Creditmemo
    {
        $order = $payment->getOrder();

        /** @var \Adyen\Payment\Api\Data\OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $payment->getEntityId());

        /** @var AdyenCreditmemoModel $adyenCreditmemo */
        $adyenCreditmemo = $this->adyenCreditmemoFactory->create();
        $adyenCreditmemo->setPspreference($pspReference);
        $adyenCreditmemo->setAdyenPaymentOrderId($adyenOrderPayment[\Adyen\Payment\Api\Data\OrderPaymentInterface::ENTITY_ID]);
        $adyenCreditmemo->setAmount($this->adyenDataHelper->originalAmount($refundAmountInCents, $order->getBaseCurrencyCode()));
        // TODO: Add credit memo status here
        $this->adyenCreditmemoResourceModel->save($adyenCreditmemo);

        return $adyenCreditmemo;
    }

    /**
     * Link all the adyen_creditmemos related to the adyen_order_payment with the given magento entity of the creditmemo
     * @throws AlreadyExistsException
     */
    public function linkAndUpdateAdyenCreditmemos(Payment $adyenOrderPayment, MagentoCreditmemoModel $magentoCreditmemo)
    {
        $adyenCreditmemoLoader = $this->adyenCreditmemoFactory->create();

        $adyenCreditmemos = $this->adyenCreditmemoResourceModel->getAdyenCreditmemosByAdyenPaymentid($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
        if (!is_null($adyenCreditmemos)) {
            foreach ($adyenCreditmemos as $adyenCreditmemo) {
                /** @var AdyenCreditmemoModel $curAdyenCreditmemo */
                $curAdyenCreditmemo = $adyenCreditmemoLoader->load($adyenCreditmemo[CreditmemoInterface::ENTITY_ID], CreditmemoInterface::ENTITY_ID);
                $curAdyenCreditmemo->setCreditmemoId($magentoCreditmemo->getEntityId());
                $this->adyenCreditmemoResourceModel->save($curAdyenCreditmemo);
            }
        }
    }


}