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

use Adyen\Payment\Helper\StateData as StateDataHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Logger\Handler\AdyenCronjob;
use Adyen\Payment\Model\ResourceModel\StateData;
use Adyen\Payment\Model\ResourceModel\StateData\Collection;
use Magento\Quote\Model\ResourceModel\Quote\Payment as QuotePaymentResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;

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
     * @var StateDataHelper
     */
    private $stateDataHelper;
    /**
     * @var QuotePaymentResourceModel
     */
    private $quotePaymentResourceModel;
    /**
     * @var OrderPaymentResourceModel
     */
    private $orderPaymentResourceModel;

    /**
     * StateDataCleanUp constructor.
     * @param Collection $stateDataCollection
     * @param StateData $stateDataResourceModel
     * @param AdyenLogger $adyenLogger
     */
    public function __construct(
        Collection $stateDataCollection,
        StateData $stateDataResourceModel,
        AdyenLogger $adyenLogger,
        StateDataHelper $stateDataHelper,
        QuotePaymentResourceModel $quotePaymentResourceModel,
        OrderPaymentResourceModel $orderPaymentResourceModel
    ) {
        $this->stateDataCollection = $stateDataCollection;
        $this->stateDataResourceModel = $stateDataResourceModel;
        $this->adyenLogger = $adyenLogger;
        $this->stateDataHelper = $stateDataHelper;
        $this->quotePaymentResourceModel = $quotePaymentResourceModel;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
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

        $expiredQuoteRows = $this->stateDataHelper->getQuotePaymentsWithStateData();
        foreach ($expiredQuoteRows as $expiredQuoteRow) {
            $expiredQuoteRow->unsAdditionalInformation(StateDataHelper::STATE_DATA_KEY);
            $this->quotePaymentResourceModel->save($expiredQuoteRow);
        }

        $expiredOrderRows = $this->stateDataHelper->getSalesOrderPaymentsWithStateData();
        foreach ($expiredOrderRows as $expiredOrderRow) {
            $expiredOrderRow->unsAdditionalInformation(StateDataHelper::STATE_DATA_KEY);
            $this->orderPaymentResourceModel->save($expiredOrderRow);
        }
    }
}
