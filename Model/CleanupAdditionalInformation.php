<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2025 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Exception;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CleanupAdditionalInformation implements CleanupAdditionalInformationInterface
{
    /**
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * This method cleans-up the unnecessary fields in `additional_information` of OrderPayment object.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return OrderPaymentInterface
     */
    public function execute(OrderPaymentInterface $orderPayment): OrderPaymentInterface
    {
        try {
            foreach (self::FIELDS_TO_BE_CLEANED_UP as $field) {
                $orderPayment->unsAdditionalInformation($field);
            }
        } catch (Exception $e) {
            $this->adyenLogger->error(
                sprintf(
                    "An error occurred while trying to cleanup additional information: %s",
                    $e->getMessage()
                )
            );
        }

        return $orderPayment;
    }
}
