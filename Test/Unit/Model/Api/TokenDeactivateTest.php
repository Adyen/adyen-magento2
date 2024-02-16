<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\Api\TokenDeactivate;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

class TokenDeactivateTest extends AbstractAdyenTestCase
{
    private $paymentTokenRepositoryMock;
    private $paymentTokenManagementMock;
    private $adyenLoggerMock;
    private $tokenDeactivate;

    protected function setUp(): void
    {
        $this->paymentTokenRepositoryMock = $this->createMock(PaymentTokenRepositoryInterface::class);
        $this->paymentTokenManagementMock = $this->createMock(PaymentTokenManagement::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->tokenDeactivate = new TokenDeactivate(
            $this->paymentTokenRepositoryMock,
            $this->paymentTokenManagementMock,
            $this->adyenLoggerMock
        );
    }

    public function testSuccessfullyDeactivatePaymentToken()
    {
        $paymentToken = 'token123';
        $paymentMethodCode = 'adyen_cc';
        $customerId = 1;
        $expectedResult = true;

        $paymentTokenMock = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $paymentTokenMock->method('getEntityId')->willReturn('123');

        $this->paymentTokenManagementMock->expects($this->once())
            ->method('getByGatewayToken')
            ->with($paymentToken, $paymentMethodCode, $customerId)
            ->willReturn($paymentTokenMock);

        $this->paymentTokenRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($paymentTokenMock)
            ->willReturn($expectedResult);

        $result = $this->tokenDeactivate->deactivateToken($paymentToken, $paymentMethodCode, $customerId);

        $this->assertEquals($expectedResult, $result);
    }

    public function testAttemptToDeactivateNonExistentPaymentToken()
    {
        $paymentToken = null;
        $paymentMethodCode = 'adyen_cc';
        $customerId = 1;

        $this->paymentTokenManagementMock->expects($this->once())
            ->method('getByGatewayToken')
            ->with($this->equalTo('non_existent_token'), $this->equalTo($paymentMethodCode), $this->equalTo($customerId))
            ->willReturn($paymentToken);

        $result = $this->tokenDeactivate->deactivateToken('non_existent_token', $paymentMethodCode, $customerId);

        $this->assertFalse($result, "Expected the result to be false when attempting to deactivate a non-existent payment token.");
    }

    public function testDeactivateTokenWithInvalidCustomerId()
    {
        $paymentToken = 'fakeToken';
        $paymentMethodCode = 'adyen_cc';
        $customerId = 999;

        $this->paymentTokenManagementMock->expects($this->once())
            ->method('getByGatewayToken')
            ->with($paymentToken, $paymentMethodCode, $customerId)
            ->willReturn(null);

        $this->adyenLoggerMock->expects($this->never())
            ->method('error');

        $result = $this->tokenDeactivate->deactivateToken($paymentToken, $paymentMethodCode, $customerId);

        $this->assertFalse($result, "Expected the result to be false when deactivating a token with an invalid customer ID.");
    }

    public function testExceptionThrownDuringTokenDeletion()
    {
        $paymentToken = 'token123';
        $paymentMethodCode = 'adyen_cc';
        $customerId = 1;
        $exceptionMessage = 'Error during deletion';

        $paymentTokenMock = $this->createMock(\Magento\Vault\Api\Data\PaymentTokenInterface::class);
        $paymentTokenMock->method('getEntityId')->willReturn('123');

        $this->paymentTokenManagementMock->expects($this->once())
            ->method('getByGatewayToken')
            ->with($paymentToken, $paymentMethodCode, $customerId)
            ->willReturn($paymentTokenMock);

        $this->paymentTokenRepositoryMock->expects($this->once())
            ->method('delete')
            ->with($paymentTokenMock)
            ->willThrowException(new \Exception($exceptionMessage));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error while attempting to deactivate token with id 123'));

        $result = $this->tokenDeactivate->deactivateToken($paymentToken, $paymentMethodCode, $customerId);

        $this->assertFalse($result);
    }
}
