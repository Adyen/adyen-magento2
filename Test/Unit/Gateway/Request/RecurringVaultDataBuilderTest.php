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

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\RecurringVaultDataBuilder;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Method\Vault;

class RecurringVaultDataBuilderTest extends AbstractAdyenTestCase
{
    private object $recurringVaultDataBuilder;
    private $vaultHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->vaultHelperMock = $this->createMock(\Adyen\Payment\Helper\Vault::class);

        $objectManager = new ObjectManager($this);
        $this->recurringVaultDataBuilder = $objectManager->getObject(RecurringVaultDataBuilder::class, [
            'vaultHelper' => $this->vaultHelperMock
        ]);
    }

    /**
     * @param $paymentMethodCode
     * @param $tokenDetails
     * @param $expect3dsFlag
     * @dataProvider dataProvider
     * @return void
     */
    public function testBuild($paymentMethodCode, $tokenDetails, $expect3dsFlag)
    {
        $paymentMethodProviderCode = str_replace('_vault', '', $paymentMethodCode);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn(1);

        $paymentMethodInstanceMock = $this->createMock(Vault::class);
        $paymentMethodInstanceMock->method('getProviderCode')->willReturn($paymentMethodProviderCode);
        $paymentMethodInstanceMock->method('getCode')->willReturn($paymentMethodCode);

        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $paymentTokenMock->method('getTokenDetails')->willReturn($tokenDetails);
        $paymentTokenMock->method('getGatewayToken')->willReturn("ABC1234567");

        $extensionAttributesMock = $this->createGeneratedMock(ExtensionAttributesInterface::class, [
            'getVaultPaymentToken'
        ]);
        $extensionAttributesMock->method('getVaultPaymentToken')->willReturn($paymentTokenMock);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getExtensionAttributes')->willReturn($extensionAttributesMock);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $this->vaultHelperMock->method('getPaymentMethodRecurringProcessingModel')
            ->willReturn('CardOnFile');

        $request = $this->recurringVaultDataBuilder->build($buildSubject);

        $this->assertIsArray($request);
        $this->assertArrayHasKey('recurringProcessingModel', $request['body']);
        if ($expect3dsFlag) {
            $this->assertArrayHasKey('nativeThreeDS', $request['body']['authenticationData']['threeDSRequestData']);
        }
    }

    public static function dataProvider(): array
    {
        return [
            [
                'paymentMethodCode' => 'adyen_cc_vault',
                'tokenDetails' => '{"type":"visa","maskedCC":"1111","expirationDate":"3\/2030", "tokenType": "CardOnFile"}',
                'expect3dsFlag' => true
            ],
            [
                'paymentMethodCode' => 'adyen_cc_vault',
                'tokenDetails' => '{"type":"visa","maskedCC":"1111","expirationDate":"3\/2030"}',
                'expect3dsFlag' => true
            ],
            [
                'paymentMethodCode' => 'adyen_klarna_vault',
                'tokenDetails' => '{"type":"klarna", "tokenType": "CardOnFile"}',
                'expect3dsFlag' => false
            ],
            [
                'paymentMethodCode' => 'adyen_klarna_vault',
                'tokenDetails' => '{"type":"klarna"}',
                'expect3dsFlag' => false
            ],
        ];
    }
}
