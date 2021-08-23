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

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Magento\Framework\Model\AbstractModel;

class PaymentResponse extends AbstractModel implements PaymentResponseInterface
{

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\PaymentResponse::class);
    }


    /**
     * @return mixed
     */
    public function getMerchantReference()
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    /**
     * @param string $merchantReference
     * @return mixed
     */
    public function setMerchantReference($merchantReference)
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    /**
     * @return mixed
     */
    public function getResultCode()
    {
        return $this->getData(self::RESULT_CODE);
    }

    /**
     * @param string $resultCode
     * @return mixed
     */
    public function setResultCode($resultCode)
    {
        return $this->setData(self::RESULT_CODE, $resultCode);
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->getData(self::RESPONSE);
    }

    /**
     * @param string $response
     * @return mixed
     */
    public function setResponse($response)
    {
        return $this->setData(self::RESPONSE, $response);
    }
}
