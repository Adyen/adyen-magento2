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

namespace Adyen\Payment\Test\Unit\Block\Adminhtml\System\Config\Field;

use Adyen\Payment\Block\Adminhtml\System\Config\Field\RecurringProcessingModel;
use Adyen\Payment\Block\Adminhtml\System\Config\Field\SelectYesNo;
use Adyen\Payment\Block\Adminhtml\System\Config\Field\Tokenization;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\DataObject;
use Magento\Framework\View\LayoutInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;

#[CoversClass(Tokenization::class)]
#[AllowMockObjectsWithoutExpectations]
class TokenizationTest extends AbstractAdyenTestCase
{
    private Tokenization $block;
    private MockObject $paymentMethodsHelperMock;
    private MockObject $dataHelperMock;

    protected function setUp(): void
    {
        parent::setUp();

        $hashCallback = fn ($value) => 'hash_' . $value;

        $enabledRendererMock = $this->getMockBuilder(SelectYesNo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calcOptionHash'])
            ->getMock();
        $enabledRendererMock->method('calcOptionHash')->willReturnCallback($hashCallback);

        $rpmRendererMock = $this->getMockBuilder(RecurringProcessingModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calcOptionHash'])
            ->getMock();
        $rpmRendererMock->method('calcOptionHash')->willReturnCallback($hashCallback);

        $layoutMock = $this->createMock(LayoutInterface::class);
        $layoutMock->method('createBlock')->willReturnMap([
            [SelectYesNo::class, '', ['data' => ['is_render_to_js_template' => true]], $enabledRendererMock],
            [RecurringProcessingModel::class, '', ['data' => ['is_render_to_js_template' => true]], $rpmRendererMock]
        ]);

        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->dataHelperMock = $this->createMock(Data::class);

        $this->block = $this->getMockBuilder(Tokenization::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLayout'])
            ->getMock();
        $this->block->method('getLayout')->willReturn($layoutMock);

        (new ReflectionProperty(Tokenization::class, 'paymentMethodsHelper'))
            ->setValue($this->block, $this->paymentMethodsHelperMock);
        (new ReflectionProperty(Tokenization::class, 'dataHelper'))
            ->setValue($this->block, $this->dataHelperMock);
    }

    #[DataProvider('prepareArrayRowDataProvider')]
    public function testPrepareArrayRow(
        ?string $recurringProcessingModel,
        array $supportedModels,
        array $expectedOptions,
        ?string $expectedColumnValue
    ): void {
        $methodInstanceMock = $this->createMock(MethodInterface::class);
        $this->dataHelperMock->method('getMethodInstance')
            ->with('adyen_method')
            ->willReturn($methodInstanceMock);
        $this->paymentMethodsHelperMock->method('getSupportedRecurringProcessingModels')
            ->with($methodInstanceMock)
            ->willReturn($supportedModels);

        $row = new DataObject([
            '_id' => '_row',
            'payment_method_code' => 'adyen_method',
            'name' => 'Payment Method',
            'enabled' => '1',
            'recurring_processing_model' => $recurringProcessingModel,
            'column_values' => ['_row_recurring_processing_model' => $recurringProcessingModel]
        ]);

        $this->invokeMethod($this->block, '_prepareArrayRow', [$row]);

        $this->assertSame($expectedOptions, $row->getData('option_extra_attrs'));
        $this->assertSame($expectedColumnValue, $row->getData('column_values')['_row_recurring_processing_model']);
    }

    public static function prepareArrayRowDataProvider(): array
    {
        $allModels = ['CardOnFile', 'Subscription', 'UnscheduledCardOnFile'];
        $selected = 'selected=\"selected\"';
        $hidden = 'disabled="disabled" hidden="hidden"';

        return [
            'all models supported' => [
                'CardOnFile',
                $allModels,
                ['option_hash_1' => $selected, 'option_hash_CardOnFile' => $selected],
                'CardOnFile'
            ],
            'restricted method hides unsupported model' => [
                'Subscription',
                ['Subscription', 'UnscheduledCardOnFile'],
                [
                    'option_hash_1' => $selected,
                    'option_hash_Subscription' => $selected,
                    'option_hash_CardOnFile' => $hidden
                ],
                'Subscription'
            ],
            'restricted method without saved model' => [
                null,
                ['Subscription'],
                [
                    'option_hash_1' => $selected,
                    'option_hash_CardOnFile' => $hidden,
                    'option_hash_UnscheduledCardOnFile' => $hidden
                ],
                null
            ],
            'saved unsupported model is not selected' => [
                'CardOnFile',
                ['Subscription', 'UnscheduledCardOnFile'],
                ['option_hash_1' => $selected, 'option_hash_CardOnFile' => $hidden],
                null
            ]
        ];
    }

    public function testPrepareArrayRowWithoutPaymentMethodCodeAllowsAllModels(): void
    {
        $this->dataHelperMock->expects($this->never())->method('getMethodInstance');

        $row = new DataObject([
            'enabled' => '0',
            'recurring_processing_model' => 'CardOnFile'
        ]);

        $this->invokeMethod($this->block, '_prepareArrayRow', [$row]);

        $this->assertSame(
            ['option_hash_CardOnFile' => 'selected=\"selected\"'],
            $row->getData('option_extra_attrs')
        );
    }
}
