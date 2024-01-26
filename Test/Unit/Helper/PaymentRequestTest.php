<?php

use Adyen\Client;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Recurring;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class PaymentRequestTest extends AbstractAdyenTestCase
{
    private $paymentRequest;
    private $configHelper;
    private $adyenHelper;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(Config::class);
        $this->configHelper
            ->method('getAdyenAbstractConfigData')
            ->willReturn('MERCHANT_ACCOUNT_PLACEHOLDER');

        $this->adyenHelper = $this->createMock(Data::class);
        $this->adyenHelper->method('padShopperReference')->willReturn('001');


        $objectManager = new ObjectManager($this);
        $this->paymentRequest = $objectManager->getObject(PaymentRequest::class, [
            'configHelper' => $this->configHelper,
            'adyenHelper' => $this->adyenHelper
        ]);
    }

    public function testListRecurringContractByType()
    {
        $recurringServiceMock = $this->createMock(Recurring::class);
        $recurringServiceMock->method('listRecurringDetails')->willReturn([]);

        $this->adyenHelper
            ->method('initializeAdyenClient')
            ->willReturn($this->createMock(Client::class));
        $this->adyenHelper->method('createAdyenRecurringService')->willReturn($recurringServiceMock);

        $this->assertIsArray($this->paymentRequest->listRecurringContractByType('001', 1, 'CardOnFile'));
    }

    /**
     * @dataProvider disableRecurringContractProvider
     */
    public function testDisableRecurringContract($response, $assert)
    {
        if (!$assert) {
            $this->expectException(LocalizedException::class);
        }

        $result = [
            'response' => $response
        ];

        $recurringServiceMock = $this->createMock(Recurring::class);
        $recurringServiceMock->method('disable')->willReturn($result);

        $this->adyenHelper
            ->method('initializeAdyenClient')
            ->willReturn($this->createMock(Client::class));
        $this->adyenHelper->method('createAdyenRecurringService')->willReturn($recurringServiceMock);

        $apiResponse = $this->paymentRequest->disableRecurringContract('TOKEN_PLACEHOLDER', '001', 1);

        if ($assert) {
            $this->assertTrue($apiResponse);
        }
    }

    public static function disableRecurringContractProvider(): array
    {
        return [
            [
                'response' => '[detail-successfully-disabled]',
                'assert' => true
            ],
            [
                'response' => '[failed]',
                'assert' => false
            ]
        ];
    }
}
