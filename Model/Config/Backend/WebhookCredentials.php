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

use Adyen\AdyenException;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\ManagementHelper;
use Adyen\Service\Management\WebhooksMerchantLevelApi;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;

class WebhookCredentials extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagementHelper $managementApiHelper
     * @param Config $configHelper
     * @param UrlInterface $url
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ManagementHelper $managementApiHelper,
        private readonly Config $configHelper,
        private readonly UrlInterface $url,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return WebhookCredentials
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function beforeSave(): WebhookCredentials
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
            $client = $this->managementApiHelper->getAdyenApiClient($apiKey, $isDemoMode);
            $service = new WebhooksMerchantLevelApi($client);

            $this->managementApiHelper->setupWebhookCredentials(
                $merchantAccount,
                $username,
                $password,
                $webhookUrl,
                $isDemoMode,
                $service
            );
        }

        return parent::beforeSave();
    }
}
