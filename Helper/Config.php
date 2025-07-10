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
    const XML_PAYMENT_METHODS_ACTIVE= "payment_methods_active";
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
    const XML_ADYEN_RATEPAY = 'adyen_ratepay';
    const XML_PAYMENT_ORIGIN_URL = 'payment_origin_url';
    const XML_PAYMENT_RETURN_URL = 'payment_return_url';
    const XML_STATUS_FRAUD_MANUAL_REVIEW = 'fraud_manual_review_status';
    const XML_STATUS_FRAUD_MANUAL_REVIEW_ACCEPT = 'fraud_manual_review_accept_status';
    const XML_MOTO_MERCHANT_ACCOUNTS = 'moto_merchant_accounts';
    const XML_CONFIGURATION_MODE = 'configuration_mode';
    const XML_ADYEN_POS_CLOUD = 'adyen_pos_cloud';
    const XML_PAYMENT_ACTION = 'payment_action';
    const XML_WEBHOOK_NOTIFICATION_PROCESSOR = 'webhook_notification_processor';
    const AUTO_CAPTURE_OPENINVOICE = 'auto';
    const XML_RECURRING_CONFIGURATION = 'recurring_configuration';
    const XML_ALLOW_MULTISTORE_TOKENS = 'allow_multistore_tokens';
    const XML_THREEDS_FLOW = 'threeds_flow';
    const XML_REMOVE_PROCESSED_WEBHOOKS = 'remove_processed_webhooks';
    const XML_PROCESSED_WEBHOOK_REMOVAL_TIME = 'processed_webhook_removal_time';

    protected ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;
    private WriterInterface $configWriter;
    private SerializerInterface $serializer;

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

    public function getIsPaymentMethodsActive($storeId = null): bool
    {
        return $this->getConfigData(
            self::XML_PAYMENT_METHODS_ACTIVE,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
            ) === '1';
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
        return $this->encryptor->decrypt(trim((string) $key));
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

        return $this->encryptor->decrypt(trim((string) $key));
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
     * @param $storeId
     * @return bool|mixed
     * @deprecated
     */
    public function isAlternativePaymentMethodsEnabled($storeId = null): bool
    {
        return $this->getConfigData('active', Config::XML_ADYEN_HPP, $storeId, true);
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

    public function isClickToPayEnabled($storeId): ?bool
    {
        return $this->getConfigData('enable_click_to_pay', self::XML_ADYEN_CC, $storeId);
    }

    public function debugLogsEnabled($storeId): bool
    {
        return $this->getConfigData('debug', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId, true);
    }

    public function getAutoCaptureOpenInvoice(int $storeId): bool
    {
        $captureForOpenInvoice = $this->getConfigData(
            'capture_for_openinvoice',
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
        return $captureForOpenInvoice === self::AUTO_CAPTURE_OPENINVOICE;
    }

    public function getSupportMailAddress(int $storeId): ?string
    {
        return $this->getConfigData('adyen_support_email_address', self::XML_ADYEN_ABSTRACT_PREFIX, $storeId);
    }

    public function getAdyenPosCloudConfigData(string $field, int $storeId = null, bool $flag = false)
    {
        return $this->getConfigData($field, self::XML_ADYEN_POS_CLOUD, $storeId, $flag);
    }

    public function getAdyenPosCloudPaymentAction(int $storeId): string
    {
        return $this->getAdyenPosCloudConfigData(self::XML_PAYMENT_ACTION, $storeId);
    }

    public function useQueueProcessor($storeId = null): bool
    {
        return $this->getConfigData(
            self::XML_WEBHOOK_NOTIFICATION_PROCESSOR,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        ) === NotificationProcessor::QUEUE;
    }

    public function getConfigurationMode(int $storeId): string
    {
        return $this->getConfigData(
            self::XML_CONFIGURATION_MODE,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId
        );
    }

    public function getAdyenAbstractConfigData(string $field, int $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId);
    }

    public function getLiveEndpointPrefix(int $storeId = null): ?string
    {
        $prefix = $this->getAdyenAbstractConfigData('live_endpoint_url_prefix', $storeId);

        if (is_null($prefix)) {
            return null;
        }

        return trim($prefix);
    }

    public function getAdyenAbstractConfigDataFlag($field, $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_abstract', $storeId, true);
    }

    public function getAdyenCcConfigData($field, $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_cc', $storeId);
    }

    public function getAdyenHppConfigData($field, $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_hpp', $storeId);
    }

    public function getAdyenHppVaultConfigDataFlag($field, $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_hpp_vault', $storeId, true);
    }

    public function isHppVaultEnabled($storeId = null): mixed
    {
        return $this->getAdyenHppVaultConfigDataFlag('active', $storeId);
    }

    public function getAdyenOneclickConfigData($field, int $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId);
    }

    public function getAdyenOneclickConfigDataFlag($field, int $storeId = null): bool
    {
        return $this->getConfigData($field, 'adyen_oneclick', $storeId, true);
    }

    public function isPerStoreBillingAgreement(int $storeId): bool //Only use of Flag above
    {
        return !$this->getAdyenOneclickConfigDataFlag('share_billing_agreement', $storeId);
    }

    public function getAdyenBoletoConfigData(string $field, int $storeId = null): mixed
    {
        return $this->getConfigData($field, 'adyen_boleto', $storeId);
    }

    public function getCheckoutFrontendRegion(int $storeId = null): ?string
    {
        $checkoutFrontendRegion = $this->getAdyenAbstractConfigData('checkout_frontend_region', $storeId);

        if (is_null($checkoutFrontendRegion)) {
            return null;
        }

        return trim($checkoutFrontendRegion);
    }

    public function getRatePayId(int $storeId = null)
    {
        return $this->getConfigData("ratepay_id", self::XML_ADYEN_RATEPAY, $storeId);
    }

    public function getAllowMultistoreTokens(int $storeId = null): ?bool
    {
        return $this->getConfigData(
            self::XML_ALLOW_MULTISTORE_TOKENS,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            $storeId,
            true
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
     * Indicates whether if the processed webhook removal cronjob is enabled or not.
     *
     * This field can only be configured on default scope level as
     * the notification table doesn't have nay relation with the stores.
     *
     * @return bool
     */
    public function getIsProcessedWebhookRemovalEnabled(): bool
    {
        return $this->getConfigData(
            self::XML_REMOVE_PROCESSED_WEBHOOKS,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            null,
            true
        );
    }

    /**
     * Returns the configured amount of days a webhook has to be older than in order to be removed.
     *
     * This field can only be configured on default scope level as
     * the notification table doesn't have any relation with the stores.
     *
     * @return int
     */
    public function getProcessedWebhookRemovalTime(): int
    {
        return (int) $this->getConfigData(
            self::XML_PROCESSED_WEBHOOK_REMOVAL_TIME,
            self::XML_ADYEN_ABSTRACT_PREFIX,
            null
        );
    }

    public function getIsCvcRequiredForRecurringCardPayments(int $storeId = null): bool
    {
         return (bool) $this->getConfigData(
            'require_cvc',
            Config::XML_ADYEN_CC_VAULT,
            $storeId,
            true
        );
    }

    public function getConfigData(string $field, string $xmlPrefix, ?int $storeId, bool $flag = false): mixed
    {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);

        if (!$flag) {
            return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    public function setConfigData(
        $value,
        string $field,
        string $xmlPrefix,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): void {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);
        $this->configWriter->save($path, $value, $scope, $scopeId);
    }

    /**
     * Deletes config data
     * @param string $field
     * @param string $xmlPrefix
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    public function removeConfigData(
        string $field,
        string $xmlPrefix,
        string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): void {
        $path = implode("/", [self::XML_PAYMENT_PREFIX, $xmlPrefix, $field]);
        $this->configWriter->delete($path, $scope, $scopeId);
    }
}
