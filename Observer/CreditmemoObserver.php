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

namespace Adyen\Payment\Observer;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\Creditmemo as CreditMemoHelper;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\StatusResolver;
use Magento\Sales\Model\Order\Creditmemo;
use Adyen\Payment\Helper\PaymentMethods;

class CreditmemoObserver implements ObserverInterface
{
    /** @var Payment $adyenPaymentResourceModel */
    private $adyenPaymentResourceModel;

    /** @var PaymentFactory */
    private $adyenOrderPaymentFactory;

    /** @var InvoiceHelper $invoiceHelper*/
    private $invoiceHelper;

    /** @var StatusResolver $statusResolver */
    private $statusResolver;

    /** @var AdyenOrderPayment $adyenOrderPaymentHelper */
    private $adyenOrderPaymentHelper;

    /** @var CreditmemoHelper $creditmemoHelper */
    private $creditmemoHelper;

    /** @var Config $configHelper */
    private $configHelper;

    /** @var PaymentMethods $paymentMethodsHelper */
    private $paymentMethodsHelper;

    /** @var OrderHelper */
    private $orderHelper;

    /**
     * @var AdyenLogger
     */
    private $logger;

    public function __construct(
        Payment $adyenPaymentResourceModel,
        PaymentFactory $adyenOrderPaymentFactory,
        InvoiceHelper $invoiceHelper,
        StatusResolver $statusResolver,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        CreditmemoHelper $creditmemoHelper,
        Config $configHelper,
        AdyenLogger $adyenLogger,
        PaymentMethods $paymentMethodsHelper,
        OrderHelper $orderHelper
    ) {
        $this->adyenPaymentResourceModel = $adyenPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
        $this->invoiceHelper = $invoiceHelper;
        $this->statusResolver = $statusResolver;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->creditmemoHelper = $creditmemoHelper;
        $this->configHelper = $configHelper;
        $this->logger = $adyenLogger;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Link all adyen_creditmemos to the appropriate magento credit memo and set the order to PROCESSING to allow
     * further credit memos to be generated
     *
     * @param Observer $observer
     * @throws AlreadyExistsException
     */
    public function execute(Observer $observer)
    {
        $adyenOrderPaymentFactory = $this->adyenOrderPaymentFactory->create();

        /** @var Creditmemo $creditmemo */
        $creditmemo = $observer->getData('creditmemo');
        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();

        $adyenOrderPayments = $this->adyenPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId()
        );
        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            /** @var \Adyen\Payment\Model\Order\Payment $adyenOrderPaymentObject */
            $adyenOrderPaymentObject = $adyenOrderPaymentFactory->load(
                $adyenOrderPayment[OrderPaymentInterface::ENTITY_ID],
                OrderPaymentInterface::ENTITY_ID
            );
            $this->creditmemoHelper->linkAndUpdateAdyenCreditmemos($adyenOrderPaymentObject, $creditmemo);
        }
    }
}
