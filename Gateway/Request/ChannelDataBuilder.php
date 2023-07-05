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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\StateData;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class ChannelDataBuilder implements BuilderInterface
{
    /** @var StateData */
    private $stateDataHelper;

    public function __construct(StateData $stateDataHelper)
    {
        $this->stateDataHelper = $stateDataHelper;
    }

    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();

        $stateData = $this->stateDataHelper->getStateData($order->getQuoteId());
        $request['body']['channel'] = array_key_exists('channel', $stateData) ? $stateData['channel'] : 'web';

        return $request;
    }
}
