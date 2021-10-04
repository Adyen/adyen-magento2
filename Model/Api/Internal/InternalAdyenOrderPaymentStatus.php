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
use Adyen\Payment\Api\AdyenOrderPaymentStatusInterface;
use Adyen\Payment\Api\Internal\InternalAdyenOrderPaymentStatusInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

/**
 * Class InternalAdyenPaymentDetailsInterface
 */
class InternalAdyenOrderPaymentStatus implements InternalAdyenOrderPaymentStatusInterface
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
     * @var AdyenOrderPaymentStatusInterface
     */
    protected $adyenOrderPaymentStatus;

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
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
        $this->adyenOrderPaymentStatus = $adyenOrderPaymentStatus;
    }

    /**
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($orderId, $shopperEmail, $formKey)
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

        return $this->adyenOrderPaymentStatus->getOrderPaymentStatus($orderId, $shopperEmail);
    }
}
