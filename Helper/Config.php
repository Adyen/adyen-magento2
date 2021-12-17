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
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class Config
{
    const XML_PAYMENT_PREFIX = "payment";
    const XML_ADYEN_ABSTRACT_PREFIX = "adyen_abstract";
    const XML_ADYEN_GIVING_PREFIX = "adyen_giving";
    const XML_MERCHANT_ACCOUNT = "merchant_account";
    const XML_NOTIFICATIONS_USERNAME = "notification_username";
    const XML_NOTIFICATIONS_PASSWORD = "notification_password";
    const XML_NOTIFICATIONS_CAN_CANCEL_FIELD = "notifications_can_cancel";
    const XML_NOTIFICATIONS_HMAC_CHECK = "notifications_hmac_check";
    const XML_NOTIFICATIONS_IP_CHECK = "notifications_ip_check";
    const XML_NOTIFICATIONS_HMAC_KEY_LIVE = "notification_hmac_key_live";
    const XML_NOTIFICATIONS_HMAC_KEY_TEST = "notification_hmac_key_test";
    const XML_CHARGED_CURRENCY = "charged_currency";
    const XML_HAS_HOLDER_NAME = "has_holder_name";
    const XML_HOLDER_NAME_REQUIRED = "holder_name_required";
    const XML_HOUSE_NUMBER_STREET_LINE = "house_number_street_line";
    const XML_ADYEN_HPP_VAULT = 'adyen_hpp_vault';
    const XML_PAYMENT_ORIGIN_URL = 'payment_origin_url';
    const XML_PAYMENT_RETURN_URL = 'payment_return_url';
    const XML_STATUS_FRAUD_MANUAL_REVIEW = 'fraud_manual_review_status';
    const XML_STATUS_FRAUD_MANUAL_REVIEW_ACCEPT = 'fraud_manual_review_accept_status';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getMerchantAccount($storeId = null)
    {
        return $this->getConfigData(
            self::XML_MERCHANT_ACCOUNT,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getNotificationsUsername($storeId = null)
    {
        return $this->getConfigData(
            self::XML_NOTIFICATIONS_USERNAME,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getNotificationsPassword($storeId = null)
    {
        $key = $this->getConfigData(
            self::XML_NOTIFICATIONS_PASSWORD,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
        return $this->encryptor->decrypt(trim($key));
    }

    /**
     * Retrieve flag for notifications_can_cancel
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsCanCancel($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_CAN_CANCEL_FIELD,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve flag for notifications_hmac_check
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsHmacCheck($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_HMAC_CHECK,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve flag for notifications_ip_check
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsIpCheck($storeId = null)
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_IP_CHECK,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve key for notifications_hmac_key
     *
     * @param int $storeId
     * @return string
     */
    public function getNotificationsHmacKey($storeId = null)
    {
        if ($this->isDemoMode($storeId)) {
            $key = $this->getConfigData(
                self::XML_NOTIFICATIONS_HMAC_KEY_TEST,
                self::XML_ADYEN_ABSTRACT_PREFIX,
                $storeId,
                false
            );
        } else {
            $key = $this->getConfigData(
                self::XML_NOTIFICATIONS_HMAC_KEY_LIVE,
                self::XML_ADYEN_ABSTRACT_PREFIX,
                $storeId,
                false
            );
        }
        return $this->encryptor->decrypt(trim($key));
    }

    public function isDemoMode($storeId = null)
    {
        return $this->getConfigData('demo_mode', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    /**
     * Check if alternative payment methods vault is enabled
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isStoreAlternativePaymentMethodEnabled($storeId = null)
    {
        return $this->getConfigData('active', self::XML_ADYEN_HPP_VAULT, $storeId);
    }

    /**
     * Retrieve charged currency selection (base or display)
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getChargedCurrency($storeId = null)
    {
        return $this->getConfigData(self::XML_CHARGED_CURRENCY, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve has_holder_name config
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getHasHolderName($storeId = null)
    {
        return $this->getConfigData(self::XML_HAS_HOLDER_NAME, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve house_number_street_line config
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getHouseNumberStreetLine($storeId = null)
    {
        return $this->getConfigData(self::XML_HOUSE_NUMBER_STREET_LINE, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve holder_name_required config
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getHolderNameRequired($storeId = null)
    {
        return $this->getConfigData(self::XML_HOLDER_NAME_REQUIRED, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve payment_origin_url config
     *
     * @param int|string $storeId
     * @return mixed
     */
    public function getPWAOriginUrl($storeId)
    {
        return $this->getConfigData(self::XML_PAYMENT_ORIGIN_URL, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    public function adyenGivingEnabled($storeId)
    {
        return $this->getConfigData('active', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingConfigData($storeId)
    {
        return [
            'name' => $this->getAdyenGivingCharityName($storeId),
            'description' => $this->getAdyenGivingCharityDescription($storeId),
            'backgroundUrl' => $this->getAdyenGivingBackgroundImage($storeId),
            'logoUrl' => $this->getAdyenGivingCharityLogo($storeId),
            'website' => $this->getAdyenGivingCharityWebsite($storeId),
            'donationAmounts' => $this->getAdyenGivingDonationAmounts($storeId)
        ];
    }

    public function getAdyenGivingCharityName($storeId)
    {
        return $this->getConfigData('charity_name', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingCharityDescription($storeId)
    {
        return $this->getConfigData('charity_description', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingBackgroundImage($storeId)
    {
        return $this->getConfigData('background_image', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingCharityLogo($storeId)
    {
        return $this->getConfigData('charity_logo', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingCharityWebsite($storeId)
    {
        return $this->getConfigData('charity_website', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getAdyenGivingDonationAmounts($storeId)
    {
        return $this->getConfigData('donation_amounts', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    public function getCharityMerchantAccount($storeId)
    {
        return $this->getConfigData('charity_merchant_account', self::XML_ADYEN_GIVING_PREFIX, $storeId);
    }

    /**
     * Retrieve payment_return_url config
     *
     * @param int|string $storeId
     * @return mixed
     */
    public function getPWAReturnUrl($storeId)
    {
        return $this->getConfigData(self::XML_PAYMENT_RETURN_URL, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve the passed fraud status config
     *
     * @param int|string $storeId
     * @return mixed
     */
    public function getFraudStatus($fraudStatus, $storeId)
    {
        return $this->getConfigData(
            $fraudStatus,
            Config::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param string $xmlPrefix
     * @param int $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    private function getConfigData($field, $xmlPrefix, $storeId, $flag = false)
    {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }
}
