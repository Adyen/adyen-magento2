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

namespace Adyen\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;

class AdyenPaymentMethodConfigProvider implements ConfigProviderInterface
{
    /** @var RequestInterface  */
    protected $request;

    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    public function getConfig(): array
    {
        return [];
    }

    protected function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
