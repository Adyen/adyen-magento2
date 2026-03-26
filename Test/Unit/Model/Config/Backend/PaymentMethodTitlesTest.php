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

use Adyen\Payment\Model\Config\Backend\PaymentMethodTitles;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(PaymentMethodTitles::class)]
class PaymentMethodTitlesTest extends AbstractAdyenTestCase
{
    private PaymentMethodTitles $model;
    private MockObject $serializerMock;
    private MockObject $mathRandomMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->mathRandomMock = $this->createMock(Random::class);

        $this->model = $this->getMockBuilder(PaymentMethodTitles::class)
            ->setConstructorArgs([
                $this->createConfiguredMock(Context::class, [
                    'getEventDispatcher' => $this->createMock(ManagerInterface::class),
                ]),
                $this->createMock(Registry::class),
                $this->createMock(ScopeConfigInterface::class),
                $this->createMock(TypeListInterface::class),
                $this->mathRandomMock,
                $this->serializerMock,
                $this->createMock(AbstractResource::class),
                $this->createMock(AbstractDb::class),
                [],
            ])
            ->onlyMethods([])
            ->getMock();
    }

    public function testBeforeSaveSerializesValidRows(): void
    {
        $inputRows = [
            'row1' => ['payment_method_type' => 'scheme', 'title' => 'Kreditkarte'],
            'row2' => ['payment_method_type' => 'klarna', 'title' => 'Klarna'],
        ];

        $expectedMap = ['scheme' => 'Kreditkarte', 'klarna' => 'Klarna'];
        $serialized  = '{"scheme":"Kreditkarte","klarna":"Klarna"}';

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with($expectedMap)
            ->willReturn($serialized);

        $this->model->setData('value', $inputRows);
        $result = $this->model->beforeSave();

        $this->assertInstanceOf(PaymentMethodTitles::class, $result);
        $this->assertSame($serialized, $this->model->getData('value'));
    }

    public function testBeforeSaveSkipsRowsWithEmptyType(): void
    {
        $inputRows = [
            'row1' => ['payment_method_type' => '', 'title' => 'Some Title'],
            'row2' => ['payment_method_type' => 'klarna', 'title' => 'Klarna'],
        ];

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with(['klarna' => 'Klarna'])
            ->willReturn('{"klarna":"Klarna"}');

        $this->model->setData('value', $inputRows);
        $this->model->beforeSave();

        $this->assertSame('{"klarna":"Klarna"}', $this->model->getData('value'));
    }

    public function testBeforeSaveSkipsRowsWithEmptyTitle(): void
    {
        $inputRows = [
            'row1' => ['payment_method_type' => 'scheme', 'title' => ''],
            'row2' => ['payment_method_type' => 'ideal', 'title' => 'iDEAL'],
        ];

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with(['ideal' => 'iDEAL'])
            ->willReturn('{"ideal":"iDEAL"}');

        $this->model->setData('value', $inputRows);
        $this->model->beforeSave();

        $this->assertSame('{"ideal":"iDEAL"}', $this->model->getData('value'));
    }

    public function testBeforeSaveDeduplicatesOnLastRow(): void
    {
        $inputRows = [
            'row1' => ['payment_method_type' => 'scheme', 'title' => 'Cards'],
            'row2' => ['payment_method_type' => 'scheme', 'title' => 'Kreditkarte'],
        ];

        $this->serializerMock->expects($this->once())
            ->method('serialize')
            ->with(['scheme' => 'Kreditkarte'])
            ->willReturn('{"scheme":"Kreditkarte"}');

        $this->model->setData('value', $inputRows);
        $this->model->beforeSave();

        $this->assertSame('{"scheme":"Kreditkarte"}', $this->model->getData('value'));
    }

    public function testBeforeSaveWithNonArrayValueReturnsEarly(): void
    {
        $this->serializerMock->expects($this->never())->method('serialize');

        $this->model->setData('value', 'not-an-array');
        $result = $this->model->beforeSave();

        $this->assertInstanceOf(PaymentMethodTitles::class, $result);
        $this->assertSame('not-an-array', $this->model->getData('value'));
    }

    public function testAfterLoadDeserializesAndSetsRows(): void
    {
        $serialized = '{"scheme":"Kreditkarte","ideal":"iDEAL"}';
        $decoded    = ['scheme' => 'Kreditkarte', 'ideal' => 'iDEAL'];

        $this->serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($serialized)
            ->willReturn($decoded);

        $this->mathRandomMock->method('getUniqueHash')
            ->willReturnOnConsecutiveCalls('_abc', '_def');

        $expectedRows = [
            '_abc' => ['payment_method_type' => 'scheme', 'title' => 'Kreditkarte'],
            '_def' => ['payment_method_type' => 'ideal',  'title' => 'iDEAL'],
        ];

        $this->model->setData('value', $serialized);
        $this->invokeMethod($this->model, '_afterLoad');

        $this->assertSame($expectedRows, $this->model->getData('value'));
    }

    public function testAfterLoadWithEmptyValueReturnsEarly(): void
    {
        $this->serializerMock->expects($this->never())->method('unserialize');

        $this->model->setData('value', '');
        $this->invokeMethod($this->model, '_afterLoad');

        $this->assertSame('', $this->model->getData('value'));
    }

    public function testAfterLoadWithNonArrayDeserializedValueReturnsEarly(): void
    {
        $this->serializerMock->expects($this->once())
            ->method('unserialize')
            ->willReturn('not-an-array');

        $this->model->setData('value', 'invalid');
        $this->invokeMethod($this->model, '_afterLoad');

        $this->assertSame('invalid', $this->model->getData('value'));
    }
}
