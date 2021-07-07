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
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Requests;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RiskDataBuilder implements BuilderInterface
{
    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * PaymentDataBuilder constructor.
     *
     * @param Requests $adyenRequestsHelper
     */
    public function __construct(
        Requests $adyenRequestsHelper
    ) {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
    }

    /**
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $request['body'] = $this->adyenRequestsHelper->buildRiskData([]);
        return $request;
    }
}
