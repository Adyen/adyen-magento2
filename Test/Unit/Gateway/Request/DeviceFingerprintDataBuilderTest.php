<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Payment\Gateway\Request\DeviceFingerprintDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;

class DeviceFingerprintDataBuilderTest extends AbstractAdyenTestCase
{
    private ?DeviceFingerprintDataBuilder $deviceFingerprintDataBuilder;

    protected function setUp(): void
    {
        $this->deviceFingerprintDataBuilder = new DeviceFingerprintDataBuilder();
    }

    protected function tearDown(): void
    {
        $this->deviceFingerprintDataBuilder = null;
    }

    public function testBuildAddsDeviceFingerprintWhenAvailable()
    {
        $buildSubject = $this->getBuildSubject('fingerprint-123');

        $request = $this->deviceFingerprintDataBuilder->build($buildSubject);

        $this->assertSame(
            ['body' => ['deviceFingerprint' => 'fingerprint-123']],
            $request
        );
    }

    #[DataProvider('emptyDeviceFingerprintProvider')]
    public function testBuildReturnsEmptyBodyWhenDeviceFingerprintIsEmpty($deviceFingerprint)
    {
        $buildSubject = $this->getBuildSubject($deviceFingerprint);

        $request = $this->deviceFingerprintDataBuilder->build($buildSubject);

        $this->assertSame(['body' => []], $request);
    }

    public static function emptyDeviceFingerprintProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero string' => ['0'],
        ];
    }

    public function testBuildThrowsExceptionWhenPaymentDataObjectIsMissing()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->deviceFingerprintDataBuilder->build([]);
    }

    private function getBuildSubject($deviceFingerprint): array
    {
        $paymentStub = $this->createStub(Payment::class);
        $paymentStub->method('getAdditionalInformation')
            ->willReturn($deviceFingerprint);

        return [
            'payment' => $this->createConfiguredStub(PaymentDataObject::class, [
                'getPayment' => $paymentStub
            ])
        ];
    }
}
