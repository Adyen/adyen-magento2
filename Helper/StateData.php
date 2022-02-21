<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Model\ResourceModel\StateData as StateDataResourceModel;
use Adyen\Payment\Model\ResourceModel\StateData\Collection as StateDataCollection;
use Adyen\Payment\Model\StateData as StateDataModel;
use Adyen\Payment\Model\StateDataFactory;

class StateData
{
    const CLEANUP_RESULT_CODES = array(
        "Authorised"
    );

    /**
     * @var array Temporary (per request) storage of state data
     */
    private $stateData = [];

    /**
     * @var StateDataCollection
     */
    private $stateDataCollection;

    /**
     * @var StateDataFactory
     */
    private $stateDataFactory;

    /**
     * @var StateDataResourceModel
     */
    private $stateDataResourceModel;

    public function __construct(
        StateDataCollection $stateDataCollection,
        StateDataFactory $stateDataFactory,
        StateDataResourceModel $stateDataResourceModel
    ) {
        $this->stateDataCollection = $stateDataCollection;
        $this->stateDataFactory = $stateDataFactory;
        $this->stateDataResourceModel = $stateDataResourceModel;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @param $resultCode
     * @throws \Exception
     */
    public function CleanQuoteStateData($quoteId, $resultCode)
    {
        if (in_array($resultCode, self::CLEANUP_RESULT_CODES)) {
            $rows = $this->stateDataCollection->getStateDataRowsWithQuoteId($quoteId)->getItems();
            foreach ($rows as $row) {
                $this->deleteStateData($row->getData('entity_id'));
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function deleteStateData($entityId)
    {
        /** @var StateDataModel $stateData */
        $stateData = $this->stateDataFactory->create()->load($entityId);
        $this->stateDataResourceModel->delete($stateData);
    }

    public function setStateData(array $stateData, int $quoteId)
    {
        $this->stateData[$quoteId] = $stateData;
    }

    public function getStateData(int $quoteId): array
    {
        return $this->stateData[$quoteId] ?? [];
    }
}
