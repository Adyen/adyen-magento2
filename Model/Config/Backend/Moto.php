<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Adyen\Payment\Model\Ui\Adminhtml\AdyenMotoConfigProvider;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class Moto
 * @package Adyen\Payment\Model\Config\Backend
 */
class Moto extends Value
{
    /**
     * Moto constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Random $mathRandom
     * @param SerializerInterface $serializer
     * @param EncryptorInterface $encryptor
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        protected readonly Random $mathRandom,
        private readonly SerializerInterface $serializer,
        private readonly EncryptorInterface $encryptor,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        if (!is_array($value)) {
            return $this;
        }
        $result = [];
        foreach ($value as $data) {
            if (!$data || !is_array($data) || count($data) < 2) {
                continue;
            }

            $merchantAccount = $data['merchant_account'];
            $clientKey = $data['client_key'];
            $enviroment = $data['demo_mode'];

            if ($data['api_key'] != AdyenMotoConfigProvider::API_KEY_PLACEHOLDER) {
                $apiKey = $data['api_key'];
                $apiKey = $this->encryptor->encrypt(trim((string) $apiKey));
            }
            else {
                $oldRowValue = $this->getOldValue();
                $oldRowValue = $this->serializer->unserialize($oldRowValue);
                $apiKey = $oldRowValue[$merchantAccount]['apikey'];
            }

            $result[$merchantAccount] = array(
                "clientkey" => $clientKey,
                "apikey" => $apiKey,
                "demo_mode" => $enviroment
            );
        }

        $this->setValue($this->serializer->serialize($result));
        return $this;
    }

    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (empty($value)) {
            return $this;
        }

        $value = $this->serializer->unserialize($value);
        $value = $this->encodeArrayFieldValue($value);
        $this->setValue($value);
        return $this;
    }

    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];
        foreach ($value as $merchantAccount => $items) {
            $resultId = $this->mathRandom->getUniqueHash('_');
            $result[$resultId] = [
                'merchant_account' => $merchantAccount,
                'client_key' => $items['clientkey'],
                // Set default value for API key in the frontend to prevent re-encryption in backend model
                'api_key' => AdyenMotoConfigProvider::API_KEY_PLACEHOLDER,
                'demo_mode' => $items['demo_mode']
            ];
        }
        return $result;
    }
}
