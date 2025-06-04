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

/**
 * Class Moto
 * @package Adyen\Payment\Model\Config\Backend
 */
class Moto extends \Magento\Framework\App\Config\Value
{
    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    /**
     * @var \Magento\Framework\Math\Random
     */
    protected $mathRandom;

    /**
     * Moto constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->encryptor = $encryptor;
        $this->mathRandom = $mathRandom;
        $this->serializer = $serializer;
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
