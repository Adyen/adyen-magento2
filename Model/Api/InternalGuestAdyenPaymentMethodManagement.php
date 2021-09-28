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

namespace Adyen\Payment\Model\Api;

use Adyen\AdyenException;
use Adyen\Payment\Api\GuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Api\InternalGuestAdyenPaymentMethodManagementInterface;
use Adyen\Payment\Helper\PaymentMethods;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Encryption\Helper\Security;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * Class InternalGuestAdyenPaymentMethodManagement
 * @package Adyen\Payment\Model\Api
 */
class InternalGuestAdyenPaymentMethodManagement implements InternalGuestAdyenPaymentMethodManagementInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var PaymentMethods
     */
    protected $paymentMethodsHelper;

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
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param PaymentMethods $paymentMethodsHelper
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentMethods $paymentMethodsHelper,
        Http $request,
        Validator $formKeyValidator,
        GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->guestAdyenPaymentMethodManagement = $guestAdyenPaymentMethodManagement;
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

        return $this->guestAdyenPaymentMethodManagement->getPaymentMethods($cartId, $shippingAddress);
    }
}
