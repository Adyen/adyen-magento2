<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Exception;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CheckoutStateDataCleanupHandler implements HandlerInterface
{
    /**
     * @param StateDataCollection $adyenStateDataCollection
     * @param StateData $stateDataResourceModel
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        private readonly StateDataCollection $adyenStateDataCollection,
        private readonly StateData $stateDataResourceModel,
        private readonly AdyenLogger $adyenLogger
    ) { }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $readPayment = SubjectReader::readPayment($handlingSubject);
        $quoteId = $readPayment->getPayment()->getOrder()->getQuoteId();

        $stateDataCollection = $this->adyenStateDataCollection->getStateDataRowsWithQuoteId($quoteId);

        foreach ($stateDataCollection as $stateDataItem) {
            try {
                $this->stateDataResourceModel->delete($stateDataItem);
            } catch (Exception $exception) {
                $this->adyenLogger->error(__("State data was not cleaned-up: %s", $exception->getMessage()));
            }
        }
    }
}
