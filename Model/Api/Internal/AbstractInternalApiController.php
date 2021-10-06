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
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

abstract class AbstractInternalApiController
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
     * AbstractInternalApiController constructor.
     * @param Http $request
     * @param Validator $formKeyValidator
     */
    public function __construct(
        Http $request,
        Validator $formKeyValidator
    ) {
        $this->request = $request;
        $this->formKeyValidator = $formKeyValidator;
    }

    /**
     * @param $formKey
     * @return bool
     * @throws AdyenException
     */
    public function validateInternalRequest($formKey)
    {
        $isAjax = $this->request->isAjax();
        // Post value has to be manually set since it will have no post data when this function is accessed
        $formKeyValid = $this->formKeyValidator->validate($this->request->setPostValue('form_key', $formKey));

        if (!$isAjax || !$formKeyValid) {
            throw new AdyenException(
                'Unable to access internal api controller. Request is not AJAX or invalid CSRF token',
                401
            );
        }

        return true;
    }
}