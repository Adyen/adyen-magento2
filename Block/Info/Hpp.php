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
     * Check if Payment method selection is configured on Adyen or Magento
     *
     * @return mixed
     */
    public function isPaymentSelectionOnAdyen()
    {
        return $this->_adyenHelper->getAdyenHppConfigDataFlag('payment_selection_on_adyen');
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
