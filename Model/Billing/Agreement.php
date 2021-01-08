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

use Magento\Sales\Model\Order\Payment;

class Agreement extends \Magento\Paypal\Model\Billing\Agreement
{
    /**
     * @var \Adyen\Payment\Helper\Data
     */
    private $adyenHelper;

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
        parent::__construct(
            $context,
            $registry,
            $paymentData,
            $billingAgreementFactory,
            $dateFactory,
            $resource,
            $resourceCollection,
            $data
        );

        $this->adyenHelper = $adyenHelper;
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
     * for async store of billing agreement through the recurring_contract notification
     *
     * @param $data
     * @return $this
     */
    public function parseRecurringContractData($data)
    {
        $this
            ->setMethodCode('adyen_oneclick')
            ->setReferenceId($data['recurringDetailReference'])
            ->setCreatedAt($data['creationDate']);

        $creationDate = str_replace(' ', '-', $data['creationDate']);
        $this->setCreatedAt($creationDate);

        //Billing agreement SEPA
        if (isset($data['bank']['iban'])) {
            $this->setAgreementLabel(
                __(
                    '%1, %2',
                    $data['bank']['iban'],
                    $data['bank']['ownerName']
                )
            );
        }

        // Billing agreement is CC
        if (isset($data['card']['number'])) {
            $ccType = $data['variant'];
            if (strpos($ccType, "paywithgoogle") !== false && !empty($data['paymentMethodVariant'])) {
                $ccType = $data['paymentMethodVariant'];
            }
            $ccTypes = $this->adyenHelper->getCcTypesAltData();

            if (isset($ccTypes[$ccType])) {
                $ccType = $ccTypes[$ccType]['name'];
            }

            $label = __(
                '%1, %2, **** %3',
                $ccType,
                $data['card']['holderName'],
                $data['card']['number'],
                $data['card']['expiryMonth'],
                $data['card']['expiryYear']
            );
            $this->setAgreementLabel($label);
        }

        if ($data['variant'] == 'paypal') {
            $email = '';

            if (isset($data['tokenDetails']['tokenData']['EmailId'])) {
                $email = $data['tokenDetails']['tokenData']['EmailId'];
            } elseif (isset($data['lastKnownShopperEmail'])) {
                $email = $data['lastKnownShopperEmail'];
            }

            $label = __(
                'PayPal %1',
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
            unset($data['creationDate'], $data['recurringDetailReference'], $data['payment_method']);
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

    /**
     * For sync result to store billing agreement
     *
     * @param $contractDetail
     * @return $this
     */
    public function setCcBillingAgreement($contractDetail, $storeOneClick, $storeId)
    {
        $this
            ->setMethodCode('adyen_oneclick')
            ->setReferenceId($contractDetail['recurring.recurringDetailReference']);

        if (!isset($contractDetail['cardBin']) ||
            !isset($contractDetail['cardHolderName']) ||
            !isset($contractDetail['cardSummary']) ||
            !isset($contractDetail['expiryDate']) ||
            !isset($contractDetail['paymentMethod'])
        ) {
            $this->_errors[] = __(
                '"In the Additional data in API response section, select: Card bin,
                Card summary, Expiry Date, Cardholder name, Recurring details and Variant
                to create billing agreements immediately after the payment is authorized."'
            );
            return $this;
        }
        // Billing agreement is CC

        $ccType = $variant = $contractDetail['paymentMethod'];
        if (strpos($ccType, "paywithgoogle") !== false && !empty($contractDetail['paymentMethodVariant'])) {
            $ccType = $variant = $contractDetail['paymentMethodVariant'];
        }
        $ccTypes = $this->adyenHelper->getCcTypesAltData();

        if (isset($ccTypes[$ccType])) {
            $ccType = $ccTypes[$ccType]['name'];
        }

        if ($contractDetail['cardHolderName']) {
            $label = __(
                '%1, %2, **** %3',
                $ccType,
                $contractDetail['cardHolderName'],
                $contractDetail['cardSummary']
            );
        } else {
            $label = __(
                '%1, **** %2',
                $ccType,
                $contractDetail['cardSummary']
            );
        }

        $this->setAgreementLabel($label);

        $expiryDate = explode('/', $contractDetail['expiryDate']);

        if (!empty($contractDetail['pos_payment'])) {
            $recurringType = $this->adyenHelper->getAdyenPosCloudConfigData('recurring_type', $storeId);
        } else {
            $recurringType = $this->adyenHelper->getRecurringTypeFromOneclickRecurringSetting($storeId);

            // for bcmc and maestro recurring is not allowed so don't set this
            if ($recurringType === \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING &&
                ($contractDetail['paymentMethod'] === "bcmc" || $contractDetail['paymentMethod'] === "maestro")
            ) {
                $recurringType = \Adyen\Payment\Model\RecurringType::ONECLICK;
            }

            // if shopper decides to not store the card don't save it as oneclick
            if (!$storeOneClick &&
                $recurringType === \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING
            ) {
                $recurringType = \Adyen\Payment\Model\RecurringType::RECURRING;
            }
        }

        $agreementData = [
            'card' => [
                'holderName' => $contractDetail['cardHolderName'],
                'number' => $contractDetail['cardSummary'],
                'expiryMonth' => $expiryDate[0],
                'expiryYear' => $expiryDate[1]
            ],
            'variant' => $variant,
            'contractTypes' => explode(',', $recurringType)
        ];

        if (!empty($contractDetail['pos_payment'])) {
            $agreementData['posPayment'] = true;
        }

        $this->setAgreementData($agreementData);

        return $this;
    }

    /**
     * @param Payment $payment
     * @param $recurringDetailReference
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * todo: refactor or remove this method as those fields are set later
     */
    public function importOrderPaymentWithRecurringDetailReference(Payment $payment, $recurringDetailReference)
    {
        $baData = $payment->getBillingAgreementData();
        $this->_paymentMethodInstance = (isset($baData['method_code']))
            ? $this->_paymentData->getMethodInstance($baData['method_code'])
            : $payment->getMethodInstance();
        if(empty($baData['billing_agreement_id'])){
            $baData['billing_agreement_id'] = $recurringDetailReference;
        }

        $this->_paymentMethodInstance->setStore($payment->getMethodInstance()->getStore());
        $this->setCustomerId($payment->getOrder()->getCustomerId())
            ->setMethodCode($this->_paymentMethodInstance->getCode())
            ->setReferenceId($baData['billing_agreement_id'])
            ->setStatus(self::STATUS_ACTIVE)
            ->setAgreementLabel($this->_paymentMethodInstance->getTitle());

        return $this;
    }

    public function getErrors()
    {
        return $this->_errors;
    }
}
