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

use Adyen\Payment\Helper\StateData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class StateDataCleanupHandler implements HandlerInterface
{
    /**
     * @var StateData
     */
    private $stateDataHelper;

    public function __construct(
        StateData $stateDataHelper
    ) {
        $this->stateDataHelper = $stateDataHelper;
    }

    public function handle(array $handlingSubject, array $response)
    {
        if (!empty($response['resultCode'])) {
            $paymentDataObject = SubjectReader::readPayment($handlingSubject);
            $this->stateDataHelper->cleanQuoteStateData($paymentDataObject->getOrder()->getQuoteId(), $response['resultCode']);
        }
    }
}
