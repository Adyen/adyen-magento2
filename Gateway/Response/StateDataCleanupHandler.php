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
 * Copyright (c) 2021 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Response;

use Adyen\Payment\Gateway\Data\Order\OrderAdapter;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class StateDataCleanupHandler implements HandlerInterface
{
    /**
     * @var StateData
     */
    private $stateDataHelper;

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    public function __construct(
        StateData $stateDataHelper,
        AdyenLogger $adyenLogger
    ) {
        $this->stateDataHelper = $stateDataHelper;
        $this->adyenLogger = $adyenLogger;
    }

    public function handle(array $handlingSubject, array $response)
    {
        if (!empty($response['resultCode'])) {
            $paymentDataObject = SubjectReader::readPayment($handlingSubject);
            $orderAdapter = $paymentDataObject->getOrder();
            if ($orderAdapter instanceof OrderAdapter) {
                $this->stateDataHelper->cleanQuoteStateData($paymentDataObject->getOrder()->getQuoteId(), $response['resultCode']);
            } else {
                $this->adyenLogger->warning(sprintf(
                    'Unexpected OrderAdapter class received: %s. State data will not be deleted', get_class($orderAdapter)
                ));
            }
        }
    }
}
