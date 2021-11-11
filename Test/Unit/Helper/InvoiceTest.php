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
     * @var Context|\PHPUnit\Framework\MockObject\MockObject
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
     * @var Data|MockObject
     */
    private $mockDataHelper;

    protected function setUp(): void
    {
        $contextMock = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger = $this->getMockBuilder(AdyenLogger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockDataHelper = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockDataHelper->method('parseTransactionId')->willReturnCallback(function ($arg) {
            return ['pspReference' => $arg];
        });
        $mockInvoiceResourceModel = $this->getMockBuilder(InvoiceResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAdyenInvoiceFactory = $this->getMockBuilder(InvoiceFactory::class)
            ->disableOriginalConstructor()
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
            $this->mockDataHelper,
            $mockInvoiceResourceModel,
            $mockAdyenInvoiceFactory,
            $mockAdyenInvoiceResourceModel,
            $mockOrderPaymentResourceModel
        );

        $this->notification = $this->getMockBuilder(Notification::class)
            ->disableOriginalConstructor()
            ->setMethods(['getPspreference', 'getOriginalReference'])
            ->getMock();
        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()->getMock();
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

    public function finalizeInvoiceDataProvider()
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

    private function getMockInvoiceCollection($originalReference, $updateAll = false): array
    {
        $invoice = $this->getMockBuilder(Order\Invoice::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTransactionId', 'getState', 'wasPayCalled', 'pay', 'getEntityId'])
            ->getMock();

        $collection = [];
        $invoiceModelStates = [Order\Invoice::STATE_OPEN, Order\Invoice::STATE_PAID];
        $firstInvoiceUpdated = false;
        foreach ($invoiceModelStates as $state) {
            foreach ([true, false] as $wasPayCalled) {
                $clone = clone $invoice;
                $this->mockMethods($clone, [
                    'getState' => $state,
                    'wasPayCalled' => $wasPayCalled
                ]);
                if ($updateAll) {
                    $this->mockMethods($clone, [
                        'getTransactionId' => $originalReference
                    ]);
                } elseif (!$wasPayCalled && !$firstInvoiceUpdated) {
                    $this->mockMethods($clone, [
                        'getTransactionId' => $originalReference
                    ]);
                    $firstInvoiceUpdated = true;
                }
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