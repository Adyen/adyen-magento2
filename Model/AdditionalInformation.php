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

use Adyen\Payment\Api\Data\AdditionalInformationInterface;
use Magento\Framework\Model\AbstractModel;

class AdditionalInformation extends AbstractModel implements AdditionalInformationInterface
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\AdditionalInformation::class);
    }

    /**
     * @return array|int|mixed|null
     */
    public function getPaymentId()
    {
        return $this->getData(self::PAYMENT_ID);
    }

    /**
     * @param int $paymentId
     * @return AdditionalInformation
     */
    public function setPaymentId(int $paymentId)
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
    }

    /**
     * @return array|mixed|string|null
     */
    public function getAdditionalInformation() {
        return $this->getData(self::ADDITIONAL_INFORMATION);
    }

    /**
     * @param string $additionalInformation
     * @return AdditionalInformation
     */
    public function setAdditionalInformation(string $additionalInformation)
    {
        return $this->setData(self::ADDITIONAL_INFORMATION, $additionalInformation);
    }

}