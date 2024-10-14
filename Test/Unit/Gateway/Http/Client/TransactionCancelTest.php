<?php

namespace Adyen\Payment\Test\Unit\Gateway\Http\Client;

use Adyen\Client;
use Adyen\Model\Checkout\PaymentCancelResponse;
use Adyen\Payment\Gateway\Http\Client\TransactionCancel;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Idempotency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Http\TransferInterface;
use Adyen\Service\Checkout;
use Adyen\AdyenException;

class TransactionCancelTest extends AbstractAdyenTestCase
{
    private $adyenHelperMock;
    private $idempotencyHelperMock;
    private $transferObjectMock;
    private $checkoutServiceMock;
    private $transactionCancel;

    protected function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->setUpAdyenHelperMockExpectations();

        $this->idempotencyHelperMock = $this->createMock(Idempotency::class);
        $this->transferObjectMock = $this->createMock(TransferInterface::class);
        $this->clientMock = $this->createMock(Client::class);
        $this->checkoutServiceMock = $this->createMock(Checkout\ModificationsApi::class);
        $this->transferObjectMock->method('getClientConfig')->willReturn([]);
        $this->checkoutServiceMock
            ->method('cancelAuthorisedPaymentByPspReference')
            ->willReturn(new PaymentCancelResponse(['status' => 'received']));
        $this->transactionCancel = new TransactionCancel(
            $this->adyenHelperMock,
            $this->idempotencyHelperMock
        );
    }

    private function setUpAdyenHelperMockExpectations(): void
    {
        $this->adyenHelperMock->expects($this->once())
            ->method('getMagentoDetails')
            ->willReturn(['name' => 'Magento', 'version' => '2.x.x', 'edition' => 'Community']);

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleName')
            ->willReturn('adyen-magento2');

        $this->adyenHelperMock->expects($this->once())
            ->method('getModuleVersion')
            ->willReturn('1.2.3');
    }

    public function testSuccessfulCancellation()
    {
        // Arrange
        $requestBody = [
            [
                'merchantAccount' => 'TestMerchantAccount',
                'originalReference' => 'TestOriginalReference',
                'paymentPspReference' => 'paymentPspReference'
            ]
        ];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);
        $this->transferObjectMock->method('getHeaders')->willReturn([]);
        $this->transferObjectMock->method('getClientConfig')->willReturn([]);
        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($this->clientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($this->checkoutServiceMock);

        $expectedResult = ['status' => 'received'];

        // Act
        $result = $this->transactionCancel->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testCancellationWithAdyenApiException()
    {
        // Arrange
        $requestBody = [
            [
                'merchantAccount' => 'TestMerchantAccount',
                'originalReference' => 'TestOriginalReference',
                'paymentPspReference' => 'paymentPspReference'
            ]
        ];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($this->clientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($this->checkoutServiceMock);

        // Simulate Adyen API exception
        $this->checkoutServiceMock->method('cancelAuthorisedPaymentByPspReference')->willThrowException(new AdyenException('API exception'));

        // Act
        $result = $this->transactionCancel->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertSame($result, ['error' => 'API exception']);
    }

    public function testCancellationWithMultipleRequests()
    {
        // Arrange
        $requestBody = [
            [
                'merchantAccount' => 'TestMerchantAccount1',
                'originalReference' => 'TestOriginalReference1',
                'paymentPspReference' => 'paymentPspReference1'
            ],
            [
                'merchantAccount' => 'TestMerchantAccount2',
                'originalReference' => 'TestOriginalReference2',
                'paymentPspReference' => 'paymentPspReference2'
            ]
        ];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);
        $this->transferObjectMock->method('getHeaders')->willReturn([]);
        $this->transferObjectMock->method('getClientConfig')->willReturn([]);

        $this->adyenHelperMock->method('initializeAdyenClientWithClientConfig')->willReturn($this->clientMock);
        $this->adyenHelperMock->method('initializeModificationsApi')->willReturn($this->checkoutServiceMock);

        $expectedResults = ['status' => 'received'];

        // Act
        $results = $this->transactionCancel->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertEquals($expectedResults, $results);
    }

    public function testCancellationWithEmptyRequestArray()
    {
        // Arrange
        $requestBody = [];
        $this->transferObjectMock->method('getBody')->willReturn($requestBody);

        // Act
        $result = $this->transactionCancel->placeRequest($this->transferObjectMock);

        // Assert
        $this->assertEmpty($result);
    }
}
