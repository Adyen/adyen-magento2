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
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

/**
 * Class InternalAdyenPaymentDetailsInterface
 */
class InternalAdyenPaymentDetails extends AbstractInternalApiController implements InternalAdyenPaymentDetailsInterface
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
        parent::__construct($request, $formKeyValidator);
        $this->adyenPaymentDetails = $adyenPaymentDetails;
    }

    /**
     * {@inheritDoc}
     * @throws AdyenException
     */
    public function handleInternalRequest($payload, $formKey)
    {
        $this->validateInternalRequest($formKey);

        return $this->adyenPaymentDetails->initiate($payload);
    }
}
