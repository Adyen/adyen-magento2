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

use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Api\Internal\InternalAdyenOrderPaymentStatusInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

class InternalAdyenOrderPaymentStatus extends AbstractInternalApiController implements InternalAdyenOrderPaymentStatusInterface
{
    protected Http $request;

    protected Validator $formKeyValidator;

    protected AdyenOrderPaymentStatusInterface $adyenOrderPaymentStatus;

    /**
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param AdyenOrderPaymentStatusInterface $adyenOrderPaymentStatus
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        AdyenOrderPaymentStatusInterface $adyenOrderPaymentStatus
    ) {
        parent::__construct($request, $formKeyValidator);
        $this->adyenOrderPaymentStatus = $adyenOrderPaymentStatus;
    }

    /**
     * @param string $orderId
     * @param string $formKey
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function handleInternalRequest(string $orderId, string $formKey): string
    {
        $this->validateInternalRequest($formKey);

        return $this->adyenOrderPaymentStatus->getOrderPaymentStatus($orderId);
    }
}
