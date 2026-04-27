<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

class PaymentMethodTitles extends Value
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param Random $mathRandom
     * @param SerializerInterface $serializer
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
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Serialize the dynamic table rows before saving to the database.
     * Throws an exception if duplicate payment method types are detected.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave(): static
    {
        $value = $this->getValue();

        if (!is_array($value)) {
            return $this;
        }

        $result = [];

        foreach ($value as $rowData) {
            if (!is_array($rowData)) {
                continue;
            }

            $type = trim((string) ($rowData['payment_method_type'] ?? ''));
            $title = trim((string) ($rowData['title'] ?? ''));

            if ($type === '' || $title === '') {
                continue;
            }

            if (isset($result[$type])) {
                throw new LocalizedException(
                    __('Duplicate payment method override: "%1". Each payment method can only have one title override.', $type)
                );
            }

            $result[$type] = $title;
        }

        $this->setValue($this->serializer->serialize($result));

        return $this;
    }

    /**
     * Deserialize the stored value after loading from the database so it can
     * be rendered by the AbstractFieldArray dynamic table.
     *
     * @return $this
     */
    protected function _afterLoad(): static
    {
        $value = $this->getValue();

        if (empty($value)) {
            return $this;
        }

        $decoded = $this->serializer->unserialize($value);

        if (!is_array($decoded)) {
            return $this;
        }

        $this->setValue($this->encodeArrayFieldValue($decoded));

        return $this;
    }

    /**
     * Convert the stored `{type: title}` map into the row-keyed format that
     * AbstractFieldArray expects for pre-population.
     *
     * @param array $value
     * @return array
     */
    protected function encodeArrayFieldValue(array $value): array
    {
        $result = [];

        foreach ($value as $type => $title) {
            $id = $this->mathRandom->getUniqueHash('_');
            $result[$id] = [
                'payment_method_type' => $type,
                'title'               => $title,
            ];
        }

        return $result;
    }
}
