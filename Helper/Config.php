<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\AdyenException;
use Adyen\Payment\Model\Config\Source\NotificationProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Config
{
    const XML_PAYMENT_PREFIX = "payment";
    const XML_ADYEN_ABSTRACT_PREFIX = "adyen_abstract";
    const XML_ADYEN_GIVING_PREFIX = "adyen_giving";
    const XML_MERCHANT_ACCOUNT = "merchant_account";
    const XML_NOTIFICATIONS_USERNAME = "notification_username";
    const XML_NOTIFICATIONS_PASSWORD = "notification_password";
    const XML_WEBHOOK_URL = "webhook_url";
    const XML_NOTIFICATIONS_CAN_CANCEL_FIELD = "notifications_can_cancel";
    const XML_NOTIFICATIONS_HMAC_KEY_LIVE = "notification_hmac_key_live";
    const XML_NOTIFICATIONS_HMAC_KEY_TEST = "notification_hmac_key_test";
    const XML_NOTIFICATIONS_IP_CHECK = "notifications_ip_check";
    const XML_CHARGED_CURRENCY = "charged_currency";
    const XML_HAS_HOLDER_NAME = "has_holder_name";
    const XML_HOLDER_NAME_REQUIRED = "holder_name_required";
    const XML_HOUSE_NUMBER_STREET_LINE = "house_number_street_line";
    const XML_ADYEN_ONECLICK = 'adyen_oneclick';
    const XML_ADYEN_HPP = 'adyen_hpp';
    const XML_ADYEN_CC = 'adyen_cc';
    const XML_ADYEN_HPP_VAULT = 'adyen_hpp_vault';
    const XML_ADYEN_CC_VAULT = 'adyen_cc_vault';
    const XML_ADYEN_MOTO = 'adyen_moto';
    const XML_PAYMENT_ORIGIN_URL = 'payment_origin_url';
    const XML_PAYMENT_RETURN_URL = 'payment_return_url';
    const XML_STATUS_FRAUD_MANUAL_REVIEW = 'fraud_manual_review_status';
    const XML_STATUS_FRAUD_MANUAL_REVIEW_ACCEPT = 'fraud_manual_review_accept_status';
    const XML_MOTO_MERCHANT_ACCOUNTS = 'moto_merchant_accounts';
    const XML_CONFIGURATION_MODE = 'configuration_mode';
    const XML_ADYEN_POS_CLOUD = 'adyen_pos_cloud';
    const XML_WEBHOOK_NOTIFICATION_PROCESSOR = 'webhook_notification_processor';
    const XML_THREEDS_FLOW = 'threeds_flow';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param WriterInterface $configWriter
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        WriterInterface $configWriter,
        SerializerInterface $serializer
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->configWriter = $configWriter;
        $this->serializer = $serializer;
    }

    /**
     * @param $mode
     * @param mixed $storeId
     * @return string
     */
    public function getApiKey($mode, $storeId = null): string
    {
        $apiKey = $this->getConfigData('api_key_' . $mode, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);

        return $this->encryptor->decrypt($apiKey);
    }

    /**
     * @param $mode
     * @param $storeId
     * @return string|null
     */
    public function getClientKey($mode, $storeId = null): ?string
    {
        return $this->getConfigData('client_key_' . $mode, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getMerchantAccount($storeId = null): ?string
    {
        return $this->getConfigData(
            self::XML_MERCHANT_ACCOUNT,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return array|null
     */
    public function getMotoMerchantAccounts($storeId = null): ?array
    {
        $serializedData = $this->getConfigData(
            self::XML_MOTO_MERCHANT_ACCOUNTS,
            self::XML_ADYEN_MOTO,
            $storeId
        );

        return $this->serializer->unserialize($serializedData);
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isMotoPaymentMethodEnabled($storeId = null): bool
    {
        return $this->getConfigData('active', Config::XML_ADYEN_MOTO, $storeId, true);
    }

    /**
     * Returns the properties of a MOTO merchant account in an array (API Key, Client Key, Demo Mode)
     *
     * @param string $motoMerchantAccount
     * @param $storeId
     * @return array
     * @throws AdyenException
     */
    public function getMotoMerchantAccountProperties(string $motoMerchantAccount, $storeId = null) : array
    {
        $motoMerchantAccounts = $this->getMotoMerchantAccounts($storeId);

        if (!isset($motoMerchantAccounts[$motoMerchantAccount])) {
            throw new AdyenException("Related MOTO merchant account couldn't be found.");
        }

        return $motoMerchantAccounts[$motoMerchantAccount];
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getNotificationsUsername($storeId = null): ?string
    {
        return $this->getConfigData(
            self::XML_NOTIFICATIONS_USERNAME,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getNotificationsPassword($storeId = null): ?string
    {
        $key = $this->getConfigData(
            self::XML_NOTIFICATIONS_PASSWORD,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );

        if (is_null($key)) {
            return null;
        }
        return $this->encryptor->decrypt(trim($key));
    }

    /**
     * @param mixed $storeId
     * @return string|null
     */
    public function getWebhookUrl($storeId = null): ?string
    {
        return $this->getConfigData(
            self::XML_WEBHOOK_URL,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    public function getWebhookId($storeId = null): ?string
    {
        return $this->getConfigData('webhook_id', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    /**
     * Retrieve flag for notifications_can_cancel
     *
     * @param mixed $storeId
     * @return bool
     */
    public function getNotificationsCanCancel($storeId = null): bool
    {
        return (bool)$this->getConfigData(
            self::XML_NOTIFICATIONS_CAN_CANCEL_FIELD,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    /**
     * Retrieve key for notifications_hmac_key
     *
     * @param mixed $storeId
     * @return string|null
     */
    public function getNotificationsHmacKey($storeId = null): ?string
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

        if (is_null($key)) {
            return null;
        }

        return $this->encryptor->decrypt(trim($key));
    }

    /**
     * Retrieve flag for notifications_ip_check
     *
     * @param int $storeId
     * @return bool
     */
    public function getNotificationsIpCheck(int $storeId = null): bool
    {
        return (bool) $this->getConfigData(
            self::XML_NOTIFICATIONS_IP_CHECK,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
        );
    }

    public function isDemoMode($storeId = null): bool
    {
        return $this->getConfigData('demo_mode', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    /**
     * Get how the alternative payment should be tokenized
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function getAlternativePaymentMethodTokenType($storeId = null)
    {
        return $this->getConfigData('token_type', self::XML_ADYEN_HPP, $storeId);
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isAlternativePaymentMethodsEnabled($storeId = null): bool
    {
        return $this->getConfigData('active', Config::XML_ADYEN_HPP, $storeId, true);
    }

    /**
     * Check if alternative payment methods vault is enabled
     *
     * @param null|int|string $storeId
     * @return mixed
     */
    public function isStoreAlternativePaymentMethodEnabled($storeId = null)
    {
        return $this->getConfigData('active', self::XML_ADYEN_HPP_VAULT, $storeId, true);
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
        return $this->getConfigData(self::XML_HAS_HOLDER_NAME, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
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
        return $this->getConfigData(self::XML_HOLDER_NAME_REQUIRED, self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
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
     * Determine whether or not to send additional riskdata properties in /payments and /authorize requests
     * @param $storeId
     * @return bool
     */
    public function sendAdditionalRiskData($storeId): bool
    {
        return $this->getConfigData('send_additional_risk_data', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    public function sendLevel23AdditionalData($storeId): bool
    {
        return $this->getConfigData('send_level23_data', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    /**
     * @param $storeId
     * @return bool|null
     */
    public function getCardRecurringActive($storeId): ?bool
    {
        return $this->getConfigData('active', self::XML_ADYEN_ONECLICK, $storeId, true);
    }

    /**
     * @param $storeId
     * @return string|null
     */
    public function getCardRecurringMode($storeId): ?string
    {
        return $this->getConfigData('card_mode', self::XML_ADYEN_ONECLICK, $storeId);
    }

    /**
     * @param $storeId
     * @return string|null
     */
    public function getCardRecurringType($storeId): ?string
    {
        return $this->getConfigData('card_type', self::XML_ADYEN_ONECLICK, $storeId);
    }

    public function isClickToPayEnabled($storeId): ?bool
    {
        return $this->getConfigData('enable_click_to_pay', self::XML_ADYEN_CC, $storeId);
    }

    public function getTokenizedPaymentMethods($storeId)
    {
        return $this->getConfigData('tokenized_payment_methods', self::XML_ADYEN_HPP, $storeId);
    }

    public function debugLogsEnabled($storeId): bool
    {
        return $this->getConfigData('debug', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    public function getAutoCaptureOpenInvoice(int $storeId): bool
    {
        return $this->getConfigData('auto_capture_openinvoice', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    public function getSupportMailAddress(int $storeId): ?string
    {
        return $this->getConfigData('adyen_support_email_address', self::XML_ADYEN_SUPPORT_PREFIX, $storeId);
    }

    public function getAdyenPosCloudConfigData(string $field, int $storeId = null, bool $flag = false)
    {
        return $this->getConfigData($field, self::XML_ADYEN_POS_CLOUD, $storeId, $flag);
    }

    public function useQueueProcessor($storeId = null): bool
    {
        return $this->getConfigData(
            self::XML_WEBHOOK_NOTIFICATION_PROCESSOR,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        ) === NotificationProcessor::QUEUE;
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function getConfigurationMode(int $storeId): string
    {
        return $this->getConfigData(
            self::XML_CONFIGURATION_MODE,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    /**
     * Returns the preferred ThreeDS authentication type for card and card vault payments.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getThreeDSFlow(int $storeId = null): string
    {
        return $this->getConfigData(
            self::XML_THREEDS_FLOW,
            self::XML_ADYEN_CC,
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
    public function getConfigData($field, $xmlPrefix, $storeId, $flag = false)
    {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);

        if (!$flag) {
            return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    public function setConfigData($value, $field, $xmlPrefix, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
    {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);
        $this->configWriter->save($path, $value, $scope);
    }
}
