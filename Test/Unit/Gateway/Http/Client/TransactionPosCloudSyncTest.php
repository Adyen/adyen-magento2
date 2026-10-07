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

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Gateway\Http\Client\TransactionPosCloudSync;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\PosPayment;
use Magento\Payment\Gateway\Http\TransferInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TransactionPosCloudSyncTest extends AbstractAdyenTestCase
{
    private Data|MockObject $adyenHelperMock;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private Config|MockObject $configHelperMock;
    private TransactionPosCloudSync $transactionPosCloudSync;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->transactionPosCloudSync = new TransactionPosCloudSync(
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->configHelperMock
        );
    }

    public function testPlaceRequestSuccess()
    {
        $requestBody = ['SaleToPOIRequest' => ['MessageHeader' => []]];
        $expectedResponse = ['SaleToPOIResponse' => ['PaymentResponse' => ['Response' => ['Result' => 'Success']]]];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getClientConfig' => ['storeId' => 1]
        ]);

        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelperMock->method('getPosApiKey')->with(1)->willReturn('pos_api_key');
        $this->adyenHelperMock->expects($this->once())
            ->method('initializeAdyenClientForPos')
            ->with(1, 'pos_api_key')
            ->willReturn($client);
        $this->adyenHelperMock->expects($this->once())
            ->method('createAdyenPosPaymentService')
            ->with($client)
            ->willReturn($service);

        $service->expects($this->once())
            ->method('runTenderSync')
            ->with($requestBody)
            ->willReturn($expectedResponse);

        $this->adyenHelperMock->expects($this->once())->method('logResponse')->with($expectedResponse);

        $result = $this->transactionPosCloudSync->placeRequest($transferObjectMock);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testPlaceRequestThrowsWhenApiKeyIsEmpty()
    {
        $requestBody = ['SaleToPOIRequest' => ['MessageHeader' => []]];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getClientConfig' => ['storeId' => 1]
        ]);

        $this->adyenHelperMock->method('getPosApiKey')->with(1)->willReturn('');
        $this->adyenHelperMock->expects($this->never())->method('initializeAdyenClientForPos');
        $this->adyenHelperMock->expects($this->never())->method('createAdyenPosPaymentService');

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenDebug')
            ->with('Required field POS API key is not configured! Check your Adyen configuration.');

        $result = $this->transactionPosCloudSync->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(
            'Required field POS API key is not configured! Check your Adyen configuration.',
            $result['error']
        );
    }

    public function testPlaceRequestHandlesServiceException()
    {
        $requestBody = ['SaleToPOIRequest' => ['MessageHeader' => []]];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getClientConfig' => ['storeId' => 1]
        ]);

        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelperMock->method('getPosApiKey')->with(1)->willReturn('pos_api_key');
        $this->adyenHelperMock->method('initializeAdyenClientForPos')->willReturn($client);
        $this->adyenHelperMock->method('createAdyenPosPaymentService')->willReturn($service);

        $service->method('runTenderSync')->willThrowException(new AdyenException('Connection timeout'));

        $this->adyenLoggerMock->expects($this->once())
            ->method('addAdyenDebug')
            ->with('Connection timeout');

        $result = $this->transactionPosCloudSync->placeRequest($transferObjectMock);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Connection timeout', $result['error']);
    }

    public function testPlaceRequestLogsRequest()
    {
        $requestBody = ['SaleToPOIRequest' => ['MessageHeader' => []]];

        $transferObjectMock = $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $requestBody,
            'getClientConfig' => ['storeId' => 1]
        ]);

        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelperMock->method('getPosApiKey')->willReturn('pos_api_key');
        $this->adyenHelperMock->method('initializeAdyenClientForPos')->willReturn($client);
        $this->adyenHelperMock->method('createAdyenPosPaymentService')->willReturn($service);
        $service->method('runTenderSync')->willReturn([]);

        $this->adyenHelperMock->expects($this->once())
            ->method('logRequest')
            ->with($requestBody, '', '/sync');

        $this->transactionPosCloudSync->placeRequest($transferObjectMock);
    }
}
