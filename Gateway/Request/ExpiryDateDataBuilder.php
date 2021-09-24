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
        $expiryDate = date_create_from_format(
            AdyenPayByLinkConfigProvider::DATE_FORMAT,
            $paymentFormFields["adyen_pbl_expires_at"]
        );

        $request['body']['expiresAt'] = $expiryDate->format(DATE_ATOM);

        return $request;
    }
}
