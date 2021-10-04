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
use Adyen\Payment\Api\AdyenPaymentDetailsInterface;
use Adyen\Payment\Api\Internal\InternalAdyenPaymentDetailsInterface;
use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * Class InternalAdyenPaymentDetailsInterface
 */
class InternalAdyenPaymentDetails implements InternalAdyenPaymentDetailsInterface
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
     * @var AdyenPaymentDetailsInterface
     */
    protected $adyenPaymentDetails;

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
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->adyenPaymentDetails = $adyenPaymentDetails;
    }

    /**
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($payload, $formKey)
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

        return $this->adyenPaymentDetails->initiate($payload);
    }
}
