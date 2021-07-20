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

namespace Adyen\Payment\Model;

use Adyen\Payment\Api\AdyenStateDataInterface;
use Adyen\Payment\Api\Data\StateDataInterface;
use Adyen\Service\Validator\CheckoutStateDataValidator;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;

class AdyenStateData implements AdyenStateDataInterface
{

    /**
     * @var CheckoutStateDataValidator
     */
    private $checkoutStateDataValidator;

    /**
     * @var StateDataFactory
     */
    private $stateDataFactory;

    /**
     * @var ResourceModel\StateData
     */
    private $stateDataResourceModel;

    public function __construct(
        CheckoutStateDataValidator $checkoutStateDataValidator,
        StateDataFactory $stateDataFactory,
        ResourceModel\StateData $stateDataResourceModel
    ) {
        $this->checkoutStateDataValidator = $checkoutStateDataValidator;
        $this->stateDataFactory = $stateDataFactory;
        $this->stateDataResourceModel = $stateDataResourceModel;
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function save($stateData, $quoteId)
    {
        // Decode payload from frontend
        $stateData = json_decode($stateData, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(__('State data call failed because the request was not a valid JSON'));
        }

        $stateData = json_encode($this->checkoutStateDataValidator->getValidatedAdditionalData($stateData));

        /** @var StateData $stateDataObj */
        $stateDataObj = $this->stateDataFactory->create();
        $stateDataObj->setQuoteId((int)$quoteId)->setStateData((string)$stateData);
        $this->stateDataResourceModel->save($stateDataObj);
    }
}
