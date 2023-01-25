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
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;

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
            'getEntityId' => [OrderPaymentInterface::ENTITY_ID => $adyenOrderPaymentId]
        ]);

        $orderPaymentResourceModel = $this->createConfiguredMock(Payment::class, [
            'getOrderPaymentDetails' => $adyenOrderPaymentMock->getEntityId()
        ]);

        $orderPaymentResourceModel->method('getOrderPaymentDetails')
            ->with($originalReference, $paymentMock->getEntityId())
            ->willReturn($adyenOrderPaymentMock->getEntityId());

        $adyenCreditmemoMock = $this->createMock(\Adyen\Payment\Model\Creditmemo::class);
        $adyenCreditmemoFactoryMock = $this->createGeneratedMock(CreditmemoFactory::class, ['create']);
        $adyenCreditmemoFactoryMock->method('create')->willReturn($adyenCreditmemoMock);

        $adyenCreditmemoResourceModel = $this->createMock(\Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class);

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

        $result = $creditmemoHelper->createAdyenCreditMemo($paymentMock, $pspReference, $originalReference, $refundAmount);

        $this->assertSame($adyenCreditmemoMock, $result);
    }

    /**
     * @param null $context
     * @param null $adyenDataHelper
     * @param null $adyenCreditmemoFactory
     * @param null $adyenCreditmemoResourceModel
     * @param null $orderPaymentResourceModel
     * @return Creditmemo
     */
    public function createCreditmemoHelper(
        $context = null,
        $adyenDataHelper = null,
        $adyenCreditmemoFactory = null,
        $adyenCreditmemoResourceModel = null,
        $orderPaymentResourceModel = null
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
            $adyenCreditmemoResourceModel = $this->createMock(\Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo::class);
        }

        if (is_null($orderPaymentResourceModel)) {
            $orderPaymentResourceModel = $this->createMock(Payment::class);
        }

        return new Creditmemo(
            $context,
            $adyenDataHelper,
            $adyenCreditmemoFactory,
            $adyenCreditmemoResourceModel,
            $orderPaymentResourceModel
        );
    }
}
