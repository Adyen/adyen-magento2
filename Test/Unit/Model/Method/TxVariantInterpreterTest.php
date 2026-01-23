<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Method;

use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Adyen\Payment\Model\Method\TxVariantInterpreter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;
use UnexpectedValueException;

class TxVariantInterpreterTest extends AbstractAdyenTestCase
{
    private DataHelper $dataHelper;
    private PaymentMethodsHelper $paymentMethodsHelper;

    protected function setUp(): void
    {
        $this->dataHelper = $this->createMock(DataHelper::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethodsHelper::class);
    }

    public function testResolvesWithFullTxVariantAndExtractsCardWhenWallet(): void
    {
        $txVariant = 'mc_googlepay';
        $fullMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . $txVariant;

        $methodInstance = $this->createMock(MethodInterface::class);

        $this->dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($fullMethodCode)
            ->willReturn($methodInstance);

        $this->paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(true);

        $sut = new TxVariantInterpreter($txVariant, $this->dataHelper, $this->paymentMethodsHelper);

        $this->assertSame($methodInstance, $sut->getMethodInstance());
        $this->assertSame('mc', $sut->getCard());
    }

    public function testFallsBackToParsedPaymentMethodPartWhenFullTxVariantFails(): void
    {
        $txVariant = 'mc_googlepay';

        $fullMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . $txVariant;
        $fallbackMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . 'googlepay';

        $methodInstance = $this->createMock(MethodInterface::class);

        $call = 0;

        $this->dataHelper->expects($this->exactly(2))
            ->method('getMethodInstance')
            ->willReturnCallback(function (string $methodCode) use (
                &$call,
                $fullMethodCode,
                $fallbackMethodCode,
                $methodInstance
            ) {
                $call++;

                if ($call === 1) {
                    $this->assertSame($fullMethodCode, $methodCode);
                    throw new UnexpectedValueException('not found');
                }

                if ($call === 2) {
                    $this->assertSame($fallbackMethodCode, $methodCode);
                    return $methodInstance;
                }

                $this->fail('getMethodInstance called more than twice');
            });

        $this->paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(true);

        $sut = new TxVariantInterpreter($txVariant, $this->dataHelper, $this->paymentMethodsHelper);

        $this->assertSame($methodInstance, $sut->getMethodInstance());
        $this->assertSame('mc', $sut->getCard());
    }

    public function testUnderscoreNonWalletDoesNotThrowAndDoesNotSetCard(): void
    {
        // Example: not wallet but includes underscore
        $txVariant = 'facilypay_3x';
        $fullMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . $txVariant;

        $methodInstance = $this->createMock(MethodInterface::class);

        $this->dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($fullMethodCode)
            ->willReturn($methodInstance);

        $this->paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(false);

        $sut = new TxVariantInterpreter($txVariant, $this->dataHelper, $this->paymentMethodsHelper);

        $this->assertSame($methodInstance, $sut->getMethodInstance());
        $this->assertNull($sut->getCard());
    }

    public function testThrowsWhenNeitherFullNorFallbackMethodInstanceCanBeResolved(): void
    {
        $txVariant = 'mc_googlepay';

        $fullMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . $txVariant;
        $fallbackMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . 'googlepay';

        $call = 0;

        $this->dataHelper->expects($this->exactly(2))
            ->method('getMethodInstance')
            ->willReturnCallback(function (string $methodCode) use (
                &$call,
                $fullMethodCode,
                $fallbackMethodCode
            ) {
                $call++;

                if ($call === 1) {
                    $this->assertSame($fullMethodCode, $methodCode);
                    throw new UnexpectedValueException('no such method');
                }

                if ($call === 2) {
                    $this->assertSame($fallbackMethodCode, $methodCode);
                    throw new UnexpectedValueException('no such method');
                }

                $this->fail('getMethodInstance called more than twice');
            });

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Payment method instance not found for txVariant "%s" (attempted "%s").',
                $txVariant,
                $fallbackMethodCode
            )
        );

        new TxVariantInterpreter($txVariant, $this->dataHelper, $this->paymentMethodsHelper);
    }

    public function testWalletWithoutUnderscoreDoesNotSetCard(): void
    {
        $txVariant = 'googlepay';
        $fullMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . $txVariant;

        $methodInstance = $this->createMock(MethodInterface::class);

        $this->dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($fullMethodCode)
            ->willReturn($methodInstance);

        $this->paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(true);

        $sut = new TxVariantInterpreter($txVariant, $this->dataHelper, $this->paymentMethodsHelper);

        $this->assertSame($methodInstance, $sut->getMethodInstance());
        $this->assertNull($sut->getCard());
    }
}
