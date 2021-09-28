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
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;

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
    protected $httpRequest;

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
     * @param Http $httpRequest
     * @param Validator $formKeyValidator
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentMethods $paymentMethodsHelper,
        Http $httpRequest,
        Validator $formKeyValidator,
        GuestAdyenPaymentMethodManagementInterface $guestAdyenPaymentMethodManagement
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentMethodsHelper = $paymentMethodsHelper;
        $this->httpRequest = $httpRequest;
        $this->formKeyValidator = $formKeyValidator;
        $this->guestAdyenPaymentMethodManagement = $guestAdyenPaymentMethodManagement;
    }

    /**
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($cartId, AddressInterface $shippingAddress = null)
    {
        $isAjax = $this->httpRequest->isAjax();
        $formKeyValid = $this->formKeyValidator->validate($this->httpRequest);

        if (!$isAjax || !$formKeyValid) {
            throw new AdyenException(
                'Unable to access InternalGuestAdyenPaymentMethodManagement. Request is not AJAX or invalid CSRF token'
            );
        }

        return $this->guestAdyenPaymentMethodManagement->getPaymentMethods($cartId, $shippingAddress);
    }
}
