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

use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Creditmemo;
use Adyen\Payment\Model\CreditmemoFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;

class CreditMemoTest extends AbstractAdyenTestCase
{
    /**
     * @param null $context
     * @param null $adyenDataHelper
     * @param null $adyenCreditmemoFactory
     * @param null $adyenCreditmemoResourceModel
     * @param null $orderPaymentResourceModel
     * @return CreditMemo
     */
    public function createCreditMemoHelper(
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
            $adyenCreditmemoFactory = $this->createMock(CreditmemoFactory::class);
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
