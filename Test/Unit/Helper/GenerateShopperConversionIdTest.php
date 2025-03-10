<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\GenerateShopperConversionId;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class GenerateShopperConversionIdTest extends AbstractAdyenTestCase
{
    private ?GenerateShopperConversionId $helper;
    private MockObject|CheckoutSession $checkoutSessionMock;
    private MockObject|Quote $quoteMock;
    private MockObject|Payment $paymentMock;
    private MockObject|Context $contextMock;

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->contextMock = $this->createMock(Context::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->paymentMock = $this->createMock(Payment::class);

        $this->helper = new GenerateShopperConversionId(
            $this->contextMock,
            $this->checkoutSessionMock
        );
    }

    /**
     * Tear down the test environment
     */
    protected function tearDown(): void
    {
        $this->helper = null;
        parent::tearDown();
    }

    /**
     * Test getShopperConversionId method
     */
    public function testGetShopperConversionId(): void
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->quoteMock->expects($this->once())
            ->method('setPayment')
            ->with($this->paymentMock);

        $this->quoteMock->expects($this->once())
            ->method('save');

        $result = $this->helper->getShopperConversionId();

        $this->assertNotNull($result);
    }
}
