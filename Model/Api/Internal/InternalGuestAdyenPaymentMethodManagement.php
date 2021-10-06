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
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api\Internal;

use Adyen\AdyenException;
use Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Api\Internal\InternalGuestAdyenPaymentMethodManagementInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class InternalGuestAdyenPaymentMethodManagement
 */
class InternalGuestAdyenPaymentMethodManagement extends AbstractInternalApiController implements InternalGuestAdyenPaymentMethodManagementInterface
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Validator
     */
    protected $formKeyValidator;

    /**
     * @var GuestAdyenPaymentMethodManagementInterface
     */
    protected $guestAdyenPaymentMethodManagement;

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
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($cartId, $formKey, AddressInterface $shippingAddress = null)
    {
        $this->validateInternalRequest($formKey);

        return $this->guestAdyenPaymentMethodManagement->getPaymentMethods($cartId, $shippingAddress);
    }
}
