<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\PaymentMethod;

use Adyen\Payment\Api\Data\AdyenPaymentMethodInterface;
use Adyen\Payment\Api\Data\AdyenPaymentMethodRepositoryInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\ResourceModel\Metadata;

class AdyenPaymentMethodRepository implements AdyenPaymentMethodRepositoryInterface
{
    /** @var Metadata  */
    private $metaData;

    public function __construct(Metadata $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * @throws AlreadyExistsException
     */
    public function save(AdyenPaymentMethodInterface $adyenPaymentMethod)
    {
        $this->metaData->getMapper()->save($adyenPaymentMethod);
    }
}
