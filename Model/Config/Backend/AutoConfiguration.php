<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\AdyenException;
use Adyen\Payment\Helper\BaseUrlHelper;
use Adyen\Payment\Helper\ManagementHelper;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;

class AutoConfiguration extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ManagementHelper $managementApiHelper
     * @param UrlInterface $url
     * @param BaseUrlHelper $baseUrlHelper
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
        private readonly UrlInterface $url,
        private readonly BaseUrlHelper $baseUrlHelper,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return AutoConfiguration
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    public function beforeSave(): AutoConfiguration
    {
        if ('auto' === $this->getValue()) {
            $this->saveAllowedOrigins();
        }
        return parent::beforeSave();
    }

    /**
     * @return void
     * @throws AdyenException
     * @throws NoSuchEntityException
     */
    private function saveAllowedOrigins(): void
    {
        $demoMode = (int)$this->getFieldsetDataValue('demo_mode');
        $environment = $demoMode ? 'test' : 'live';

        $apiKey = $this->getFieldsetDataValue('api_key_' . $environment);
        $client = $this->managementApiHelper->getAdyenApiClient($apiKey, $demoMode);
        $service = $this->managementApiHelper->getMyAPICredentialApi($client);
        $configuredOrigins = $this->managementApiHelper->getAllowedOrigins($service);

        $domain = $this->baseUrlHelper->getDomainFromUrl($this->url->getBaseUrl());
        if (!in_array($domain, $configuredOrigins)) {
            $this->managementApiHelper->saveAllowedOrigin($service, $domain);
        }
    }
}
