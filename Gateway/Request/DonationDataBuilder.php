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

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Helper\Requests;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\StoreManagerInterface;

class DonationDataBuilder implements BuilderInterface
{
    /**
     * @var Requests
     */
    private $adyenRequestsHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(Requests $adyenRequestsHelper, StoreManagerInterface $storeManager)
    {
        $this->adyenRequestsHelper = $adyenRequestsHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $request['body'] = $this->adyenRequestsHelper
            ->buildDonationData($buildSubject['payment'], $this->storeManager->getStore()->getId());

        return $request;
    }
}
