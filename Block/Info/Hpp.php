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
 * Copyright (c) 2015 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Info;

class Hpp extends AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'Adyen_Payment::info/adyen_hpp.phtml';

    /**
     * Get all Banktransfer related data
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getBankTransferData()
    {
        $result = [];
        if (!empty($this->getInfo()->getOrder()->getPayment()) &&
            !empty($this->getInfo()->getOrder()->getPayment()->getAdditionalInformation('bankTransfer.owner'))
        ) {
            $result = $this->getInfo()->getOrder()->getPayment()->getAdditionalInformation();
        }

        return $result;
    }

    /**
     * Get all Multibanco related data
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMultibancoData()
    {
        $result = [];
        if (!empty($this->getInfo()->getOrder()->getPayment()) &&
            !empty($this->getInfo()->getOrder()->getPayment()->getAdditionalInformation('comprafacil.entity'))
        ) {
            $result = $this->getInfo()->getOrder()->getPayment()->getAdditionalInformation();
        }

        return $result;
    }

    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getOrder()
    {
        return $this->getInfo()->getOrder();
    }

    /**
     * @return null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSplitPayments()
    {
        // retrieve split payments of the order
        $orderPaymentCollection = $this->_adyenOrderPaymentCollectionFactory
            ->create()
            ->addPaymentFilterAscending($this->getInfo()->getId());

        if ($orderPaymentCollection->getSize() > 0) {
            return $orderPaymentCollection;
        } else {
            return null;
        }
    }
}
