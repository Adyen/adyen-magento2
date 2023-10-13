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

namespace Adyen\Payment\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenPosCloudDataAssignObserver extends AbstractDataAssignObserver
{
    const TERMINAL_ID = 'terminal_id';
    const NUMBER_OF_INSTALLMENTS = 'number_of_installments';
    const FUNDING_SOURCE = 'funding_source';

    protected array $additionalInformationList = [
        self::TERMINAL_ID,
        self::NUMBER_OF_INSTALLMENTS,
        self::FUNDING_SOURCE
    ];

    public function execute(Observer $observer): void
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        // Remove cc_type information from the previous payment
        $paymentInfo->unsAdditionalInformation('cc_type');

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (array_key_exists($additionalInformationKey, $additionalData)) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
