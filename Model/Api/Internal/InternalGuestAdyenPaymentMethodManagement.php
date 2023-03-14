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

use Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Api\Internal\InternalGuestAdyenPaymentMethodManagementInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;

class InternalGuestAdyenPaymentMethodManagement extends AbstractInternalApiController implements InternalGuestAdyenPaymentMethodManagementInterface
{
    protected Http $request;

    protected Validator $formKeyValidator;

    protected GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement;

    /**
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement
    ) {
        parent::__construct($request, $formKeyValidator);
        $this->guestAdyenPaymentMethodManagement = $guestAdyenPaymentMethodManagement;
    }

    /**
     * @param string $cartId
     * @param string $formKey
     * @return string
     * @throws \Adyen\AdyenException
     */
    public function handleInternalRequest(string $cartId, string $formKey,): string {
        $this->validateInternalRequest($formKey);

        return $this->guestAdyenPaymentMethodManagement->getPaymentMethods($cartId);
    }
}
