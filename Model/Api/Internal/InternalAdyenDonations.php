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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api\Internal;

use Adyen\AdyenException;
use Adyen\Payment\Api\Internal\InternalAdyenDonationsInterface;
use Adyen\Payment\Model\Api\AdyenDonations;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;

class InternalAdyenDonations extends AbstractInternalApiController implements InternalAdyenDonationsInterface
{
    /**
     * @var AdyenDonations
     */
    private $adyenDonations;

    public function __construct(
        Http $request,
        Validator $formKeyValidator,
        AdyenDonations $adyenDonations
    )
    {
        parent::__construct($request, $formKeyValidator);
        $this->adyenDonations = $adyenDonations;
    }

    /**
     * @inheritDoc
     *
     * @throws CommandException
     * @throws NotFoundException
     * @throws AdyenException
     */
    public function handleInternalRequest($payload, $formKey)
    {
        $this->validateInternalRequest($formKey);
        return $this->adyenDonations->donate($payload);
    }
}
