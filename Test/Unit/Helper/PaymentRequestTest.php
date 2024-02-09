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

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Client;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout;
use Adyen\Service\Recurring;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

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

    public function testAuthorise3d()
    {
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getStoreId')->willReturn(1);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $checkoutServiceMock = $this->createMock(Checkout::class);
        $checkoutServiceMock->method('paymentsDetails')->willReturn([]);

        $this->adyenHelper
            ->method('initializeAdyenClient')
            ->willReturn($this->createMock(Client::class));

        $this->adyenHelper->method('createAdyenCheckoutService')->willReturn($checkoutServiceMock);

        $result = $this->paymentRequest->authorise3d($paymentMock);
        $this->assertIsArray($result);
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
