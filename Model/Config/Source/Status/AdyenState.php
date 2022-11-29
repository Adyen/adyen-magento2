<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Source\Status;


class AdyenState
{
    final const STATE_MAINTAIN = "maintain";
    final const STATE_MAINTAIN_STATUS = [self::STATE_MAINTAIN => "Maintain status"];
}
