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
 * Copyright (c) 2019 Adyen B.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model;

use Adyen\AdyenException;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Magento\Framework\Exception\NoSuchEntityException as MagentoNoSuchEntityException;

class AdyenOriginKey implements \Adyen\Payment\Api\AdyenOriginKeyInterface
{
    /**
     * @var AdyenHelper
     */
    private $helper;

    public function __construct(AdyenHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * {@inheritDoc}
     * @throws MagentoNoSuchEntityException
     * @throws AdyenException
     */
    public function getOriginKey()
    {
        return $this->helper->getOriginKeyForBaseUrl();
    }
}
