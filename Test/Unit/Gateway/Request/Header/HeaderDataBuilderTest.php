<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\HeaderDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Data;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HeaderDataBuilderTest extends AbstractAdyenTestCase
{
    /**
     * @var HeaderDataBuilder
     */
    private $headerDataBuilder;

    /**
     * @var Data|MockObject
     */
    private $adyenHelperMock;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->adyenHelperMock = $this->getMockBuilder(Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->headerDataBuilder = $objectManager->getObject(
            HeaderDataBuilder::class,
            [
                'adyenHelper' => $this->adyenHelperMock
            ]
        );
    }

    public function testBuild()
    {
        $paymentMock = $this->getMockBuilder(\Magento\Payment\Model\InfoInterface::class)
            ->getMock();
        $paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $paymentMock
        ]);

        $buildSubject = ['payment' => $paymentDataObjectMock];

        $headers = ['header1' => 'value1', 'header2' => 'value2'];

        $this->adyenHelperMock->expects($this->once())
            ->method('buildRequestHeaders')
            ->with($paymentMock)
            ->willReturn($headers);

        $result = $this->headerDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals($headers, $result['headers']);
    }
}
