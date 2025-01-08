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
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault as AdyenVaultHelper;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Model\Method\Vault;
use PHPUnit\Framework\MockObject\MockObject;

class RecurringVaultDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?RecurringVaultDataBuilder $recurringVaultDataBuilder;
    protected AdyenVaultHelper|MockObject $vaultHelperMock;
    protected StateData|MockObject $stateDataHelperMock;
    protected Config|MockObject $configHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->vaultHelperMock = $this->createMock(AdyenVaultHelper::class);
        $this->stateDataHelperMock = $this->createMock(StateData::class);
        $this->configHelperMock = $this->createMock(Config::class);

        $this->recurringVaultDataBuilder = new RecurringVaultDataBuilder(
            $this->stateDataHelperMock,
            $this->vaultHelperMock,
            $this->configHelperMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->recurringVaultDataBuilder = null;
    }

    /**
     * @param $paymentMethodCode
     * @param $tokenDetails
     * @param $tokenType
     * @param $isInstantPurchase
     *
     * @return void
     * @throws LocalizedException
     *
     * @dataProvider dataProvider
     */
    public function testBuild($paymentMethodCode, $tokenDetails, $tokenType, $isInstantPurchase)
    {
        $quoteId = 1;
        $storeId = 1;

        $paymentMethodProviderCode = str_replace('_vault', '', $paymentMethodCode);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn($quoteId);
        $orderMock->method('getStoreId')->willReturn($storeId);

        $paymentMethodInstanceMock = $this->createMock(Vault::class);
        $paymentMethodInstanceMock->method('getProviderCode')->willReturn($paymentMethodProviderCode);
        $paymentMethodInstanceMock->method('getCode')->willReturn($paymentMethodCode);

        $paymentTokenMock = $this->createMock(PaymentTokenInterface::class);
        $paymentTokenMock->method('getTokenDetails')->willReturn($tokenDetails);
        $paymentTokenMock->method('getGatewayToken')->willReturn("ABC1234567");
        $paymentTokenMock->method('getType')->willReturn($tokenType);

        $extensionAttributesMock = $this->createGeneratedMock(ExtensionAttributesInterface::class, [
            'getVaultPaymentToken'
        ]);
        $extensionAttributesMock->method('getVaultPaymentToken')->willReturn($paymentTokenMock);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getExtensionAttributes')->willReturn($extensionAttributesMock);
        $paymentMock->method('getAdditionalInformation')
            ->with('instant-purchase')
            ->willReturn($isInstantPurchase);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $this->vaultHelperMock->method('getPaymentMethodRecurringProcessingModel')
            ->willReturn('CardOnFile');

        if ($tokenType === PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD && !$isInstantPurchase) {
            $this->stateDataHelperMock->expects($this->once())
                ->method('getStateData')
                ->with($quoteId);
        }

        $request = $this->recurringVaultDataBuilder->build($buildSubject);

        $this->assertIsArray($request);
        $this->assertArrayHasKey('recurringProcessingModel', $request['body']);

        if ($tokenType === PaymentTokenFactoryInterface::TOKEN_TYPE_CREDIT_CARD) {
            $this->assertArrayHasKey('nativeThreeDS', $request['body']['authenticationData']['threeDSRequestData']);
            $this->assertArrayHasKey('holderName', $request['body']['paymentMethod']);
        } else {
            $this->assertArrayNotHasKey('additionalData', $request['body']);
        }
    }

    public static function dataProvider(): array
    {
        return [
            [
                'paymentMethodCode' => 'adyen_cc_vault',
                'tokenDetails' => '{"type":"visa","maskedCC":"1111","expirationDate":"3\/2030", "tokenType": "CardOnFile"}',
                'tokenType' => 'card',
                'isInstantPurchase' => false
            ],
            [
                'paymentMethodCode' => 'adyen_cc_vault',
                'tokenDetails' => '{"type":"visa","maskedCC":"1111","expirationDate":"3\/2030"}',
                'tokenType' => 'card',
                'isInstantPurchase' => false
            ],
            [
                'paymentMethodCode' => 'adyen_cc_vault',
                'tokenDetails' => '{"type":"visa","maskedCC":"1111","expirationDate":"3\/2030"}',
                'tokenType' => 'card',
                'isInstantPurchase' => true
            ],
            [
                'paymentMethodCode' => 'adyen_klarna_vault',
                'tokenDetails' => '{"type":"klarna", "tokenType": "CardOnFile"}',
                'tokenType' => 'account',
                'isInstantPurchase' => false
            ],
            [
                'paymentMethodCode' => 'adyen_klarna_vault',
                'tokenDetails' => '{"type":"klarna"}',
                'tokenType' => 'account',
                'isInstantPurchase' => false
            ],
            [
                'paymentMethodCode' => 'adyen_klarna_vault',
                'tokenDetails' => '{"type":"klarna"}',
                'tokenType' => 'account',
                'isInstantPurchase' => true
            ]
        ];
    }
}
