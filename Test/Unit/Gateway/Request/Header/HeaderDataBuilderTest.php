<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\PlatformInfo;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;

class HeaderDataBuilderTest extends AbstractAdyenTestCase
{
    /**
     * @var HeaderDataBuilder
     */
    private $headerDataBuilder;

    /**
     * @var PlatformInfo
     */
    private $platformInfo;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->platformInfo = $this->getMockBuilder(PlatformInfo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->headerDataBuilder = $objectManager->getObject(
            HeaderDataBuilder::class,
            [
                'platformInfo' => $this->platformInfo
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

        $this->platformInfo->expects($this->once())
            ->method('buildRequestHeaders')
            ->with($paymentMock)
            ->willReturn($headers);

        $result = $this->headerDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals($headers, $result['headers']);
    }
}
