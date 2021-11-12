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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Invoice as AdyenInvoiceModel;
use Adyen\Payment\Model\InvoiceFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Invoice\Invoice as AdyenInvoiceResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    protected $contextMock;
    /**
     * @var Invoice
     */
    private $invoiceHelper;
    /**
     * @var Notification|MockObject
     */
    private $notification;
    /**
     * @var Order|MockObject
     */
    private $order;
    /**
     * @var Order\Invoice|MockObject
     */
    private $invoice;
    /**
     * @var InvoiceFactory|MockObject
     */
    private $mockAdyenInvoiceFactory;
    /**
     * @var AdyenInvoiceModel|MockObject
     */
    private $adyenInvoice;

    protected function setUp(): void
    {
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockDataHelper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockDataHelper->method('parseTransactionId')->willReturnCallback(function ($arg) {
            return ['pspReference' => $arg];
        });
        $mockInvoiceResourceModel = $this->getMockBuilder(InvoiceResourceModel::class)
            ->disableOriginalConstructor()
            ->setMethods(['save'])
            ->getMock();
        $this->mockAdyenInvoiceFactory = $this->getMockBuilder(InvoiceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();
        $mockAdyenInvoiceResourceModel = $this->getMockBuilder(AdyenInvoiceResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockOrderPaymentResourceModel = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->invoiceHelper = new Invoice(
            $contextMock,
            $mockLogger,
            $mockDataHelper,
            $mockInvoiceResourceModel,
            $this->mockAdyenInvoiceFactory,
            $mockAdyenInvoiceResourceModel,
            $mockOrderPaymentResourceModel
        );

        $this->notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPspreference', 'getOriginalReference', 'getAdditionalData'])
            ->getMock();
        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->invoice = $this->getMockBuilder(Order\Invoice::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTransactionId', 'getState', 'wasPayCalled', 'pay', 'getEntityId'])
            ->getMock();
        $this->adyenInvoice = $this->getMockBuilder(AdyenInvoiceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @dataProvider finalizeInvoiceDataProvider
     */
    public function testFinalizeInvoices($originalReference, $invoiceCollection, $numberOfFinalizedInvoices)
    {
        $this->mockMethods($this->order, [
            'getInvoiceCollection' => $invoiceCollection
        ]);
        $this->mockMethods($this->notification, [
            'getOriginalReference' => $originalReference,
        ]);

        $result = $this->invoiceHelper->finalizeInvoices($this->order, $this->notification);

        $this->assertEquals($numberOfFinalizedInvoices, count($result));
    }

    public function finalizeInvoiceDataProvider(): array
    {
        return [
            [
                'originalReference' => '1234ZXCV5678FGHJ',
                'invoiceCollection' => $this->getMockInvoiceCollection('1234ZXCV5678FGHJ', true),
                'numberOfFinalizedInvoices' => 3
            ],
            [
                'originalReference' => '1234ZXCV5678FGHJ',
                'invoiceCollection' => $this->getMockInvoiceCollection('1234ZXCV5678FGHJ'),
                'numberOfFinalizedInvoices' => 1
            ]
        ];
    }

    public function testCreateAdyenInvoice()
    {
        $invoiceId = 1;
        $additionalData = ['additional_data' => 'xxxxyyyyzzzz'];
        $pspReference = 'ABCD0987EFGH6543';
        $originalReference = '1234ZXCV5678FGHJ';
        $notification = clone $this->notification;
        $this->mockMethods($notification, [
            'getAdditionalData' => $additionalData,
            'getPspreference' => $pspReference,
            'getOriginalReference' => $originalReference,
        ]);
        $invoice = clone $this->invoice;
        $this->mockMethods($invoice, [
            'getEntityId' => $invoiceId
        ]);
        $adyenInvoice = clone $this->adyenInvoice;
        $adyenInvoice->expects($this->once())->method('setInvoiceId')->with($invoiceId);
        $adyenInvoice->expects($this->once())->method('setPspreference')->with($pspReference);
        $adyenInvoice->expects($this->once())->method('setOriginalReference')->with($originalReference);
        $adyenInvoice->expects($this->once())->method('setAcquirerReference')->with($additionalData['additional_data']);
        $this->mockMethods($this->mockAdyenInvoiceFactory, ['create' => $adyenInvoice]);
        $result = $this->invoiceHelper->createAdyenInvoice($this->order, $notification, $invoice);

        $this->assertInstanceOf(AdyenInvoiceModel::class, $result);
    }

    public function testGetLinkedInvoiceToCaptureNotification()
    {
        $reference = 'ACDC4321WXYZ0123';
        $linkedInvoiceIndex = 1;
        $invoiceCollection = $this->getMockInvoiceCollection($reference, false, $linkedInvoiceIndex);
        $this->mockMethods($this->order, [
            'getInvoiceCollection' => $invoiceCollection
        ]);
        $this->mockMethods($this->notification, [
            'getOriginalReference' => $reference,
        ]);

        $result = $this->invoiceHelper->getLinkedInvoiceToCaptureNotification($this->order, $this->notification);

        $this->assertEquals($result, $invoiceCollection[$linkedInvoiceIndex]);
    }

    private function getMockInvoiceCollection($originalReference, $linkAll = false, $linkedIndex = null): array
    {
        $invoice = $this->getMockBuilder(Order\Invoice::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection = [];
        $invoiceModelStates = [Order\Invoice::STATE_OPEN, Order\Invoice::STATE_PAID];
        $firstInvoiceUpdated = false;
        $index = 0;
        foreach ($invoiceModelStates as $state) {
            foreach ([true, false] as $wasPayCalled) {
                $clone = clone $invoice;
                $this->mockMethods($clone, [
                    'getState' => $state,
                    'wasPayCalled' => $wasPayCalled
                ]);
                if (is_int($linkedIndex)) {
                    if ($index === $linkedIndex) {
                        $this->mockMethods($clone, [
                            'getTransactionId' => $originalReference
                        ]);
                    }
                } elseif ($linkAll) {
                    $this->mockMethods($clone, [
                        'getTransactionId' => $originalReference
                    ]);
                } elseif (!$wasPayCalled && !$firstInvoiceUpdated) {
                    $this->mockMethods($clone, [
                        'getTransactionId' => $originalReference
                    ]);
                    $firstInvoiceUpdated = true;
                }
                $index++;
                $collection[] = $clone;
            }
        }

        return $collection;
    }

    private function mockMethods(MockObject $object, array $methods)
    {
        foreach($methods as $key => $value) {
            $object->method($key)->willReturn($value);
        }
    }
}