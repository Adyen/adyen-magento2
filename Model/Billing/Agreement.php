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
     * Agreement constructor.
     * 
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Paypal\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
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
    ) {
        parent::__construct($context,
                            $registry,
                            $paymentData,
                            $billingAgreementFactory,
                            $dateFactory,
                            $resource,
                            $resourceCollection,
                            $data);

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

    /**
     * @param $data
     * @return $this
     */
    public function parseRecurringContractData($data)
    {
        $this
            ->setMethodCode('adyen_oneclick')
            ->setReferenceId($data['recurringDetailReference'])
            ->setCreatedAt($data['creationDate']);

        $creationDate =  str_replace(' ', '-', $data['creationDate']);
        $this->setCreatedAt($creationDate);

        //Billing agreement SEPA
        if (isset($data['bank']['iban'])) {
            $this->setAgreementLabel(__('%1, %2',
                $data['bank']['iban'],
                $data['bank']['ownerName']
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

            $email = "";

            if (isset($data['tokenDetails']['tokenData']['EmailId'])) {
                $email = $data['tokenDetails']['tokenData']['EmailId'];
            } elseif (isset($data['lastKnownShopperEmail'])) {
                $email = $data['lastKnownShopperEmail'];
            }

            $label = __('PayPal %1',
                $email
            );
            $this->setAgreementLabel($label);
        }

        $this->setAgreementData($data);

        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
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

    /**
     * @return mixed
     */
    public function getAgreementData()
    {
        return json_decode($this->getData('agreement_data'), true);
    }
}