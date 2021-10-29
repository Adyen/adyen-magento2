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

namespace Adyen\Payment\Gateway\Validator;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class DonateResponseValidator extends AbstractValidator
{
    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    public function __construct(ResultInterfaceFactory $resultInterfaceFactory, AdyenLogger $adyenLogger)
    {
        parent::__construct($resultInterfaceFactory);
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $response = SubjectReader::readResponse($validationSubject);

        if (empty($response['resultCode'])) {
            $errorMsg = __('An error occurred with the donation.');

            if (!empty($response['error'])) {
                $this->adyenLogger->error($response['error']);
            }

            throw new LocalizedException(__($errorMsg));
        }

        return $this->createResult(true);
    }
}
