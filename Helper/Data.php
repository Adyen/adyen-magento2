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

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Framework\Config\DataInterface
     */
    protected $_dataStorage;

    /**
     * @var \Magento\Directory\Model\Config\Source\Country
     */
    protected $_country;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var \Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory
     */
    protected $_billingAgreementCollectionFactory;

    /**
     * @var Repository
     */
    protected $_assetRepo;

    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $_assetSource;

    /**
     * Data constructor.
     * 
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Framework\Config\DataInterface $dataStorage
     * @param \Magento\Directory\Model\Config\Source\Country $country
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param \Magento\Framework\View\Asset\Source $assetSource
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Config\DataInterface $dataStorage,
        \Magento\Directory\Model\Config\Source\Country $country,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Adyen\Payment\Model\Resource\Billing\Agreement\CollectionFactory $billingAgreementCollectionFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\View\Asset\Source $assetSource
    ) {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_dataStorage = $dataStorage;
        $this->_country = $country;
        $this->_moduleList = $moduleList;
        $this->_billingAgreementCollectionFactory = $billingAgreementCollectionFactory;
        $this->_assetRepo = $assetRepo;
        $this->_assetSource = $assetSource;
    }

    /**
     * @desc return recurring types for configuration setting
     * @return array
     */
    public function getRecurringTypes()
    {
        return [
            \Adyen\Payment\Model\RecurringType::ONECLICK => 'ONECLICK',
            \Adyen\Payment\Model\RecurringType::ONECLICK_RECURRING => 'ONECLICK,RECURRING',
            \Adyen\Payment\Model\RecurringType::RECURRING => 'RECURRING'
        ];
    }

    /**
     * @desc return recurring types for configuration setting
     * @return array
     */
    public function getModes()
    {
        return [
            '1' => 'Test Mode',
            '0' => 'Production Mode'
        ];
    }

    /**
     * @desc return recurring types for configuration setting
     * @return array
     */
    public function getCaptureModes() {
        return [
            'auto' => 'immediate',
            'manual' => 'manual'
        ];
    }

    /**
     * @desc return recurring types for configuration setting
     * @return array
     */
    public function getPaymentRoutines() {
        return [
            'single' => 'Single Page Payment Routine',
            'multi' => 'Multi-page Payment Routine'
        ];
    }


    /**
     * Return the formatted currency. Adyen accepts the currency in multiple formats.
     * @param $amount
     * @param $currency
     * @return string
     */
    public function formatAmount($amount, $currency)
    {
        switch($currency) {
            case "JPY":
            case "IDR":
            case "KRW":
            case "BYR":
            case "VND":
            case "CVE":
            case "DJF":
            case "GNF":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
            case "GHC":
            case "KMF":
                $format = 0;
                break;
            case "MRO":
                $format = 1;
                break;
            case "BHD":
            case "JOD":
            case "KWD":
            case "OMR":
            case "LYD":
            case "TND":
                $format = 3;
                break;
            default:
                $format = 2;
                break;
        }

        return (int)number_format($amount, $format, '', '');
    }

    /**
     * Tax Percentage needs to be in minor units for Adyen
     *
     * @param float $taxPercent
     * @return int
     */
    public function getMinorUnitTaxPercent($taxPercent)
    {
        $taxPercent = $taxPercent * 100;
        return (int)$taxPercent;
    }

    /**
     * @param $amount
     * @param $currency
     * @return float
     */
    public function originalAmount($amount, $currency)
    {
        // check the format
        switch($currency) {
            case "JPY":
            case "IDR":
            case "KRW":
            case "BYR":
            case "VND":
            case "CVE":
            case "DJF":
            case "GNF":
            case "PYG":
            case "RWF":
            case "UGX":
            case "VUV":
            case "XAF":
            case "XOF":
            case "XPF":
            case "GHC":
            case "KMF":
                $format = 1;
                break;
            case "MRO":
                $format = 10;
                break;
            case "BHD":
            case "JOD":
            case "KWD":
            case "OMR":
            case "LYD":
            case "TND":
                $format = 1000;
                break;
            default:
                $format = 100;
                break;
        }

        return ($amount / $format);
    }

    /**
     * Street format
     * @param type $address
     * @return array
     */
    public function getStreet($address)
    {
        if (empty($address)) {
            return false;
        }

        $street = self::formatStreet($address->getStreet());
        $streetName = $street['0'];
        unset($street['0']);
        $streetNr = implode(' ', $street);
        return (['name' => trim($streetName), 'house_number' => $streetNr]);
    }

    /**
     * Fix this one string street + number
     * @example street + number
     * @param type $street
     * @return type $street
     */
    static public function formatStreet($street)
    {
        if (count($street) != 1) {
            return $street;
        }
        preg_match('/((\s\d{0,10})|(\s\d{0,10}\w{1,3}))$/i', $street['0'], $houseNumber, PREG_OFFSET_CAPTURE);
        if (!empty($houseNumber['0'])) {
            $_houseNumber = trim($houseNumber['0']['0']);
            $position = $houseNumber['0']['1'];
            $streetName = trim(substr($street['0'], 0, $position));
            $street = [$streetName, $_houseNumber];
        }
        return $street;
    }


    /**
     * @desc gives back global configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenAbstractConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId);
    }

    /**
     * @desc gives back global configuration values as boolean
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenAbstractConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId, true);
    }

    /**
     * @desc Gives back adyen_cc configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenCcConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_cc', $storeId);
    }

    /**
     * @desc Gives back adyen_cc configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenCcConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_cc', $storeId, true);
    }

    /**
     * @desc Gives back adyen_hpp configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenHppConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_hpp', $storeId);
    }

    /**
     * @desc Gives back adyen_hpp configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenHppConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_hpp', $storeId, true);
    }

    /**
     * @desc Gives back adyen_oneclick configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenOneclickConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId);
    }

    /**
     * @desc Gives back adyen_oneclick configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenOneclickConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId, true);
    }

    /**
     * @desc Gives back adyen_pos configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenPosConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pos', $storeId);
    }

    /**
     * @desc Gives back adyen_pos configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenPosConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pos', $storeId, true);
    }

    /**
     * @desc Gives back adyen_pay_by_mail configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenPayByMailConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pay_by_mail', $storeId);
    }

    /**
     * @desc Gives back adyen_pay_by_mail configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenPayByMailConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_pay_by_mail', $storeId, true);
    }

    /**
     * @desc Gives back adyen_boleto configuration values
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenBoletoConfigData($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_boleto', $storeId);
    }

    /**
     * @desc Gives back adyen_boleto configuration values as flag
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getAdyenBoletoConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfigData($field, 'adyen_boleto', $storeId, true);
    }

    /**
     * @desc Retrieve decrypted hmac key
     * @return string
     */
    public function getHmac()
    {
        switch ($this->isDemoMode()) {
            case true:
                $secretWord =  $this->_encryptor->decrypt(trim($this->getAdyenHppConfigData('hmac_test')));
                break;
            default:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenHppConfigData('hmac_live')));
                break;
        }
        return $secretWord;
    }

    public function getHmacPayByMail()
    {
        switch ($this->isDemoMode()) {
            case true:
                $secretWord =  $this->_encryptor->decrypt(trim($this->getAdyenPayByMailConfigData('hmac_test')));
                break;
            default:
                $secretWord = $this->_encryptor->decrypt(trim($this->getAdyenPayByMailConfigData('hmac_live')));
                break;
        }
        return $secretWord;
    }

    /**
     * @desc Check if configuration is set to demo mode
     * @return mixed
     */
    public function isDemoMode($storeId = null)
    {
        return $this->getAdyenAbstractConfigDataFlag('demo_mode', $storeId);
    }

    /**
     * @desc Retrieve the decrypted notification password
     * @return string
     */
    public function getNotificationPassword()
    {
        return $this->_encryptor->decrypt(trim($this->getAdyenAbstractConfigData('notification_password')));
    }

    /**
     * @desc Retrieve the webserver username
     * @return string
     */
    public function getWsUsername($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $wsUsername =  trim($this->getAdyenAbstractConfigData('ws_username_test', $storeId));
        } else {
            $wsUsername = trim($this->getAdyenAbstractConfigData('ws_username_live', $storeId));
        }
        return $wsUsername;
    }

    /**
     * @desc Retrieve the webserver password
     * @return string
     */
    public function getWsPassword($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $wsPassword = $this->_encryptor->decrypt(trim($this->getAdyenAbstractConfigData('ws_password_test', $storeId)));
        } else {
            $wsPassword = $this->_encryptor->decrypt(trim($this->getAdyenAbstractConfigData('ws_password_live', $storeId)));
        }
        return $wsPassword;
    }

    /**
     * @desc Cancels the order
     * @param $order
     */
    public function cancelOrder($order)
    {
        $orderStatus = $this->getAdyenAbstractConfigData('payment_cancelled');
        $order->setActionFlag($orderStatus, true);

        switch ($orderStatus) {
            case \Magento\Sales\Model\Order::STATE_HOLDED:
                if ($order->canHold()) {
                    $order->hold()->save();
                }
                break;
            default:
                if ($order->canCancel()) {
                    $order->cancel()->save();
                }
                break;
        }
    }

    /**
     * Creditcard type that is selected is different from creditcard type that we get back from the request this
     * function get the magento creditcard type this is needed for getting settings like installments
     * @param $ccType
     * @return mixed
     */
    public function getMagentoCreditCartType($ccType)
    {
        $ccTypesMapper = $this->getCcTypesAltData();

        if (isset($ccTypesMapper[$ccType])) {
            $ccType = $ccTypesMapper[$ccType]['code'];
        }

        return $ccType;
    }

    /**
     * @return array
     */
    public function getCcTypesAltData()
    {
        $adyenCcTypes =  $this->getAdyenCcTypes();
        $types = [];
        foreach ($adyenCcTypes as $key => $data) {
            $types[$data['code_alt']] = $data;
            $types[$data['code_alt']]['code'] = $key;
        }
        return $types;
    }

    /**
     * @return mixed
     */
    public function getAdyenCcTypes()
    {
        return $this->_dataStorage->get('adyen_credit_cards');
    }

    /**
     * @desc Retrieve information from payment configuration
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if(!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }


    /**
     * @return array
     */
    public function getSepaCountries()
    {
        $sepaCountriesAllowed = [
            "AT", "BE", "BG", "CH", "CY", "CZ", "DE", "DK", "EE", "ES", "FI", "FR", "GB", "GF", "GI", "GP", "GR", "HR",
            "HU", "IE", "IS", "IT", "LI", "LT", "LU", "LV", "MC", "MQ", "MT", "NL", "NO", "PL", "PT", "RE", "RO", "SE",
            "SI", "SK"
        ];

        $countryList = $this->_country->toOptionArray();
        $sepaCountries = [];

        foreach ($countryList as $key => $country) {
            $value = $country['value'];
            if (in_array($value, $sepaCountriesAllowed)) {
                $sepaCountries[$value] = $country['label'];
            }
        }
        return $sepaCountries;
    }

    public function getModuleVersion()
    {
        return (string) $this->_moduleList->getOne("Adyen_Payment")['setup_version'];
    }

    public function getBoletoTypes()
    {
        return [
            [
                'value' => 'boletobancario_hsbc',
                'label' => __('boletobancario_hsbc'),
            ],
            [
                'value' => 'boletobancario_itau',
                'label' => __('boletobancario_itau'),
            ],
            [
                'value' => 'boletobancario_santander',
                'label' => __('boletobancario_santander'),
            ],
            [
                'value' => 'boletobancario_bradesco',
                'label' => __('boletobancario_bradesco'),
            ],
            [
                'value' => 'boletobancario_bancodobrasil',
                'label' => __('boletobancario_bancodobrasil'),
            ],
        ];
    }

    /**
     * @param $customerId
     * @param $storeId
     * @param $grandTotal
     * @param $recurringType
     * @return array
     */
    public function getOneClickPaymentMethods($customerId, $storeId, $grandTotal, $recurringType)
    {
        $billingAgreements = [];

        $baCollection = $this->_billingAgreementCollectionFactory->create();
        $baCollection->addFieldToFilter('customer_id', $customerId);
        $baCollection->addFieldToFilter('store_id', $storeId);
        $baCollection->addFieldToFilter('method_code', 'adyen_oneclick');
        $baCollection->addActiveFilter();

        foreach ($baCollection as $billingAgreement) {

            $agreementData = $billingAgreement->getAgreementData();

            // no agreementData and contractType then ignore
            if ((!is_array($agreementData)) || (!isset($agreementData['contractTypes']))) {
                continue;
            }

            // check if contractType is supporting the selected contractType for OneClick payments
            $allowedContractTypes = $agreementData['contractTypes'];
            if (in_array($recurringType, $allowedContractTypes)) {
                // check if AgreementLabel is set and if contract has an recurringType
                if ($billingAgreement->getAgreementLabel()) {

                    // for Ideal use sepadirectdebit because it is
                    if ($agreementData['variant'] == 'ideal') {
                        $agreementData['variant'] = 'sepadirectdebit';
                    }

                    $data = ['reference_id' => $billingAgreement->getReferenceId(),
                        'agreement_label' => $billingAgreement->getAgreementLabel(),
                        'agreement_data' => $agreementData
                    ];

                    if ($this->showLogos()) {
                        $logoName = $agreementData['variant'];

                        $asset = $this->createAsset(
                            'Adyen_Payment::images/logos/' . $logoName . '.png'
                        );
                        
                        $icon = null;
                        $placeholder = $this->_assetSource->findSource($asset);
                        if ($placeholder) {
                            list($width, $height) = getimagesize($asset->getSourceFile());
                            $icon = [
                                'url' => $asset->getUrl(),
                                'width' => $width,
                                'height' => $height
                            ];
                        }
                        $data['logo'] = $icon;
                    }

                    /**
                     * Check if there are installments for this creditcard type defined
                     */
                    $data['number_of_installments'] = 0;
                    $ccType = $this->getMagentoCreditCartType($agreementData['variant']);
                    $installments = null;
                    $installmentsValue = $this->getAdyenCcConfigData('installments');
                    if ($installmentsValue) {
                        $installments = unserialize($installmentsValue);
                    }

                    if ($installments) {
                        $numberOfInstallments = null;

                        foreach ($installments as $ccTypeInstallment => $installment) {
                            if ($ccTypeInstallment == $ccType) {
                                foreach ($installment as $amount => $installments) {
                                    if ($grandTotal <= $amount) {
                                        $numberOfInstallments = $installments;
                                    }
                                }
                            }
                        }
                        if ($numberOfInstallments) {
                            $data['number_of_installments'] = $numberOfInstallments;
                        }
                    }
                    $billingAgreements[] = $data;
                }
            }
        }
        return $billingAgreements;
    }


    /**
     * @param $paymentMethod
     * @return bool
     */
    public function isPaymentMethodOpenInvoiceMethod($paymentMethod)
    {
        if (strlen($paymentMethod) >= 9 && substr($paymentMethod, 0, 9) == 'afterpay_') {
            return true;
        } else if($paymentMethod == 'klarna' || $paymentMethod == 'ratepay') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function showLogos()
    {
        $showLogos = $this->getAdyenAbstractConfigData('title_renderer');
        if ($showLogos == \Adyen\Payment\Model\Config\Source\RenderMode::MODE_TITLE_IMAGE) {
            return true;
        }
        return false;
    }

    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->_request->isSecure()], $params);
        return $this->_assetRepo->createAsset($fileId, $params);
    }

}