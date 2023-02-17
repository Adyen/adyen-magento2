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

use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Api\Internal\InternalAdyenPaymentMethodManagementInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;

class InternalAdyenPaymentMethodManagement extends AbstractInternalApiController implements InternalAdyenPaymentMethodManagementInterface
{
    protected Http $request;

    protected Validator $formKeyValidator;

    protected AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement;

    /**
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        AdyenPaymentMethodManagementInterface $adyenPaymentMethodManagement
    ) {
        parent::__construct($request, $formKeyValidator);
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
    }

    /**
     * @param string $cartId
     * @param string $formKey
     * @param AddressInterface|null $shippingAddress
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function handleInternalRequest(
        string $cartId,
        string $formKey,
        AddressInterface $billingAddress = null
    ): string {
        $this->validateInternalRequest($formKey);

        return $this->adyenPaymentMethodManagement->getPaymentMethods($cartId, $billingAddress);
    }
}
