<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Model\Config\Backend;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Config\Backend\Tokenization;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Validator\Exception;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(Tokenization::class)]
#[AllowMockObjectsWithoutExpectations]
class TokenizationTest extends AbstractAdyenTestCase
{
    private Tokenization $model;
    private MockObject $serializerMock;
    private MockObject $paymentMethodsHelperMock;
    private MockObject $dataHelperMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->dataHelperMock = $this->createMock(Data::class);

        $this->model = $this->getMockBuilder(Tokenization::class)
            ->setConstructorArgs([
                $this->createConfiguredMock(Context::class, [
                    'getEventDispatcher' => $this->createMock(ManagerInterface::class),
                ]),
                $this->createMock(Registry::class),
                $this->createMock(ScopeConfigInterface::class),
                $this->createMock(TypeListInterface::class),
                $this->createMock(Random::class),
                $this->serializerMock,
                $this->paymentMethodsHelperMock,
                $this->dataHelperMock,
                $this->createMock(AbstractResource::class),
                $this->createMock(AbstractDb::class),
                [],
            ])
            ->onlyMethods([])
            ->getMock();
    }

    private function mockSupportedModels(array $supportedModelsByCode): void
    {
        $methodInstances = [];
        foreach (array_keys($supportedModelsByCode) as $code) {
            $methodInstances[$code] = $this->createConfiguredMock(MethodInterface::class, ['getCode' => $code]);
        }

        $this->dataHelperMock->method('getMethodInstance')
            ->willReturnCallback(fn (string $code) => $methodInstances[$code]);
        $this->paymentMethodsHelperMock->method('getSupportedRecurringProcessingModels')
            ->willReturnCallback(fn (MethodInterface $method) => $supportedModelsByCode[$method->getCode()]);
    }

    private static function row(string $code, string $enabled, ?string $recurringProcessingModel): array
    {
        return [
            'payment_method_code' => $code,
            'name' => $code . ' title',
            'enabled' => $enabled,
            'recurring_processing_model' => $recurringProcessingModel
        ];
    }

    #[DataProvider('validConfigurationDataProvider')]
    public function testBeforeSaveSerializesValidConfiguration(array $rows): void
    {
        $this->mockSupportedModels([
            'adyen_cc' => ['CardOnFile', 'Subscription', 'UnscheduledCardOnFile'],
            'adyen_restricted' => ['Subscription', 'UnscheduledCardOnFile']
        ]);

        $expected = [];
        foreach ($rows as $row) {
            $expected[$row['payment_method_code']] = [
                'name' => $row['name'],
                'enabled' => $row['enabled'],
                'recurringProcessingModel' => $row['recurring_processing_model']
            ];
        }

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with($expected)
            ->willReturn('serialized');

        $this->model->setData('value', $rows);
        $this->model->beforeSave();

        $this->assertSame('serialized', $this->model->getData('value'));
    }

    public static function validConfigurationDataProvider(): array
    {
        return [
            'unrestricted method accepts any model' => [
                ['_1' => self::row('adyen_cc', '1', 'CardOnFile')]
            ],
            'restricted method with supported model' => [
                [
                    '_1' => self::row('adyen_cc', '1', 'CardOnFile'),
                    '_2' => self::row('adyen_restricted', '1', 'UnscheduledCardOnFile')
                ]
            ],
            'disabled restricted method with unsupported model' => [
                ['_1' => self::row('adyen_restricted', '0', 'CardOnFile')]
            ]
        ];
    }

    public function testBeforeSaveThrowsExceptionForUnsupportedRecurringProcessingModel(): void
    {
        $this->mockSupportedModels([
            'adyen_cc' => ['CardOnFile', 'Subscription', 'UnscheduledCardOnFile'],
            'adyen_restricted' => ['Subscription', 'UnscheduledCardOnFile']
        ]);

        $this->serializerMock->expects($this->never())->method('serialize');

        $this->model->setData('value', [
            '_1' => self::row('adyen_cc', '1', 'CardOnFile'),
            '_2' => self::row('adyen_restricted', '1', 'CardOnFile')
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'The recurring processing model CardOnFile is not supported by adyen_restricted title.'
        );
        $this->model->beforeSave();
    }
}
