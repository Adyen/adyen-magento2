<?php
declare(strict_types=1);

/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Payment\Helper\ConnectedTerminals;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\PosPayment;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class ConnectedTerminalsTest extends AbstractAdyenTestCase
{
    private $adyenHelper;
    private $session;
    private $adyenLogger;
    private $connectedTerminals;

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createMock(Data::class);
        $this->session = $this->createMock(Session::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);

        $this->connectedTerminals = new ConnectedTerminals(
            $this->adyenHelper,
            $this->session,
            $this->adyenLogger
        );
    }

    public function testGetConnectedTerminalsReturnsEmptyWhenApiKeyIsEmpty()
    {
        $this->adyenHelper->method('getPosApiKey')->with(1)->willReturn('');

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with("Required field POS API key is not configured! Check your Adyen configuration.");

        $this->adyenHelper->expects($this->never())->method('initializeAdyenClientForPos');

        $result = $this->connectedTerminals->getConnectedTerminals(1);

        $this->assertEquals([], $result);
    }

    public function testGetConnectedTerminalsReturnsEmptyWhenApiKeyIsNull()
    {
        $this->adyenHelper->method('getPosApiKey')->with(1)->willReturn(null);

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with("Required field POS API key is not configured! Check your Adyen configuration.");

        $this->adyenHelper->expects($this->never())->method('initializeAdyenClientForPos');

        $result = $this->connectedTerminals->getConnectedTerminals(1);

        $this->assertEquals([], $result);
    }

    public function testGetConnectedTerminalsUsesSessionStoreIdWhenNotProvided()
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(1);
        $this->session->method('getQuote')->willReturn($quote);

        $this->adyenHelper->expects($this->once())
            ->method('getPosApiKey')
            ->with(1)
            ->willReturn('');

        $this->adyenLogger->expects($this->once())->method('error');

        $result = $this->connectedTerminals->getConnectedTerminals();

        $this->assertEquals([], $result);
    }

    public function testGetConnectedTerminalsReturnsResponseOnSuccess()
    {
        $expectedResponse = ['uniqueTerminalIds' => ['POS-123456789']];

        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelper->method('getPosApiKey')->with(1)->willReturn('test_api_key');
        $this->adyenHelper->method('initializeAdyenClientForPos')->with(1, 'test_api_key')->willReturn($client);
        $this->adyenHelper->method('createAdyenPosPaymentService')->with($client)->willReturn($service);
        $this->adyenHelper->method('getAdyenMerchantAccount')
            ->with('adyen_pos_cloud', 1)
            ->willReturn('TestMerchant');
        $this->adyenHelper->method('getPosStoreId')->with(1)->willReturn('');

        $service->expects($this->once())
            ->method('getConnectedTerminals')
            ->with(['merchantAccount' => 'TestMerchant'])
            ->willReturn($expectedResponse);

        $this->adyenHelper->expects($this->once())->method('logResponse')->with($expectedResponse);

        $result = $this->connectedTerminals->getConnectedTerminals(1);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetConnectedTerminalsIncludesPosStoreIdWhenConfigured()
    {
        $expectedResponse = ['uniqueTerminalIds' => ['POS-123456789']];

        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelper->method('getPosApiKey')->with(1)->willReturn('test_api_key');
        $this->adyenHelper->method('initializeAdyenClientForPos')->willReturn($client);
        $this->adyenHelper->method('createAdyenPosPaymentService')->willReturn($service);
        $this->adyenHelper->method('getAdyenMerchantAccount')->willReturn('TestMerchant');
        $this->adyenHelper->method('getPosStoreId')->with(1)->willReturn('POS_STORE_1');

        $service->expects($this->once())
            ->method('getConnectedTerminals')
            ->with([
                'merchantAccount' => 'TestMerchant',
                'store' => 'POS_STORE_1'
            ])
            ->willReturn($expectedResponse);

        $result = $this->connectedTerminals->getConnectedTerminals(1);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetConnectedTerminalsReturnsEmptyOnAdyenException()
    {
        $client = $this->createMock(Client::class);
        $service = $this->createMock(PosPayment::class);

        $this->adyenHelper->method('getPosApiKey')->with(1)->willReturn('test_api_key');
        $this->adyenHelper->method('initializeAdyenClientForPos')->willReturn($client);
        $this->adyenHelper->method('createAdyenPosPaymentService')->willReturn($service);
        $this->adyenHelper->method('getAdyenMerchantAccount')->willReturn('TestMerchant');
        $this->adyenHelper->method('getPosStoreId')->willReturn('');

        $service->method('getConnectedTerminals')->willThrowException(new AdyenException());

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with("The getConnectedTerminals response is empty check your Adyen configuration in Magento.");

        $this->adyenHelper->expects($this->never())->method('logResponse');

        $result = $this->connectedTerminals->getConnectedTerminals(1);

        $this->assertEquals([], $result);
    }
}
