<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\CaseManagement;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

class CaseManagementTest extends AbstractAdyenTestCase
{
    public function testRequiresManualReviewTrue(): void
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => 'true'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertTrue($caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewNoFraudKey(): void
    {
        $additionalData = ['test' => 'myPatience'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertFalse($caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testRequiresManualReviewUnexpectedValue(): void
    {
        $additionalData = [CaseManagement::FRAUD_MANUAL_REVIEW => '1'];
        $caseManagementHelper = $this->createCaseManagementHelper();

        $this->assertFalse($caseManagementHelper->requiresManualReview($additionalData));
    }

    public function testMarkCaseAsPendingReviewWithReviewRequiredStatus(): void
    {
        $pspReference = 'PSPREFERENCE';
        $reviewRequiredStatus = 'fraud_manual_review_status';
        $expectedManualReviewComment = 'Manual review required for order w/pspReference: PSPREFERENCE. Please check the Adyen platform.';
        $order = $this->createMock(Order::class);
        $payment = $this->createGeneratedMock(Payment::class, [], ['getData']);
        $configHelper = $this->createMock(Config::class);
        $logger = $this->createMock(AdyenLogger::class);
        $storeId = 1;

        $configHelper->method('getFraudStatus')
            ->with(Config::XML_STATUS_FRAUD_MANUAL_REVIEW, $storeId)
            ->willReturn($reviewRequiredStatus);

        $order->method('getState')
            ->willReturn(Order::STATE_NEW);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getData')->willReturn('entity_id');
        $this->assertTrue(method_exists($payment, 'getData'));

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($expectedManualReviewComment, $reviewRequiredStatus);
        $order->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);
        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Order'),
                $this->logicalAnd(
                    $this->arrayHasKey('pspReference'),
                    $this->arrayHasKey('merchantReference')
                )
            );

        $caseManagementHelper = $this->createCaseManagementHelper($logger, $configHelper);
        $returnOrder = $caseManagementHelper->markCaseAsPendingReview($order, $pspReference);
        $this->assertSame($order, $returnOrder);
    }

    public function testMarkCaseAsPendingReviewWithoutReviewRequiredStatus(): void
    {
        $pspReference = 'PSPREFERENCE';
        $reviewRequiredStatus = null;
        $expectedManualReviewComment = 'Manual review required for order w/pspReference: PSPREFERENCE. Please check the Adyen platform.';
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $payment = $this->createGeneratedMock(Payment::class, [], ['getData']);
        $configHelper = $this->createMock(Config::class);
        $logger = $this->createMock(AdyenLogger::class);

        $configHelper->method('getFraudStatus')->willReturn($reviewRequiredStatus);
        $order->method('getPayment')->willReturn($payment);
        $payment->method('getData')->willReturn('entity_id');

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($expectedManualReviewComment);

        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Order'),
                $this->logicalAnd(
                    $this->arrayHasKey('pspReference'),
                    $this->arrayHasKey('merchantReference')
                )
            );

        $caseManagementHelper = new CaseManagement($logger, $configHelper);
        $returnOrder = $caseManagementHelper->markCaseAsPendingReview($order, $pspReference);
        $this->assertSame($order, $returnOrder);
    }

    public function testMarkCaseAsAcceptedWithReviewAcceptStatus(): void
    {
        $comment = 'Order accepted';
        $reviewAcceptStatus = 'fraud_manual_review_accept_status';
        $storeId = 1;
        $order = $this->createMock(Order::class);
        $payment = $this->createGeneratedMock(Payment::class, [], ['getData']);
        $logger = $this->createMock(AdyenLogger::class);
        $configHelper = $this->createMock(Config::class);
        $caseManagementHelper = new CaseManagement($logger, $configHelper);

        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn($storeId);
        $configHelper->method('getFraudStatus')->willReturn($reviewAcceptStatus);
        $payment->method('getData')->willReturn('entity_id');

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(__($comment), $reviewAcceptStatus);

        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Order'),
                $this->logicalAnd(
                    $this->arrayHasKey('pspReference'),
                    $this->arrayHasKey('merchantReference')
                )
            );

        $caseManagementHelper->markCaseAsAccepted($order, $comment);
    }

    public function testMarkCaseAsAcceptedWithoutReviewAcceptStatus(): void
    {
        $comment = 'Order accepted';
        $reviewAcceptStatus = null;
        $storeId = 1;
        $order = $this->createMock(Order::class);
        $payment = $this->createGeneratedMock(Payment::class, [], ['getData']);
        $logger = $this->createMock(AdyenLogger::class);
        $configHelper = $this->createMock(Config::class);
        $caseManagementHelper = new CaseManagement($logger, $configHelper);

        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn($storeId);
        $configHelper->method('getFraudStatus')->willReturn($reviewAcceptStatus);
        $logger->method('getOrderContext')->willReturn([]);

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(__($comment));

        $logger->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Order'),
                $this->logicalAnd(
                    $this->arrayHasKey('pspReference'),
                )
            );

        $caseManagementHelper->markCaseAsAccepted($order, $comment);
    }

    public function testMarkCaseAsRejectedRefunded(): void
    {
        $originalPspReference = 'PSPREFERENCE';
        $comment = 'Manual review was rejected for order w/pspReference: %s. The order will be automatically refunded.';
        $autoCapture = true;
        $order = $this->createMock(Order::class);
        $logger = $this->createMock(AdyenLogger::class);
        $configHelper = $this->createMock(Config::class);

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(sprintf(
                $comment,
                $originalPspReference
            ));

        $caseManagementHelper = new CaseManagement($logger, $configHelper);
        $caseManagementHelper->markCaseAsRejected($order, $originalPspReference, $autoCapture);
    }

    public function testMarkCaseAsRejectedCancelled(): void
    {
        $originalPspReference = 'PSPREFERENCE';
        $comment = 'Manual review was rejected for order w/pspReference: %s. The order will be automatically cancelled.';
        $autoCapture = false;
        $order = $this->createMock(Order::class);
        $logger = $this->createMock(AdyenLogger::class);
        $configHelper = $this->createMock(Config::class);

        $order->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with(sprintf(
                $comment,
                $originalPspReference
            ));

        $caseManagementHelper = new CaseManagement($logger, $configHelper);
        $caseManagementHelper->markCaseAsRejected($order, $originalPspReference, $autoCapture);
    }

    /**
     * @param $adyenLoggerMock
     * @param $adyenConfigHelperMock
     * @return CaseManagement
     */
    public function createCaseManagementHelper(
        $adyenLoggerMock = null,
        $adyenConfigHelperMock = null
    ): CaseManagement
    {
        if (is_null($adyenLoggerMock)) {
            $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        }

        if (is_null($adyenConfigHelperMock)) {
            $adyenConfigHelperMock = $this->createMock(Config::class);
        }

        return new CaseManagement(
            $adyenLoggerMock,
            $adyenConfigHelperMock
        );
    }
}
