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

namespace Adyen\Payment\Api\Data;


interface AdyenPaymentMethodInterface
{
    const ENTITY_ID = 'entity_id';

    const PAYMENT_METHOD = 'payment_method';

    const ENABLE_RECURRING = 'enable_recurring';

    const ACTIVE = 'active';


    public function getEntityId();

    public function setEntityId($entityId);

    public function getPaymentMethod();

    public function setPaymentMethod($paymentMethod);

    public function getEnableRecurring();

    public function setEnableRecurring($enableRecurring);

    public function getActive();

    public function setActive($active);
}
