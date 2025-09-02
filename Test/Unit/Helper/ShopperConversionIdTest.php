<?php

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\ShopperConversionId;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class ShopperConversionIdTest extends AbstractAdyenTestCase
{
    private ?ShopperConversionId $helper = null;

    /** @var MockObject&Context */
    private $contextMock;

    /** @var MockObject&CartRepositoryInterface */
    private $cartRepositoryMock;

    /** @var MockObject&AdyenLogger */
    private $adyenLoggerMock;

    /** @var MockObject&Quote */
    private $quoteMock;

    /** @var MockObject&Payment */
    private $paymentMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextMock        = $this->createMock(Context::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->adyenLoggerMock    = $this->createMock(AdyenLogger::class);
        $this->quoteMock          = $this->createMock(Quote::class);
        $this->paymentMock        = $this->createMock(Payment::class);

        $this->helper = new ShopperConversionId(
            $this->contextMock,
            $this->cartRepositoryMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->helper = null;
        parent::tearDown();
    }

    public function testGetShopperConversionIdReturnsExistingWithoutSaving(): void
    {
        $existing = 'existing-123';

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(ShopperConversionId::SHOPPER_CONVERSION_ID)
            ->willReturn($existing);

        $this->paymentMock->expects($this->never())
            ->method('setAdditionalInformation');

        $this->cartRepositoryMock->expects($this->never())
            ->method('save');

        $result = $this->helper->getShopperConversionId($this->quoteMock);

        $this->assertSame($existing, $result);
    }

    public function testGetShopperConversionIdGeneratesAndSaves(): void
    {
        $capturedId = null;

        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(ShopperConversionId::SHOPPER_CONVERSION_ID)
            ->willReturn(null);

        $this->paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                ShopperConversionId::SHOPPER_CONVERSION_ID,
                $this->callback(function ($val) use (&$capturedId) {
                    $capturedId = $val;
                    return is_string($val) && $val !== '';
                })
            );

        $this->quoteMock->expects($this->once())
            ->method('setPayment')
            ->with($this->paymentMock);

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock);

        $this->adyenLoggerMock->expects($this->never())
            ->method('error');

        $result = $this->helper->getShopperConversionId($this->quoteMock);

        $this->assertNotEmpty($result);
        $this->assertSame($capturedId, $result);

        // Optional: sanity-check UUIDv4 shape (don’t make it brittle)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    public function testGetShopperConversionIdHandlesRuntimeException(): void
    {
        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->paymentMock);

        $this->paymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with(ShopperConversionId::SHOPPER_CONVERSION_ID)
            ->willReturn(null);

        $this->paymentMock->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                ShopperConversionId::SHOPPER_CONVERSION_ID,
                $this->isType('string')
            );

        $this->quoteMock->expects($this->once())
            ->method('setPayment')
            ->with($this->paymentMock);

        $this->cartRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->quoteMock)
            ->willThrowException(new \RuntimeException('DB fail'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->callback(fn($msg) => str_contains($msg, 'Failed to generate shopperConversionId') && str_contains($msg, 'DB fail')));

        $result = $this->helper->getShopperConversionId($this->quoteMock);

        $this->assertNull($result);
    }
}
