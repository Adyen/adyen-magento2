<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class GiftcardDataBuilder implements BuilderInterface
{
    private StateDataCollection $adyenStateData;

    public function __construct(
        StateDataCollection $adyenStateData
    ) {
        $this->adyenStateData = $adyenStateData;
    }

    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();

        $request = [];

        $stateDataCollection = $this->adyenStateData->getStateDataRowsWithQuoteId($order->getQuoteId(), 'ASC');
        $stateDataArray = $this->validateGiftcardStateData($stateDataCollection->getData());

        if (!empty($stateDataArray)) {
            $request['body'] = [
                'giftcardRequestParameters' => $stateDataArray,
            ];
        }

        return $request;
    }

    private function validateGiftcardStateData(array $stateDataArray): array
    {
        foreach ($stateDataArray as $key => $item) {
            $stateData = json_decode($item['state_data'], true);
            if (!isset($stateData['paymentMethod']['type']) ||
                !isset($stateData['paymentMethod']['brand']) ||
                $stateData['paymentMethod']['type'] !== 'giftcard') {
                unset($stateDataArray[$key]);
            }
        }

        return $stateDataArray;
    }
}
