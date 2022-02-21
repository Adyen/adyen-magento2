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
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Cron;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Logger\Handler\AdyenCronjob;
use Adyen\Payment\Model\ResourceModel\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;

class StateDataCleanUp
{
    /**
     * @var Collection
     */
    private $stateDataCollection;

    /**
     * @var StateData
     */
    private $stateDataResourceModel;

    /**
     * @var AdyenCronjob
     */
    private $adyenLogger;

    /**
     * StateDataCleanUp constructor.
     * @param Collection $stateDataCollection
     * @param StateData $stateDataResourceModel
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Collection $stateDataCollection,
        StateData $stateDataResourceModel,
        AdyenLogger $adyenLogger
    ) {
        $this->stateDataCollection = $stateDataCollection;
        $this->stateDataResourceModel = $stateDataResourceModel;
        $this->adyenLogger = $adyenLogger;
    }

    public function execute()
    {
        $expiredStateDataRows = $this->stateDataCollection->getExpiredStateDataRows();
        foreach ($expiredStateDataRows->getIterator() as $expiredStateDataRow) {
            try {
                $this->stateDataResourceModel->delete($expiredStateDataRow);
            } catch (\Exception $exception) {
                $this->adyenLogger->addError(__("State data was not cleaned-up: %s", $exception->getMessage()));
            }
        }
    }
}
