<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api\Internal;

use Adyen\Payment\Api\Internal\InternalAdyenDonationsInterface;
use Adyen\Payment\Model\Api\AdyenDonations;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

class InternalAdyenDonations extends AbstractInternalApiController implements InternalAdyenDonationsInterface
{
    private AdyenDonations $adyenDonations;

    /**
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param AdyenDonations $adyenDonations
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        AdyenDonations $adyenDonations
    ) {
        parent::__construct($request, $formKeyValidator);
        $this->adyenDonations = $adyenDonations;
    }

    /**
     * @param string $payload
     * @param string $formKey
     * @return void
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handleInternalRequest(string $payload, string $formKey): void
    {
        $this->validateInternalRequest($formKey);
        $this->adyenDonations->donate($payload);
    }
}
