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

namespace Adyen\Payment\Gateway\Command;

use Magento\Payment\Gateway\Command;
use Magento\Payment\Gateway\CommandInterface;

class HppCommand implements CommandInterface
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $_adyenHelper;

    /**
     * HppCommand constructor.
     *
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     */
    public function __construct(\Adyen\Payment\Helper\Data $adyenHelper)
    {
        $this->_adyenHelper = $adyenHelper;
    }
    /**
     * @param array $commandSubject
     * @return $this
     */
    public function execute(array $commandSubject)
    {
        $payment =\Magento\Payment\Gateway\Helper\SubjectReader::readPayment($commandSubject);
        $stateObject = \Magento\Payment\Gateway\Helper\SubjectReader::readStateObject($commandSubject);

        // do not send email
        $payment = $payment->getPayment();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);


        // update customer based on additionalFields
        if ($payment->getAdditionalInformation("gender")) {
            $order->setCustomerGender(\Adyen\Payment\Model\Gender::getMagentoGenderFromAdyenGender(
                $payment->getAdditionalInformation("gender"))
            );
        }

        if ($payment->getAdditionalInformation("dob")) {
            $order->setCustomerDob($payment->getAdditionalInformation("dob"));
        }

        if ($payment->getAdditionalInformation("telephone")) {
            $order->getBillingAddress()->setTelephone($payment->getAdditionalInformation("telephone"));
        }

        // update status and state
        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus($this->_adyenHelper->getAdyenAbstractConfigData('order_status'));
        $stateObject->setIsNotified(false);
        
        return $this;
    }
}