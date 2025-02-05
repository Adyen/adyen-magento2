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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Api\Data\InvoiceInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
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
     * @throws LocalizedException
     */
    public function testCreateInvoice()
    {
        $invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getOrder' => $this->createMock(Order::class),
            'register' => $this->createMock(InvoiceModel::class)
        ]);

        $orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'prepareInvoice' => $invoiceMock,
            'canInvoice' => true,
        ]);

        $scopeConfigMock = $this->createConfiguredMock(ScopeConfigInterface::class, [
            'isSetFlag' => false
        ]);
        $contextMock = $this->createConfiguredMock(Context::class, [
            'getScopeConfig' => $scopeConfigMock
        ]);

        $invoiceHelper = $this->createInvoiceHelper($contextMock);

        $notificationMock = $this->createWebhook();

        $invoice = $invoiceHelper->createInvoice(
            $orderMock,
            $notificationMock,
            true
        );

        $this->assertInstanceOf(InvoiceModel::class, $invoice);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function testCreateAdyenInvoice()
    {
        $adyenInvoiceMockForFactory = $this->createMock(AdyenInvoice::class);
        $adyenInvoiceFactoryMock = $this->createGeneratedMock(InvoiceFactory::class, ['create']);
        $adyenInvoiceFactoryMock->method('create')->willReturn($adyenInvoiceMockForFactory);

        $adyenInvoiceResourceModelMock =  $this->createConfiguredMock(AdyenInvoiceResourceModel::class, [
            'save' => $this->createMock(AbstractDb::class)
        ]);

        $orderPaymentResourceModelMock = $this->createConfiguredMock(OrderPaymentResourceModel::class, [
            'getOrderPaymentDetails' => [
                'entity_id' => 1000
            ]
        ]);

        $invoiceHelper = $this->createInvoiceHelper(
            null,
            null,
            null,
            null,
            $adyenInvoiceFactoryMock,
            $adyenInvoiceResourceModelMock,
            $orderPaymentResourceModelMock
        );

        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getOrder' => $this->createMock(Order::class)
        ]);

        $adyenInvoice = $invoiceHelper->createAdyenInvoice(
            $orderPaymentMock,
            'PSPREFERENCE',
            'ORIGINALREFERENCE',
            1000,
            1
        );

        $this->assertInstanceOf(AdyenInvoice::class, $adyenInvoice);
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     */
    public function testHandleCaptureWebhook()
    {
        $scopeConfigMock = $this->createConfiguredMock(ScopeConfigInterface::class, [
            'isSetFlag' => false
        ]);
        $contextMock = $this->createConfiguredMock(Context::class, [
            'getScopeConfig' => $scopeConfigMock
        ]);

        $adyenInvoiceMock = $this->createMock(AdyenInvoice::class);
        $adyenInvoiceFactory = $this->createGeneratedMock(InvoiceFactory::class, ['create']);
        $adyenInvoiceFactory->method('create')->willReturn($adyenInvoiceMock);

        $invoiceLoadedMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getOrder' => $this->createMock(MagentoOrder::class)
        ]);

        $invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getId' => 1,
            'load' => $invoiceLoadedMock
        ]);

        $orderPaymentResourceModelMock = $this->createConfiguredMock(OrderPaymentResourceModel::class, [
            'getOrderPaymentDetails' => [
                'entity_id' => 1000
            ]
        ]);

        $magentoInvoiceFactoryMock = $this->createGeneratedMock(MagentoInvoiceFactory::class, ['create']);
        $magentoInvoiceFactoryMock->method('create')->willReturn($invoiceMock);

        $magentoOrderResourceModelMock = $this->createGeneratedMock(
            \Magento\Sales\Model\ResourceModel\Order::class,
            ['save']
        );
        $magentoOrderResourceModelMock->method('save')->willReturn($invoiceMock);

        $transactionMock = $this->createGeneratedMock(Transaction::class, ['addObject']);
        $transactionMock->method('addObject')->willReturn($invoiceMock);

        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getAmount')->willReturn(10);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn('EUR');

        $invoiceAmountCurrency = $this->createMock(AdyenAmountCurrency::class);

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getOrderAmountCurrency')->willReturn($adyenAmountCurrencyMock);
        $chargedCurrencyMock->method('getInvoiceAmountCurrency')->willReturn($invoiceAmountCurrency);

        $invoiceHelper = $this->createInvoiceHelper(
            $contextMock,
            null,
            null,
            null,
            $adyenInvoiceFactory,
            null,
            $orderPaymentResourceModelMock,
            null,
            null,
            $magentoInvoiceFactoryMock,
            $magentoOrderResourceModelMock,
            null,
            null,
            $transactionMock,
            $chargedCurrencyMock
        );

        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getMethod' => 'adyen_cc',
            'getOrder' => $this->createMock(Order::class)
        ]);

        $orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'canInvoice' => true,
            'getPayment' => $orderPaymentMock,
            'getBaseGrandTotal' => 10,
            'getOrderCurrencyCode' => 'EUR',
            'prepareInvoice' => $invoiceMock
        ]);

        $notificationMock = $this->createWebhook(
            'ADY00000000XY',
            'ADY00000000TX'
        );

        $adyenInvoice = $invoiceHelper->handleCaptureWebhook(
            $orderMock,
            $notificationMock
        );

        $this->assertInstanceOf(AdyenInvoice::class, $adyenInvoice);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function testLinkAndUpdateAdyenInvoices()
    {
        $adyenInvoiceResourceModelMock =  $this->createConfiguredMock(AdyenInvoiceResourceModel::class, [
            'getAdyenInvoicesByAdyenPaymentId' => [
                ['invoice_id' => null, 'entity_id' => 99]
            ],
            'save' => $this->createMock(AbstractDb::class)
        ]);

        $adyenInvoiceLoadedMock = $this->createConfiguredMock(AdyenInvoice::class, [
            'getAmount' => 1000.0,
            'getEntityId' => 99,
        ]);
        $adyenInvoiceMockForFactory = $this->createConfiguredMock(AdyenInvoice::class, [
            'load' => $adyenInvoiceLoadedMock
        ]);
        $adyenInvoiceFactory = $this->createGeneratedMock(InvoiceFactory::class, ['create']);
        $adyenInvoiceFactory->method('create')->willReturn($adyenInvoiceMockForFactory);

        $invoiceHelper = $this->createInvoiceHelper(
            null,
            null,
            null,
            null,
            $adyenInvoiceFactory,
            $adyenInvoiceResourceModelMock
        );

        $adyenOrderPaymentMock = $this->createMock(Payment::class);
        $invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getOrder' => $this->createMock(Order::class),
            'getGrandTotal' => 1000.0,
            'getOrderCurrencyCode' => 'EUR',
            'register' => $this->createMock(InvoiceModel::class),
            'getEntityId' => 99
        ]);

        $linkedAmount = $invoiceHelper->linkAndUpdateAdyenInvoices(
            $adyenOrderPaymentMock,
            $invoiceMock
        );

        $this->assertEquals(1000.0, $linkedAmount);
    }

    /**
     * @return void
     */
    public function testIsFullInvoiceAmountManuallyCaptured()
    {
        $adyenInvoiceCollectionMock = $this->createConfiguredMock(Collection::class, [
            'getAdyenInvoicesLinkedToMagentoInvoice' => [
                [
                    InvoiceInterface::STATUS => InvoiceInterface::STATUS_SUCCESSFUL,
                    InvoiceInterface::AMOUNT => 1000
                ]
            ],
        ]);

        $invoiceAmountCurrency = $this->createMock(AdyenAmountCurrency::class);
        $invoiceAmountCurrency->expects($this->once())
            ->method('getAmount')
            ->willReturn(1000);
        $invoiceAmountCurrency->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('EUR');

        $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $chargedCurrencyMock->method('getInvoiceAmountCurrency')->willReturn($invoiceAmountCurrency);

        $invoiceHelper = $this->createInvoiceHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $adyenInvoiceCollectionMock,
            null,
            null,
            null,
            null,
            null,
            $chargedCurrencyMock
        );

        $invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getGrandTotal' => 1000,
            'getOrderCurrencyCode' => 'EUR',
        ]);

        $isFullInvoiceAmountManuallyCaptured = $invoiceHelper->isFullInvoiceAmountManuallyCaptured(
            $invoiceMock
        );

        $this->assertTrue($isFullInvoiceAmountManuallyCaptured);
    }

    /**
     * @throws AlreadyExistsException
     */
    public function testCreateInvoiceFromWebhook()
    {
        $adyenInvoiceMockForFactory = $this->createMock(AdyenInvoice::class);
        $adyenInvoiceFactoryMock = $this->createGeneratedMock(InvoiceFactory::class, ['create']);
        $adyenInvoiceFactoryMock->method('create')->willReturn($adyenInvoiceMockForFactory);

        $adyenInvoiceResourceModelMock =  $this->createConfiguredMock(AdyenInvoiceResourceModel::class, [
            'save' => $this->createMock(AbstractDb::class)
        ]);

        $orderPaymentResourceModelMock = $this->createConfiguredMock(OrderPaymentResourceModel::class, [
            'getOrderPaymentDetails' => [
                'entity_id' => 1000
            ]
        ]);

        $scopeConfigMock = $this->createConfiguredMock(ScopeConfigInterface::class, [
            'isSetFlag' => false
        ]);
        $contextMock = $this->createConfiguredMock(Context::class, [
            'getScopeConfig' => $scopeConfigMock
        ]);

        $invoiceMock = $this->createConfiguredMock(InvoiceModel::class, [
            'getId' => 1,
            'getOrder' => $this->createMock(Order::class)
        ]);
        $transactionMock = $this->createGeneratedMock(Transaction::class, ['addObject']);
        $transactionMock->method('addObject')->willReturn($invoiceMock);

        $invoiceHelper = $this->createInvoiceHelper(
            $contextMock,
            null,
            null,
            null,
            $adyenInvoiceFactoryMock,
            $adyenInvoiceResourceModelMock,
            $orderPaymentResourceModelMock,
            null,
            null,
            null,
            null,
            null,
            null,
            $transactionMock
        );

        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getMethod' => 'adyen_cc',
            'getOrder' => $this->createMock(Order::class)
        ]);

        $orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'prepareInvoice' => $invoiceMock,
            'getPayment' => $orderPaymentMock,
            'getIncrementId' => '000000001'
        ]);

        $notificationMock = $this->createWebhook(
            'ADY00000000XY',
            'ADY00000000TX'
        );

        $adyenInvoice = $invoiceHelper->createInvoiceFromWebhook(
            $orderMock,
            $notificationMock
        );

        $this->assertInstanceOf(AdyenInvoice::class, $adyenInvoice);
    }

    public function testSendInvoiceMailCatchesException()
    {
        $invoiceSenderMock = $this->createMock(InvoiceSender::class);
        $invoiceModelMock = $this->createMock(InvoiceModel::class);

        $invoiceSenderMock
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('Test Exception Message'));

        $invoiceHelper = $this->createInvoiceHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $invoiceSenderMock
        );

        $invoiceHelper->sendInvoiceMail($invoiceModelMock);
    }

    /**
     * @param $contextMock
     * @param $adyenLoggerMock
     * @param $adyenDataHelperMock
     * @param $invoiceRepositoryInterfaceMock
     * @param $adyenInvoiceFactory
     * @param $adyenInvoiceResourceModelMock
     * @param $orderPaymentResourceModelMock
     * @param $paymentFactoryMock
     * @param $adyenInvoiceCollectionMock
     * @param $magentoInvoiceFactoryMock
     * @param $magentoOrderResourceModelMock
     * @param $adyenConfigHelperMock
     * @param $invoiceSenderMock
     * @param $transactionMock
     * @return Invoice
     */
    protected function createInvoiceHelper(
        $contextMock = null,
        $adyenLoggerMock = null,
        $adyenDataHelperMock = null,
        $invoiceRepositoryInterfaceMock = null,
        $adyenInvoiceFactory = null,
        $adyenInvoiceResourceModelMock = null,
        $orderPaymentResourceModelMock = null,
        $paymentFactoryMock = null,
        $adyenInvoiceCollectionMock = null,
        $magentoInvoiceFactoryMock = null,
        $magentoOrderResourceModelMock = null,
        $adyenConfigHelperMock = null,
        $invoiceSenderMock = null,
        $transactionMock = null,
        $chargedCurrencyMock = null
    ): Invoice {

        if (is_null($contextMock)) {
            $contextMock = $this->createMock(Context::class);
        }

        if (is_null($adyenLoggerMock)) {
            $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        }

        if (is_null($adyenDataHelperMock)) {
            $adyenDataHelperMock = $this->createPartialMock(Data::class, []);
        }

        if (is_null($invoiceRepositoryInterfaceMock)) {
            $invoiceRepositoryInterfaceMock = $this->createMock(InvoiceRepositoryInterface::class);
        }

        if (is_null($adyenInvoiceFactory)) {
            $adyenInvoiceFactory = $this->createGeneratedMock(InvoiceFactory::class);
        }

        if (is_null($adyenInvoiceResourceModelMock)) {
            $adyenInvoiceResourceModelMock = $this->createMock(AdyenInvoiceResourceModel::class);
        }

        if (is_null($orderPaymentResourceModelMock)) {
            $orderPaymentResourceModelMock = $this->createMock(OrderPaymentResourceModel::class);
        }

        if (is_null($paymentFactoryMock)) {
            $paymentFactoryMock = $this->createGeneratedMock(PaymentFactory::class);
        }

        if (is_null($adyenInvoiceCollectionMock)) {
            $adyenInvoiceCollectionMock = $this->createMock(Collection::class);
        }

        if (is_null($magentoInvoiceFactoryMock)) {
            $magentoInvoiceFactoryMock = $this->createGeneratedMock(MagentoInvoiceFactory::class);
        }

        if (is_null($magentoOrderResourceModelMock)) {
            $magentoOrderResourceModelMock = $this->createMock(\Magento\Sales\Model\ResourceModel\Order::class);
        }

        if (is_null($adyenConfigHelperMock)) {
            $adyenConfigHelperMock = $this->createMock(Config::class);
        }

        if (is_null($invoiceSenderMock)) {
            $invoiceSenderMock = $this->createMock(InvoiceSender::class);
        }

        if (is_null($transactionMock)) {
            $transactionMock = $this->createGeneratedMock(Transaction::class);
        }

        if (is_null($chargedCurrencyMock)) {
            $chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        }

        return new Invoice(
            $contextMock,
            $adyenLoggerMock,
            $adyenDataHelperMock,
            $invoiceRepositoryInterfaceMock,
            $adyenInvoiceFactory,
            $adyenInvoiceResourceModelMock,
            $orderPaymentResourceModelMock,
            $paymentFactoryMock,
            $adyenInvoiceCollectionMock,
            $magentoInvoiceFactoryMock,
            $magentoOrderResourceModelMock,
            $adyenConfigHelperMock,
            $invoiceSenderMock,
            $transactionMock,
            $chargedCurrencyMock
        );
    }
}
