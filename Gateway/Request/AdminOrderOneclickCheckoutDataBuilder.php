<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AdminOrderOneclickCheckoutDataBuilder implements BuilderInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * AdminOrderOneclickCheckoutDataBuilder constructor.
     *
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * Add type and storedPaymentMethodId into request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentFormFields = $this->request->getParam('payment');

        $requestBody = array(
            'paymentMethod' => array(
                'type' => 'scheme',
                'storedPaymentMethodId' => $paymentFormFields['recurring_detail_reference']
            ),
        );

        return [
            'body' => $requestBody
        ];
    }

}