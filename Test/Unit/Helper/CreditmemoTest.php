<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Api\Data\CreditmemoInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Api\Repository\AdyenCreditmemoRepositoryInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Model\Creditmemo as AdyenCreditmemo;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\Order\Payment as AdyenOrderPayment;
use Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo as CreditmemoResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Model\Order\Creditmemo as MagentoCreditmemo;
use Magento\Sales\Model\Order\Payment as MagentoPayment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

#[AllowMockObjectsWithoutExpectations]
class CreditmemoTest extends AbstractAdyenTestCase
{
    private Creditmemo $refundScenarioHelper;

    /** @var array<string, int> */
    private array $adyenOrderPaymentIds = [];

    /** @var array<int, AdyenCreditmemo> */
    private array $storedRows = [];

    /** @var array<int, int> */
    private array $saveCounts = [];

    private ?MagentoCreditmemo $paymentCreditmemo = null;

    /** @var array<int, true> */
    private array $savedCreditmemos = [];

    private MagentoPayment $magentoPayment;

    public function testCreateAdyenCreditMemo()
    {
        $pspReference = '12A34B56C78D910E';
        $originalReference = 'E10D9C78B56A3412';
        $refundAmount = 10.00;
        $adyenOrderPaymentId = 5;

        $paymentMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Payment::class, [
            'getEntityId' => 5
        ]);

        $adyenOrderPaymentMock = $this->createConfiguredMock(OrderPaymentInterface::class, [
            'getEntityId' => $adyenOrderPaymentId
        ]);

        $orderPaymentResourceModel = $this->createConfiguredMock(Payment::class, [
            'getOrderPaymentDetails' => [OrderPaymentInterface::ENTITY_ID => $adyenOrderPaymentMock->getEntityId()]
        ]);

        $adyenCreditmemoMock = $this->createMock(\Adyen\Payment\Model\Creditmemo::class);
        $adyenCreditmemoFactoryMock = $this->createGeneratedMock(CreditmemoFactory::class, ['create']);
        $adyenCreditmemoFactoryMock->method('create')->willReturn($adyenCreditmemoMock);

        $adyenCreditmemoRepositoryMock = $this->createMock(AdyenCreditmemoRepositoryInterface::class);
        $adyenCreditmemoRepositoryMock->expects($this->once())
            ->method('save')
            ->with($adyenCreditmemoMock);

        $creditmemoHelper = $this->createCreditmemoHelper(
            null,
            null,
            $adyenCreditmemoFactoryMock,
            $orderPaymentResourceModel,
            $adyenCreditmemoRepositoryMock
        );

        $adyenCreditmemoMock->expects($this->once())->method('setPspreference')->with($pspReference);
        $adyenCreditmemoMock->expects($this->once())->method('setOriginalReference')->with($originalReference);
        $adyenCreditmemoMock->expects($this->once())->method('setAdyenPaymentOrderId')->with($adyenOrderPaymentId);
        $adyenCreditmemoMock->expects($this->once())->method('setAmount')->with($refundAmount);

        $result = $creditmemoHelper->createAdyenCreditMemo(
            $paymentMock,
            $pspReference,
            $originalReference,
            $refundAmount
        );

        $this->assertSame($adyenCreditmemoMock, $result);
    }

    public function testLinkAndUpdateAdyenCreditmemos()
    {
        $adyenOrderPaymentId = 5;
        $magentoCreditmemoId = 10;

        $adyenOrderPaymentMock = $this->createConfiguredMock(\Adyen\Payment\Model\Order\Payment::class, [
                'getEntityId' => $adyenOrderPaymentId
            ]
        );

        $adyenCreditmemoMock = $this->createConfiguredMock(CreditmemoInterface::class, [
            'getAmount' => 10.00
        ]);
        $adyenCreditmemoMock->expects($this->once())->method('setCreditmemoId')->with($magentoCreditmemoId);
        $adyenCreditmemoMock->expects($this->once())->method('setCreditmemoId')->with($magentoCreditmemoId);

        $adyenCreditmemos[] = $adyenCreditmemoMock;

        $adyenCreditmemoRepositoryMock = $this->createMock(AdyenCreditmemoRepositoryInterface::class);
        $adyenCreditmemoRepositoryMock->expects($this->once())
            ->method('getByAdyenOrderPaymentId')
            ->with($adyenOrderPaymentId)
            ->willReturn($adyenCreditmemos);
        $adyenCreditmemoRepositoryMock->expects($this->once())
            ->method('save')
            ->with($adyenCreditmemoMock);

        $magentoCreditmemoMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Creditmemo::class, [
            'getEntityId' => $magentoCreditmemoId,
            'getGrandTotal' => 10.00
        ]);

        $creditmemoHelper = $this->createCreditmemoHelper(
            null,
            null,
            null,
            null,
            $adyenCreditmemoRepositoryMock
        );

        $creditmemoHelper->linkAndUpdateAdyenCreditmemos($adyenOrderPaymentMock, $magentoCreditmemoMock);
    }

    public function testLinkAndUpdateAdyenCreditmemosLinksEveryRefundOfAPartialPaymentsCreditmemo()
    {
        $this->setUpRefundScenario(['GIFTCARD' => 1, 'CARD' => 2]);

        $firstCreditmemo = $this->createMagentoCreditmemo(101, 25.00);
        $this->refundCreditmemo($firstCreditmemo, [['GIFTCARD', 20.00], ['CARD', 5.00]]);

        $this->assertSame([1 => 101, 2 => 101], $this->linkedCreditmemoIds());

        $secondCreditmemo = $this->createMagentoCreditmemo(102, 20.80);
        $this->refundCreditmemo($secondCreditmemo, [['CARD', 20.80]]);

        $this->assertSame([1 => 101, 2 => 101, 3 => 102], $this->linkedCreditmemoIds());
        $this->assertSame([1 => 2, 2 => 2, 3 => 2], $this->saveCounts);
    }

    #[DataProvider('singleRefundDataProvider')]
    public function testLinkAndUpdateAdyenCreditmemosLinksASinglePaymentRefundOnce(
        float $refundAmount,
        float $grandTotal
    ) {
        $this->setUpRefundScenario(['CARD' => 1]);

        $creditmemo = $this->createMagentoCreditmemo(101, $grandTotal);
        $this->refundCreditmemo($creditmemo, [['CARD', $refundAmount]]);
        $this->linkCreditmemo($creditmemo);

        $this->assertSame([1 => 101], $this->linkedCreditmemoIds());
        $this->assertSame([1 => 2], $this->saveCounts);
    }

    public static function singleRefundDataProvider(): array
    {
        return [
            'full refund' => [45.80, 45.80],
            'grand total with float rounding error' => [20.80, 39.8 + 6 - 25],
        ];
    }

    public function testLinkAndUpdateAdyenCreditmemosIgnoresUnlinkedRefundsOfOtherCreditmemos()
    {
        $this->setUpRefundScenario(['CARD' => 1]);

        $this->createRefundWithoutCreditmemo('CARD', 25.00);

        $creditmemo = $this->createMagentoCreditmemo(101, 25.00);
        $this->refundCreditmemo($creditmemo, [['CARD', 25.00]]);

        $this->assertSame([1 => null, 2 => 101], $this->linkedCreditmemoIds());
        $this->assertSame([1 => 1, 2 => 2], $this->saveCounts);
    }

    public function testLinkAndUpdateAdyenCreditmemosLinksAWebhookRefundByAmount()
    {
        $this->setUpRefundScenario(['CARD' => 1]);

        $this->createRefundWithoutCreditmemo('CARD', 45.80);
        $this->linkCreditmemo($this->createMagentoCreditmemo(101, 45.80));

        $this->assertSame([1 => 101], $this->linkedCreditmemoIds());
        $this->assertSame([1 => 2], $this->saveCounts);
    }

    public function testLinkAndUpdateAdyenCreditmemosIgnoresWebhookRefundsCreatedWhileASavedCreditmemoIsOnThePayment()
    {
        $this->setUpRefundScenario(['CARD' => 1]);

        $firstCreditmemo = $this->createMagentoCreditmemo(101, 25.00);
        $this->refundCreditmemo($firstCreditmemo, [['CARD', 25.00]]);

        $this->createRefundWithoutCreditmemo('CARD', 20.80, $firstCreditmemo);
        $this->linkCreditmemo($firstCreditmemo);

        $this->assertSame([1 => 101, 2 => null], $this->linkedCreditmemoIds());

        $this->linkCreditmemo($this->createMagentoCreditmemo(102, 20.80));

        $this->assertSame([1 => 101, 2 => 102], $this->linkedCreditmemoIds());
        $this->assertSame([1 => 2, 2 => 2], $this->saveCounts);
    }

    private function setUpRefundScenario(array $adyenOrderPaymentIds): void
    {
        $this->adyenOrderPaymentIds = $adyenOrderPaymentIds;
        $this->storedRows = [];
        $this->saveCounts = [];
        $this->paymentCreditmemo = null;
        $this->savedCreditmemos = [];

        $this->magentoPayment = $this->createMock(MagentoPayment::class);
        $this->magentoPayment->method('getEntityId')->willReturn(500);
        $this->magentoPayment->method('getCreditmemo')->willReturnCallback(fn () => $this->paymentCreditmemo);

        $orderPaymentResourceModel = $this->createMock(Payment::class);
        $orderPaymentResourceModel->method('getOrderPaymentDetails')->willReturnCallback(
            fn (string $originalReference) => [
                OrderPaymentInterface::ENTITY_ID => $this->adyenOrderPaymentIds[$originalReference]
            ]
        );

        $adyenCreditmemoFactory = $this->createGeneratedMock(CreditmemoFactory::class, ['create']);
        $adyenCreditmemoFactory->method('create')->willReturnCallback(fn () => $this->createAdyenCreditmemoRow());

        $repository = $this->createMock(AdyenCreditmemoRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (AdyenCreditmemo $row) {
            if ($row->getEntityId() === null) {
                $row->setEntityId(count($this->storedRows) + 1);
            }
            $entityId = (int)$row->getEntityId();
            $this->storedRows[$entityId] = clone $row;
            $this->saveCounts[$entityId] = ($this->saveCounts[$entityId] ?? 0) + 1;

            return $row;
        });
        $repository->method('getByAdyenOrderPaymentId')->willReturnCallback(
            fn (int $adyenOrderPaymentId) => array_values(array_map(
                fn (AdyenCreditmemo $row) => clone $row,
                array_filter(
                    $this->storedRows,
                    fn (AdyenCreditmemo $row) => $row->getAdyenPaymentOrderId() === $adyenOrderPaymentId
                )
            ))
        );

        $this->refundScenarioHelper = $this->createCreditmemoHelper(
            null,
            null,
            $adyenCreditmemoFactory,
            $orderPaymentResourceModel,
            $repository
        );
    }

    private function createAdyenCreditmemoRow(): AdyenCreditmemo
    {
        return $this->getMockBuilder(AdyenCreditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function createMagentoCreditmemo(int $entityId, float $grandTotal): MagentoCreditmemo
    {
        $creditmemo = $this->createPartialMock(MagentoCreditmemo::class, ['getEntityId', 'getGrandTotal']);
        $creditmemo->method('getEntityId')->willReturnCallback(
            fn () => isset($this->savedCreditmemos[spl_object_id($creditmemo)]) ? $entityId : null
        );
        $creditmemo->method('getGrandTotal')->willReturn($grandTotal);

        return $creditmemo;
    }

    private function refundCreditmemo(MagentoCreditmemo $creditmemo, array $refunds): void
    {
        $this->paymentCreditmemo = $creditmemo;
        foreach ($refunds as $index => [$originalReference, $amount]) {
            $this->refundScenarioHelper->createAdyenCreditMemo(
                $this->magentoPayment,
                'REFUND' . count($this->storedRows) . $index,
                $originalReference,
                $amount
            );
        }
        $this->paymentCreditmemo = null;

        $this->linkCreditmemo($creditmemo);
    }

    private function createRefundWithoutCreditmemo(
        string $originalReference,
        float $amount,
        ?MagentoCreditmemo $paymentCreditmemo = null
    ): void {
        $this->paymentCreditmemo = $paymentCreditmemo;
        $this->refundScenarioHelper->createAdyenCreditMemo(
            $this->magentoPayment,
            'WEBHOOK' . count($this->storedRows),
            $originalReference,
            $amount
        );
        $this->paymentCreditmemo = null;
    }

    private function linkCreditmemo(MagentoCreditmemo $creditmemo): void
    {
        $this->savedCreditmemos[spl_object_id($creditmemo)] = true;
        foreach ($this->adyenOrderPaymentIds as $adyenOrderPaymentId) {
            $adyenOrderPayment = $this->createConfiguredMock(AdyenOrderPayment::class, [
                'getEntityId' => $adyenOrderPaymentId
            ]);
            $this->refundScenarioHelper->linkAndUpdateAdyenCreditmemos($adyenOrderPayment, $creditmemo);
        }
    }

    private function linkedCreditmemoIds(): array
    {
        return array_map(fn (AdyenCreditmemo $row) => $row->getCreditmemoId(), $this->storedRows);
    }

    public function testSaveMethodIsCalledCorrectNumberOfTimes()
    {
        $adyenCreditmemoResourceModelMock = $this->createMock(
            CreditmemoResourceModel::class
        );
        $magentoCreditmemoMock = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $magentoCreditmemoId = 1;
        $adyenCreditmemos = [
            ['data' => 'creditmemo1'],
            ['data' => 'creditmemo2'],
            ['data' => 'creditmemo3']
        ];

        $adyenCreditmemoResourceModelMock->expects($this->exactly(count($adyenCreditmemos)))
            ->method('save')
            ->willReturn($this);

        $magentoCreditmemoMock->method('getEntityId')
            ->willReturn($magentoCreditmemoId);

        foreach ($adyenCreditmemos as $adyenCreditmemo) {
            $currAdyenCreditmemoMock = $this->createMock(AbstractModel::class);
            $currAdyenCreditmemoMock->setData($adyenCreditmemo);
            $currAdyenCreditmemoMock->setEntityId($magentoCreditmemoMock->getEntityId());
            $currAdyenCreditmemoMock->setCreditmemoId($magentoCreditmemoId);
            $adyenCreditmemoResourceModelMock->save($currAdyenCreditmemoMock);
        }
    }

    /**
     * @return void
     */
    public function testUpdateAdyenCreditmemosStatus()
    {
        $adyenCreditmemoMock = $this->createMock(CreditmemoInterface::class);
        $status = 'refunded';

        $adyenCreditmemoMock->expects($this->once())
            ->method('setStatus')
            ->with($status);

        $adyenCreditmemoRepositoryMock = $this->createMock(AdyenCreditmemoRepositoryInterface::class);
        $adyenCreditmemoRepositoryMock->expects($this->once())
            ->method('save')
            ->with($adyenCreditmemoMock);

        $creditmemoHelper = $this->createCreditmemoHelper(
            null,
            null,
            null,
            null,
            $adyenCreditmemoRepositoryMock
        );

        $creditmemoHelper->updateAdyenCreditmemosStatus($adyenCreditmemoMock, $status);
    }

    /**
     * @param null $context
     * @param null $adyenDataHelper
     * @param null $adyenCreditmemoFactory
     * @param null $orderPaymentResourceModel
     * @param null $adyenCreditmemoRepositoryMock
     * @return Creditmemo
     * @throws Exception
     */
    protected function createCreditmemoHelper(
        $context = null,
        $adyenDataHelper = null,
        $adyenCreditmemoFactory = null,
        $orderPaymentResourceModel = null,
        $adyenCreditmemoRepositoryMock = null
    ): Creditmemo {
        if (is_null($context)) {
            $context = $this->createMock(Context::class);
        }

        if (is_null($adyenDataHelper)) {
            $adyenDataHelper = $this->createMock(Data::class);
        }

        if (is_null($adyenCreditmemoFactory)) {
            $adyenCreditmemoFactory = $this->createGeneratedMock(CreditmemoFactory::class);
        }

        if (is_null($orderPaymentResourceModel)) {
            $orderPaymentResourceModel = $this->createMock(Payment::class);
        }

        if (is_null($adyenCreditmemoRepositoryMock)) {
            $adyenCreditmemoRepositoryMock = $this->createMock(
                AdyenCreditmemoRepositoryInterface::class
            );
        }

        return new Creditmemo(
            $context,
            $adyenDataHelper,
            $adyenCreditmemoFactory,
            $orderPaymentResourceModel,
            $adyenCreditmemoRepositoryMock
        );
    }
}
