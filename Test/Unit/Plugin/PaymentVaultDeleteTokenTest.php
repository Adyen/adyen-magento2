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

namespace Adyen\Payment\Test\Plugin;

use Adyen\Model\Recurring\DisableResult;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Plugin\PaymentVaultDeleteToken;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Helper\Requests;
use Adyen\Service\RecurringApi;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Adyen\Service\Recurring;
use Adyen\AdyenException;
use Adyen\Client;

class PaymentVaultDeleteTokenTest extends AbstractAdyenTestCase
{
    private $storeManagerMock;
    private $dataHelperMock;
    private $adyenLoggerMock;
    private $requestsHelperMock;
    private $vaultHelperMock;
    private $paymentVaultDeleteToken;

    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->dataHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->requestsHelperMock = $this->createMock(Requests::class);
        $this->vaultHelperMock = $this->createMock(\Adyen\Payment\Helper\Vault::class);

        $this->paymentVaultDeleteToken = new PaymentVaultDeleteToken(
            $this->storeManagerMock,
            $this->dataHelperMock,
            $this->adyenLoggerMock,
            $this->requestsHelperMock,
            $this->vaultHelperMock
        );
    }

    public function testSuccessfullyDisableValidAdyenPaymentTokenBeforeDeletion()
    {
        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $paymentTokenMock->method('getPaymentMethodCode')->willReturn('adyen_cc');
        $paymentTokenMock->method('getEntityId')->willReturn('123');

        $storeMock = $this->createGeneratedMock(\Magento\Store\Model\Store::class, ['getStoreId']);
        $storeMock->method('getStoreId')->willReturn(1);

        $storeManagerMock = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->vaultHelperMock->method('isAdyenPaymentCode')->willReturn(true);

        $clientMock = $this->createMock(Client::class);
        $recurringServiceMock = $this->createMock(RecurringApi::class);
        $this->dataHelperMock->method('initializeAdyenClient')->willReturn($clientMock);
        $this->dataHelperMock->method('initializeRecurringApi')->willReturn($recurringServiceMock);

        $recurringServiceMock->expects($this->once())
            ->method('disable')
            ->willReturn(new DisableResult(['response' => 'success']));

        $this->paymentVaultDeleteToken = new PaymentVaultDeleteToken(
            $storeManagerMock,
            $this->dataHelperMock,
            $this->adyenLoggerMock,
            $this->requestsHelperMock,
            $this->vaultHelperMock
        );

        $result = $this->paymentVaultDeleteToken->beforeDelete($this->createMock(PaymentTokenRepositoryInterface::class), $paymentTokenMock);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame([$paymentTokenMock], $result);
    }

    public function testHandleExceptionDuringAdyenApiCall()
    {
        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $paymentTokenMock->method('getPaymentMethodCode')->willReturn('adyen_cc');
        $paymentTokenMock->method('getEntityId')->willReturn('123');

        $storeMock = $this->createGeneratedMock(\Magento\Store\Model\Store::class, ['getStoreId']);
        $storeMock->method('getStoreId')->willReturn(1);

        $storeManagerMock = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $this->vaultHelperMock->method('isAdyenPaymentCode')->willReturn(true);

        $this->dataHelperMock->expects($this->once())
            ->method('initializeAdyenClient')
            ->willThrowException(new AdyenException('API Error'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error while attempting to disable token with id 123: API Error'));

        $this->paymentVaultDeleteToken = new PaymentVaultDeleteToken(
            $storeManagerMock,
            $this->dataHelperMock,
            $this->adyenLoggerMock,
            $this->requestsHelperMock,
            $this->vaultHelperMock
        );

        $result = $this->paymentVaultDeleteToken->beforeDelete($this->createMock(PaymentTokenRepositoryInterface::class), $paymentTokenMock);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame([$paymentTokenMock], $result);
    }
}
