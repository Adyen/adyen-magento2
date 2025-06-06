<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Helper\Unit\Model;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\CleanupAdditionalInformation;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class CleanupAdditionalInformationTest extends AbstractAdyenTestCase
{
    protected ?CleanupAdditionalInformation $cleanupAdditionalInformation;
    protected Payment|MockObject $orderPaymentMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    public function setUp(): void
    {
        $this->orderPaymentMock = $this->createMock(Payment::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->cleanupAdditionalInformation = new CleanupAdditionalInformation(
            $this->adyenLoggerMock
        );
    }

    public function tearDown(): void
    {
        $this->cleanupAdditionalInformation = null;
    }

    public function testExecute()
    {
        $this->orderPaymentMock
            ->expects($this->exactly(count(CleanupAdditionalInformationInterface::FIELDS_TO_BE_CLEANED_UP)))
            ->method('unsAdditionalInformation')
            ->willReturnMap([
                [CleanupAdditionalInformationInterface::FIELD_ADDITIONAL_DATA, $this->orderPaymentMock],
                [CleanupAdditionalInformationInterface::FIELD_ACTION, $this->orderPaymentMock]
            ]);

        $result = $this->cleanupAdditionalInformation->execute($this->orderPaymentMock);
        $this->assertInstanceOf(Payment::class, $result);
    }
}
