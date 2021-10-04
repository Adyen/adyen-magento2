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
use Adyen\Payment\Api\AdyenPaymentMethodManagementInterface;
use Adyen\Payment\Api\Internal\InternalAdyenPaymentMethodManagementInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class InternalAdyenPaymentMethodManagement
 */
class InternalAdyenPaymentMethodManagement implements InternalAdyenPaymentMethodManagementInterface
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
     * @var AdyenPaymentMethodManagementInterface
     */
    protected $adyenPaymentMethodManagement;

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
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->adyenPaymentMethodManagement = $adyenPaymentMethodManagement;
    }

    /**
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($cartId, $formKey, AddressInterface $shippingAddress = null)
    {
        $isAjax = $this->request->isAjax();
        // Post value has to be manually set since it will have no post data when this function is accessed
        $formKeyValid = $this->formKeyValidator->validate($this->request->setPostValue('form_key', $formKey));

        if (!$isAjax || !$formKeyValid) {
            throw new AdyenException(
                'Unable to access InternalGuestAdyenPaymentMethodManagement. Request is not AJAX or invalid CSRF token',
                401
            );
        }

        return $this->adyenPaymentMethodManagement->getPaymentMethods($cartId, $shippingAddress);
    }
}
