<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\CreditMemoInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\ResourceModel\CreditMemo\CreditMemo as CreditMemoResourceModel;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\CreditMemo as AdyenCreditMemoModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo as MagentoCreditMemoModel;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order\CreditmemoFactory as MagentoCreditMemoFactory;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as MagentoCreditMemoResourceModel;

/**
 * Helper class for anything related to the creditmemo entity
 *
 * @package Adyen\Payment\Helper
 */
class CreditMemo extends AbstractHelper
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
     * @var CreditMemoResourceModel
     */
    private $adyenCreditMemoResourceModel;

    /**
     * @var CreditmemoFactory
     */
    private $adyenCreditmemoFactory;

    /**
     * Creditmemo constructor.
     * @param Context $context
     * @param Data $adyenDataHelper
     * @param CreditmemoFactory $adyenCreditmemoFactory
     * @param CreditMemoResourceModel $adyenCreditMemoResourceModel
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     */
    public function __construct(
        Context $context,
        Data $adyenDataHelper,
        CreditmemoFactory $adyenCreditmemoFactory,
        CreditMemoResourceModel $adyenCreditMemoResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel
    )
    {
        parent::__construct($context);
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenCreditmemoFactory = $adyenCreditmemoFactory;
        $this->adyenCreditMemoResourceModel = $adyenCreditMemoResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
    }

    /**
     * Link all the adyen_creditmemos related to the adyen_order_payment with the passed creditMemoModel
     *
     * @param Order\Payment $payment
     * @param string $pspReference
     * @param string $originalReference
     * @param int $refundAmountInCents
     * @returns \Adyen\Payment\Model\CreditMemo
     * @throws AlreadyExistsException
     */

    public function createAdyenCreditMemo(
        Order\Payment $payment,
    ): \Adyen\Payment\Model\CreditMemo
    {
        $order = $payment->getOrder();

        // get adyen_order_payment record
        /** @var \Adyen\Payment\Api\Data\OrderPaymentInterface $adyenOrderPayment */
        $adyenOrderPayment = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $payment->getEntityId());

        // create adyen_credit_memo record
        /** @var AdyenCreditmemoModel $adyenCreditmemo */
        $adyenCreditMemo = $this->adyenCreditmemoFactory->create();
        $adyenCreditMemo->setPspreference($pspReference);
        $adyenCreditMemo->setOriginalReference($originalReference);
        $adyenCreditMemo->setAdyenPaymentOrderId($adyenOrderPayment[\Adyen\Payment\Api\Data\OrderPaymentInterface::ENTITY_ID]);
        $adyenCreditMemo->setAmount($this->adyenDataHelper->originalAmount($refundAmountInCents, $order->getBaseCurrencyCode()));
        // Once needed, a status update for the creditmemo can be added here.
        $this->adyenCreditMemoResourceModel->save($adyenCreditmemo);

        return $adyenCreditMemo;
    }

    /**
     * Link all the adyen_creditmemos related to the adyen_order_payment with the given magento entity of the creditmemo
     * @throws AlreadyExistsException
     */
    public function linkAndUpdateAdyenCreditMemos(Payment $adyenOrderPayment, MagentoCreditMemoModel $magentoCreditMemo)
    {
        $adyenCreditMemoLoader = $this->adyenCreditmemoFactory->create();

        $adyenCreditMemos = $this->adyenCreditMemoResourceModel->getAdyenCreditMemosByAdyenPaymentid($adyenOrderPayment[OrderPaymentInterface::ENTITY_ID]);
        if (!is_null($adyenCreditMemos)) {
            foreach ($adyenCreditMemos as $adyenCreditMemo) {
                /** @var AdyenCreditMemoModel $currAdyenCreditMemo */
                $currAdyenCreditMemo = $adyenCreditMemoLoader->load($adyenCreditMemo[CreditMemoInterface::ENTITY_ID], CreditMemoInterface::ENTITY_ID);
                $currAdyenCreditMemo->setCreditmemoId($magentoCreditMemo->getEntityId());
                $this->adyenCreditMemoResourceModel->save($currAdyenCreditMemo);
            }
        }
    }
}
