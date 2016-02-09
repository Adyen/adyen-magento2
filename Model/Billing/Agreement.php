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

namespace Adyen\Payment\Model\Billing;

class Agreement extends \Magento\Paypal\Model\Billing\Agreement
{

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $_adyenHelper;


    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param array $data
     */
    public function __construct(
        \Adyen\Payment\Helper\Data $adyenHelper,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Paypal\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementFactory,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []

       )
    {
        parent::__construct($context, $registry, $paymentData, $billingAgreementFactory, $dateFactory, $resource, $resourceCollection, $data);
        $this->_adyenHelper = $adyenHelper;
    }

    /**
     * Not yet possible to set different reference on customer level like magento 1.x version
     *
     * @return int
     */
    public function getCustomerReference()
    {
        return $this->getCustomerId();
    }


    public function parseRecurringContractData($data)
    {
        $this
            ->setMethodCode('adyen_oneclick')
            ->setReferenceId($data['recurringDetailReference'])
            ->setCreatedAt($data['creationDate']);

        $creationDate =  str_replace(' ', '-', $data['creationDate']);
        $this->setCreatedAt($creationDate);

        //Billing agreement SEPA
        if (isset($data['bank_iban'])) {
            $this->setAgreementLabel(__('%1, %2',
                $data['bank_iban'],
                $data['bank_ownerName']
            ));
        }

        // Billing agreement is CC
        if (isset($data['card']['number'])) {
            $ccType = $data['variant'];
            $ccTypes = $this->_adyenHelper->getCcTypesAltData();

            if (isset($ccTypes[$ccType])) {
                $ccType = $ccTypes[$ccType]['name'];
            }

            $label = __('%1, %2, **** %3',
                $ccType,
                $data['card']['holderName'],
                $data['card']['number'],
                $data['card']['expiryMonth'],
                $data['card']['expiryYear']
            );
            $this->setAgreementLabel($label);
        }

        if ($data['variant'] == 'paypal') {
            $label = __('PayPal %1',
                $data['lastKnownShopperEmail']
            );
            $this->setAgreementLabel($label);
        }

        $this->setAgreementData($data);

        return $this;
    }

    public function setAgreementData($data)
    {
        if (is_array($data)) {
            unset($data['creationDate']);
            unset($data['recurringDetailReference']);
            unset($data['payment_method']);
        }

        $this->setData('agreement_data', json_encode($data));
        return $this;
    }

    public function getAgreementData()
    {
        return json_decode($this->getData('agreement_data'), true);
    }
}