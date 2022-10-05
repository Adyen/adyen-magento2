<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Gateway\Request;

use Adyen\Payment\Model\Ui\AdyenPayByLinkConfigProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ExpiryDateDataBuilder implements BuilderInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * Add delivery\billing details into request
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $paymentFormFields = $this->request->getParam('payment');
        $expiryDate = null;
        $payment = $buildSubject['payment']->getPayment()->getAdditionalInformation()['adyen_pbl_expires_at'];

        if (!is_null($paymentFormFields) && isset($paymentFormFields["adyen_pbl_expires_at"])) {
            $expiryDate = $paymentFormFields["adyen_pbl_expires_at"];
        }
        elseif (isset($payment)) {
            $expiryDate = $payment;
        }

        if ($expiryDate) {
            $expiryDateTime = date_create_from_format(
                AdyenPayByLinkConfigProvider::DATE_TIME_FORMAT,
                $expiryDate . ' 23:59:59'
            );

            $request['body']['expiresAt'] = $expiryDateTime->format(DATE_ATOM);

            return $request;
        }
    }
}
