<?php

declare(strict_types=1);

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

class TransactionPosCloudSyncTest extends AbstractAdyenTestCase
{
    private TransactionPosCloudSync $transactionPosCloudSync;
    private MockObject $adyenHelper;
    private MockObject $adyenLogger;
    private MockObject $configHelper;
    private MockObject $client;
    private MockObject $posPaymentService;

    private array $request;
    private int $storeId = 1;

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createMock(Data::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->client = $this->createMock(Client::class);
        $this->posPaymentService = $this->createMock(PosPayment::class);

        $this->adyenHelper->method('getPosApiKey')->with($this->storeId)->willReturn('pos-api-key');
        $this->adyenHelper->method('initializeAdyenClient')->willReturn($this->client);
        $this->adyenHelper->method('createAdyenPosPaymentService')->with($this->client)
            ->willReturn($this->posPaymentService);

        $this->transactionPosCloudSync = new TransactionPosCloudSync(
            $this->adyenHelper,
            $this->adyenLogger,
            $this->configHelper
        );

        $this->request = ['SaleToPOIRequest' => ['MessageHeader' => ['POIID' => 'TERMINAL-123']]];
    }

    private function buildTransferObject(): MockObject
    {
        return $this->createConfiguredMock(TransferInterface::class, [
            'getBody' => $this->request,
            'getClientConfig' => ['storeId' => $this->storeId]
        ]);
    }

    public function testPlaceRequestReturnsSuccessfulResponse(): void
    {
        $responseArray = ['Response' => ['Result' => 'Success']];

        $this->configHelper->expects($this->once())
            ->method('getAdyenPosCloudConfigData')
            ->with('total_timeout', $this->storeId)
            ->willReturn(130);

        $this->client->expects($this->once())->method('setTimeout')->with(130);

        $this->adyenHelper->expects($this->once())->method('logRequest')->with($this->request, '', '/sync');
        $this->adyenHelper->expects($this->once())->method('logResponse')->with($responseArray);

        $this->posPaymentService->expects($this->once())
            ->method('runTenderSync')
            ->with($this->request)
            ->willReturn($responseArray);

        $result = $this->transactionPosCloudSync->placeRequest($this->buildTransferObject());

        $this->assertEquals($responseArray, $result);
    }

    public function testPlaceRequestWithoutTimeoutDoesNotSetTimeout(): void
    {
        $responseArray = ['Response' => ['Result' => 'Success']];

        $this->configHelper->method('getAdyenPosCloudConfigData')
            ->with('total_timeout', $this->storeId)
            ->willReturn(null);

        $this->client->expects($this->never())->method('setTimeout');

        $this->posPaymentService->method('runTenderSync')->willReturn($responseArray);

        $result = $this->transactionPosCloudSync->placeRequest($this->buildTransferObject());

        $this->assertEquals($responseArray, $result);
    }

    public function testPlaceRequestHandlesAdyenException(): void
    {
        $this->configHelper->method('getAdyenPosCloudConfigData')->willReturn(130);

        $this->posPaymentService->method('runTenderSync')
            ->willThrowException(new AdyenException('POS payment failed'));

        $this->adyenLogger->expects($this->once())->method('addAdyenDebug');
        $this->adyenHelper->expects($this->once())->method('logResponse');

        $result = $this->transactionPosCloudSync->placeRequest($this->buildTransferObject());

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('POS payment failed', $result['error']);
    }
}
