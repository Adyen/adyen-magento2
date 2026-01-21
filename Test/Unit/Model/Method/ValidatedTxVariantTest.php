<?php
declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Method;

use Adyen\Payment\Exception\TxVariantValidationException;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Adyen\Payment\Model\Method\ValidatedTxVariant;
use Magento\Payment\Helper\Data as DataHelper;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;

class ValidatedTxVariantTest extends TestCase
{
    public function testWalletVariantSetsCardAndResolvesMethodInstance(): void
    {
        $dataHelper = $this->createMock(DataHelper::class);
        $paymentMethodsHelper = $this->createMock(PaymentMethodsHelper::class);
        $methodInstance = $this->createMock(MethodInterface::class);

        $txVariant = 'mc_googlepay';
        $expectedMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . 'googlepay';

        $dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($expectedMethodCode)
            ->willReturn($methodInstance);

        $paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(true);

        $sut = new ValidatedTxVariant($txVariant, $dataHelper, $paymentMethodsHelper);

        $this->assertSame('mc', $sut->getCard());
        $this->assertSame($methodInstance, $sut->getMethodInstance());
    }

    public function testNonWalletVariantWithoutUnderscoreResolvesMethodInstanceAndKeepsCardNull(): void
    {
        $dataHelper = $this->createMock(DataHelper::class);
        $paymentMethodsHelper = $this->createMock(PaymentMethodsHelper::class);
        $methodInstance = $this->createMock(MethodInterface::class);

        $txVariant = 'ideal';
        $expectedMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . 'ideal';

        $dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($expectedMethodCode)
            ->willReturn($methodInstance);

        $paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(false);

        $sut = new ValidatedTxVariant($txVariant, $dataHelper, $paymentMethodsHelper);

        $this->assertNull($sut->getCard());
        $this->assertSame($methodInstance, $sut->getMethodInstance());
    }

    public function testFalsePositiveWithUnderscoreThrowsNotWalletWhenResolvedMethodIsNotWallet(): void
    {
        $dataHelper = $this->createMock(DataHelper::class);
        $paymentMethodsHelper = $this->createMock(PaymentMethodsHelper::class);
        $methodInstance = $this->createMock(MethodInterface::class);

        $txVariant = 'faciliypay_10x';
        $expectedMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . '10x';

        $dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($expectedMethodCode)
            ->willReturn($methodInstance);

        $paymentMethodsHelper->expects($this->once())
            ->method('isWalletPaymentMethod')
            ->with($methodInstance)
            ->willReturn(false);

        $this->expectException(TxVariantValidationException::class);
        $this->expectExceptionMessage(
            sprintf(
                'TxVariant "%s" resolved to "%s" but it is not a wallet payment method.',
                $txVariant,
                $expectedMethodCode
            )
        );

        new ValidatedTxVariant($txVariant, $dataHelper, $paymentMethodsHelper);
    }

    public function testThrowsMethodNotFoundWhenMethodInstanceCannotBeResolved(): void
    {
        $dataHelper = $this->createMock(DataHelper::class);
        $paymentMethodsHelper = $this->createMock(PaymentMethodsHelper::class);

        $txVariant = 'mc_googlepay';
        $expectedMethodCode = PaymentMethodsHelper::ADYEN_PREFIX . 'googlepay';

        $dataHelper->expects($this->once())
            ->method('getMethodInstance')
            ->with($expectedMethodCode)
            ->willThrowException(new \Exception('No such method'));

        // Ensure wallet check is never called if resolution fails
        $paymentMethodsHelper->expects($this->never())
            ->method('isWalletPaymentMethod');

        $this->expectException(TxVariantValidationException::class);
        $this->expectExceptionMessage(
            sprintf(
                'TxVariant "%s" resolved to "%s" but no such payment method exists.',
                $txVariant,
                $expectedMethodCode
            )
        );

        new ValidatedTxVariant($txVariant, $dataHelper, $paymentMethodsHelper);
    }
}
