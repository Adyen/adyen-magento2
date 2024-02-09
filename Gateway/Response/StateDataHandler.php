<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\ResourceModel\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Exception;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class StateDataHandler implements HandlerInterface
{
    private Collection $adyenStateData;
    private StateData $stateDataResourceModel;
    private AdyenLogger $adyenLogger;

    public function __construct(
        Collection $adyenStateData,
        StateData $stateDataResourceModel,
        AdyenLogger $adyenLogger
    ) {
        $this->adyenStateData = $adyenStateData;
        $this->stateDataResourceModel = $stateDataResourceModel;
        $this->adyenLogger = $adyenLogger;
    }

    public function handle(array $handlingSubject, array $response): self
    {
        $readPayment = SubjectReader::readPayment($handlingSubject);
        $quoteId = $readPayment->getPayment()->getOrder()->getQuoteId();

        $stateDataCollection = $this->adyenStateData->getStateDataRowsWithQuoteId($quoteId);

        foreach ($stateDataCollection->getIterator() as $stateDataItem) {
            try {
                $this->stateDataResourceModel->delete($stateDataItem);
            } catch (Exception $exception) {
                $this->adyenLogger->error(__("State data was not cleaned-up: %s", $exception->getMessage()));
            }
        }

        return $this;
    }
}
