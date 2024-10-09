<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request\Header;

use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilder;
use Adyen\Payment\Gateway\Request\Header\HeaderDataBuilderInterface;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Data;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
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

        $productMetadata = $this->createConfiguredMock(ProductMetadata::class, [
            'getName' => 'magento',
            'getVersion' => '2.x.x',
            'getEdition' => 'Community'
        ]);
        $this->adyenHelperMock->method('getMagentoDetails')->willReturn('external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3');

        $this->adyenHelperMock = $this->getMockBuilder(Data::class)
//            ->disableOriginalConstructor()
                ->setConstructorArgs(
                $productMetadata,
            )
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

        $this->headerDataBuilder->expects($this->once())
            ->method('buildRequestHeaders')
            ->with($paymentMock)
            ->willReturn($headers);

        $result = $this->headerDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals($headers, $result['headers']);
    }


    public function testBuildRequestHeaders()
    {
        $expectedHeaders = [
            'external-platform-name' => 'magento',
            'external-platform-version' => '2.x.x',
            'external-platform-edition' => 'Community',
            'merchant-application-name' => 'adyen-magento2',
            'merchant-application-version' => '1.2.3'
        ];

        $headers = $this->headerDataBuilder->buildRequestHeaders();

        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testBuildRequestHeadersWithNonNullFrontendType()
    {
        // Mock dependencies as needed
        $payment = $this->createMock(Payment::class);

        // Set up expectations for the getAdditionalInformation method
        $payment->method('getAdditionalInformation')
            ->with(HeaderDataBuilderInterface::FRONTEND_TYPE)
            ->willReturn('some_frontend_type');

        // Call the method under test
        $result = $this->headerDataBuilder->buildRequestHeaders($payment);

        // Assert that the 'frontend-type' header is correctly set
        $this->assertArrayHasKey(HeaderDataBuilderInterface::FRONTEND_TYPE, $result);
        $this->assertEquals('some_frontend_type', $result[HeaderDataBuilderInterface::FRONTEND_TYPE]);

        // Assert other headers as needed
    }


    public function testBuildRequestHeadersWithoutPayment()
    {
        // Call the method under test without providing a payment object
        $result = $this->headerDataBuilder->buildRequestHeaders();

        // Assert that the 'frontend-type' header is not set
        $this->assertArrayNotHasKey(HeaderDataBuilderInterface::FRONTEND_TYPE, $result);
    }
}
