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

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Api\Repository\AdyenCreditmemoRepositoryInterface;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Model\AbstractModel;

class CreditmemoTest extends AbstractAdyenTestCase
{
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

        $adyenCreditmemoResourceModel = $this->createMock(
            \Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class
        );

        $creditmemoHelper = $this->createCreditmemoHelper(
            null,
            null,
            $adyenCreditmemoFactoryMock,
            $adyenCreditmemoResourceModel,
            $orderPaymentResourceModel
        );

        $adyenCreditmemoMock->expects($this->once())->method('setPspreference')->with($pspReference);
        $adyenCreditmemoMock->expects($this->once())->method('setOriginalReference')->with($originalReference);
        $adyenCreditmemoMock->expects($this->once())->method('setAdyenPaymentOrderId')->with($adyenOrderPaymentId);
        $adyenCreditmemoMock->expects($this->once())->method('setAmount')->with($refundAmount);
        $adyenCreditmemoResourceModel->expects($this->once())->method('save')->with($adyenCreditmemoMock);

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

        $adyenCreditmemos = [
            [
                'entity_id' => 1,
                'creditmemo_id' => 00001,
                'adyen_payment_order_id' => 5
            ],
            [
                'entity_id' => 2,
                'creditmemo_id' => 00002,
                'adyen_payment_order_id' => 5
            ]
        ];

        $adyenCreditmemoResourceModelMock = $this->createConfiguredMock(
            \Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class,
            [
                'getAdyenCreditmemosByAdyenPaymentid' => $adyenCreditmemos,
                'save' => $this->createPartialMock(
                    \Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class,
                    ['save']
                )
            ]
        );

        $adyenCreditmemoResourceModelMock->expects($this->atLeastOnce())
            ->method('save')
            ->willReturn(true);

        $magentoCreditmemoMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Creditmemo::class, [
            'getEntityId' => $magentoCreditmemoId
        ]);

        $adyenCreditmemoMock = $this->createMock(\Adyen\Payment\Model\Creditmemo::class);

        $adyenCreditmemoLoaderMock = $this->getMockBuilder(\Adyen\Payment\Model\Creditmemo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $adyenCreditmemoLoaderMock->method('load')->willReturn($adyenCreditmemoMock);

        $adyenCreditmemoFactoryMock = $this->createGeneratedMock(CreditmemoFactory::class, ['create']);
        $adyenCreditmemoFactoryMock->method('create')->willReturn($adyenCreditmemoLoaderMock);

        $adyenCreditmemoResourceModelMock->expects($this->once())
            ->method('getAdyenCreditmemosByAdyenPaymentid')
            ->with($adyenOrderPaymentId)
            ->willReturn($adyenCreditmemos);

        foreach ($adyenCreditmemos as $adyenCreditmemo) {
            $currAdyenCreditmemoMock = $this->createPartialMock(AbstractModel::class, []);
            $currAdyenCreditmemoMock->setData($adyenCreditmemo);
            $currAdyenCreditmemoMock->setEntityId($magentoCreditmemoMock->getEntityId());
            $currAdyenCreditmemoMock->setCreditmemoId($magentoCreditmemoMock->getEntityId());
            $adyenCreditmemoResourceModelMock->save($currAdyenCreditmemoMock);
        }

        $creditmemoHelper = $this->createCreditmemoHelper(
            null,
            null,
            $adyenCreditmemoFactoryMock,
            $adyenCreditmemoResourceModelMock
        );

        $creditmemoHelper->linkAndUpdateAdyenCreditmemos($adyenOrderPaymentMock, $magentoCreditmemoMock);
    }

    public function testSaveMethodIsCalledCorrectNumberOfTimes()
    {
        $adyenCreditmemoResourceModelMock = $this->createMock(
            \Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class
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
     * @param null $context
     * @param null $adyenDataHelper
     * @param null $adyenCreditmemoFactory
     * @param null $adyenCreditmemoResourceModel
     * @param null $orderPaymentResourceModel
     * @return Creditmemo
     */
    protected function createCreditmemoHelper(
        $context = null,
        $adyenDataHelper = null,
        $adyenCreditmemoFactory = null,
        $adyenCreditmemoResourceModel = null,
        $orderPaymentResourceModel = null,
        $adyenCreditmemoRepositoryMock = null
    ): Creditmemo
    {
        if (is_null($context)) {
            $context = $this->createMock(Context::class);
        }

        if (is_null($adyenDataHelper)) {
            $adyenDataHelper = $this->createMock(Data::class);
        }

        if (is_null($adyenCreditmemoFactory)) {
            $adyenCreditmemoFactory = $this->createGeneratedMock(CreditmemoFactory::class);
        }

        if (is_null($adyenCreditmemoResourceModel)) {
            $adyenCreditmemoResourceModel = $this->createMock(
                \Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class
            );
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
            $adyenCreditmemoResourceModel,
            $orderPaymentResourceModel,
            $adyenCreditmemoRepositoryMock
        );
    }
}
