<?php
/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;

class WebhookCredentials extends Value
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;
    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ManagementHelper $managementApiHelper,
        Config $configHelper,
        UrlInterface $url,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->managementApiHelper = $managementApiHelper;
        $this->configHelper = $configHelper;
        $this->url = $url;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        if ($this->getFieldsetDataValue('configuration_mode') === 'auto' &&
            $this->getFieldsetDataValue('create_new_webhook') === '1') {
            $username = $this->getValue();
            $password = $this->getFieldsetDataValue('notification_password');

            $webhookUrl = $this->url->getBaseUrl() . 'adyen/webhook';
            $isDemoMode = (int)$this->getFieldsetDataValue('demo_mode');
            $environment = $isDemoMode ? 'test' : 'live';

            $apiKey = $this->getFieldsetDataValue('api_key_' . $environment);
            if (isset($apiKey) && preg_match('/^\*+$/', $apiKey)) {
                // API key contains '******', set to the previously saved config value
                $apiKey = $this->configHelper->getApiKey($environment);
            }
            $merchantAccount = $this->getFieldsetDataValue('merchant_account_auto');

            $managementApiService = $this->managementApiHelper->getManagementApiService($apiKey, $isDemoMode);
            $this->managementApiHelper->setupWebhookCredentials(
                $merchantAccount,
                $username,
                $password,
                $webhookUrl,
                $isDemoMode,
                $managementApiService
            );
        }

        return parent::beforeSave();
    }
}
