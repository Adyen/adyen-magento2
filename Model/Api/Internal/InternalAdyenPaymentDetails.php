<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api\Internal;

use Adyen\Payment\Api\AdyenPaymentDetailsInterface;
use Adyen\Payment\Api\Internal\InternalAdyenPaymentDetailsInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

class InternalAdyenPaymentDetails extends AbstractInternalApiController implements InternalAdyenPaymentDetailsInterface
{
    protected Http $request;

    protected Validator $formKeyValidator;

    protected AdyenPaymentDetailsInterface $adyenPaymentDetails;

    /**
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param AdyenPaymentDetailsInterface $adyenPaymentDetails
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        AdyenPaymentDetailsInterface $adyenPaymentDetails
    ) {
        parent::__construct($request, $formKeyValidator);
        $this->adyenPaymentDetails = $adyenPaymentDetails;
    }

    /**
     * @param string $payload
     * @param string $formKey
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function handleInternalRequest(string $payload, string $formKey): string
    {
        $this->validateInternalRequest($formKey);

        return $this->adyenPaymentDetails->initiate($payload);
    }
}
