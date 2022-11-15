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

namespace Adyen\Payment\Tests\Unit\Helper;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Invoice as InvoiceModel;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Invoice\Collection;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Adyen\Payment\Model\Invoice as AdyenInvoice;

class InvoiceTest extends AbstractAdyenTestCase
{
    /**
     * @var Invoice
     */
    private $invoiceHelper;

    /**
     * @var Notification
     */
    private $notificationMock;

    /**
     * @var InvoiceModel
     */
    private $invoiceMock;

    /**
     * @var Order
     */
    private $orderMock;

    /**
     * @var MagentoOrder\Payment
     */
    private $orderPaymentMock;

    protected function setUp(): void
    {
        $this->notificationMock = $this->createWebhook();

        $this->invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getOrder' => $this->createMock(Order::class),
            'register' => $this->createMock(InvoiceModel::class)
        ]);

        $this->orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getMethod' => 'adyen_cc',
            'getOrder' => $this->createMock(Order::class)
        ]);

        $this->orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'getStatus' => 'testStatus',
            'getPayment' => $this->orderPaymentMock,
            'getBaseCurrencyCode' => 'EUR',
            'prepareInvoice' => $this->invoiceMock,
            'canInvoice' => true,
        ]);

        $scopeConfig = $this->createConfiguredMock(ScopeConfigInterface::class, [
            'isSetFlag' => false
        ]);

        $adyenInvoiceMock = $this->createConfiguredMock(AdyenInvoice::class, [
            'getAmount' => 1000,
            'getEntityId' => 'ENTITY_ID'
        ]);
        $adyenInvoiceFactory = $this->createGeneratedMock(InvoiceFactory::class, ['create']);
        $adyenInvoiceFactory->method('create')->willReturn($adyenInvoiceMock);

        $this->invoiceHelper = new Invoice(
            $this->createConfiguredMock(Context::class, ['getScopeConfig' => $scopeConfig]),
            $this->createMock(AdyenLogger::class),
            $this->createConfiguredMock(Data::class, [
                'originalAmount' => 1000,
                'formatAmount' => 1000
            ]),
            $this->createMock(InvoiceRepositoryInterface::class),
            $adyenInvoiceFactory,
            $this->createConfiguredMock(AdyenInvoiceResourceModel::class, [
                'getAdyenInvoiceByCaptureWebhook' => null,
                'getAdyenInvoicesByAdyenPaymentId' => [
                    ['invoice_id' => null, 'entity_id' => 'ENTITY_ID']
                ],
                'save' => $this->createMock(AbstractDb::class)
            ]),
            $this->createConfiguredMock(OrderPaymentResourceModel::class, [
                'getOrderPaymentDetails' => [
                    'entity_id' => 'MOCK_ENTITY_ID'
                ]
            ]),
            $this->createGeneratedMock(PaymentFactory::class),
            $this->createMock(Collection::class),
            $this->createGeneratedMock(MagentoInvoiceFactory::class),
            $this->createMock(\Magento\Sales\Model\ResourceModel\Order::class),
            $this->createMock(Config::class),
            $this->createMock(InvoiceSender::class),
            $this->createMock(Transaction::class)
        );
    }

    /**
     * @throws LocalizedException
     */
    public function testCreateInvoice()
    {
        $invoice = $this->invoiceHelper->createInvoice(
            $this->orderMock,
            $this->notificationMock,
            true
        );

        $this->assertInstanceOf(InvoiceModel::class, $invoice);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function testCreateAdyenInvoice()
    {
        $adyenInvoice = $this->invoiceHelper->createAdyenInvoice(
            $this->orderPaymentMock,
            'PSPREFERENCE',
            'ORIGINALREFERENCE',
            1000,
            1
        );

        $this->assertInstanceOf(AdyenInvoice::class, $adyenInvoice);
    }

//    public function testHandleCaptureWebhook()
//    {
//        // This test handles regular capture flow from plugin.
//        $adyenInvoice = $this->invoiceHelper->handleCaptureWebhook(
//            $this->orderMock,
//            $this->notificationMock
//        );
//
//        $this->assertInstanceOf(AdyenInvoice::class, $adyenInvoice);
//    }

    /**
     * @throws AlreadyExistsException
     */
    public function testLinkAndUpdateAdyenInvoices()
    {
        $adyenOrderPaymentMock = $this->createMock(Payment::class);

        $linkedAmount = $this->invoiceHelper->linkAndUpdateAdyenInvoices(
            $adyenOrderPaymentMock,
            $this->invoiceMock
        );

        $this->assertEquals(1000, $linkedAmount);
    }

















}
