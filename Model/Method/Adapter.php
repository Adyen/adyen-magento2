<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Method;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method;
use Magento\Quote\Api\Data\PaymentInterface;

class Adapter extends Method\Adapter
{
    public function assignData(DataObject $data): Adapter
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->addData(
            [
                'cc_number' => $additionalData->getCcNumber(),
            ]
        );
        return parent::assignData($data);
    }
}
