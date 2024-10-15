<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilder;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use \Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\MockObject\MockObject;

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

    /**
     * @var InfoInterface|MockObject
     */
    private $paymentMock;

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

        $this->paymentMock = $this->getMockBuilder(InfoInterface::class)
            ->getMock();

        $this->setUpAdyenHelperMockExpectations();
    }

    /**
     * Mock common Adyen helper expectations.
     */
    private function setUpAdyenHelperMockExpectations(): void
    {
        $this->adyenHelperMock->expects($this->once())
            ->method('getMagentoDetails')
            ->willReturn(['name' => 'Magento', 'version' => '2.x.x', 'edition' => 'Community']);

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleName')
            ->willReturn('adyen-magento2');

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleVersion')
            ->willReturn('1.2.3');
    }

    public function testBuild()
    {
        $paymentDataObjectMock = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $this->paymentMock
        ]);

        $buildSubject = ['payment' => $paymentDataObjectMock];

        $this->paymentMock->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY)
            ->willReturn('luma');

        $expectedHeaders = [
            'external-platform-name' => 'Magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3',
            'external-platform-frontendtype' => 'luma'
        ];

        // Call the build method
        $result = $this->headerDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals($expectedHeaders, $result['headers']);
    }

    public function testBuildRequestHeaders()
    {
        $this->paymentMock->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY)
            ->willReturn('luma');

        $result = $this->headerDataBuilder->buildRequestHeaders($this->paymentMock);

        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE, $result);
        $this->assertEquals('luma', $result[HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE]);
    }

    public function testBuildRequestHeadersWithoutFrontendType()
    {
        $this->paymentMock->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::ADDITIONAL_DATA_FRONTEND_TYPE_KEY)
            ->willReturn(null);

        $result = $this->headerDataBuilder->buildRequestHeaders($this->paymentMock);

        // Since no payment is passed, there should be no frontend type
        $this->assertArrayNotHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE, $result);
    }

    public function testBuildRequestHeadersWithoutPayment()
    {
        // Call the method with null payment
        $result = $this->headerDataBuilder->buildRequestHeaders();

        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_NAME, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_VERSION, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_EDITION, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::MERCHANT_APPLICATION_NAME, $result);
        $this->assertArrayHasKey(HeaderDataBuilderInterface::MERCHANT_APPLICATION_VERSION, $result);

        // Since no payment is passed, there should be no frontend type
        $this->assertArrayNotHasKey(HeaderDataBuilderInterface::EXTERNAL_PLATFORM_FRONTEND_TYPE, $result);
    }
}
