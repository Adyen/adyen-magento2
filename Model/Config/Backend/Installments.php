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

namespace Adyen\Payment\Model\Config\Backend;

class Installments extends \Magento\Framework\App\Config\Value
{
    /**
     * @var \Magento\Framework\Math\Random
     */
    protected $mathRandom;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Math\Random $mathRandom
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Math\Random $mathRandom,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->mathRandom = $mathRandom;
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Prepare data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (!is_array($value)) {
            return $this;
        }
        $result = [];
        foreach ($value as $data) {
            if (!$data) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }
            if (count($data) < 2) {
                continue;
            }

            $amount = $data['amount'];
            $installments = $data['installments'];
            $ccTypes = $data['cc_types'];

            foreach ($ccTypes as $ccType) {
                $result[$ccType][$amount] = $installments;
            }
        }

        // sort on installments
        $finalResult = [];
        foreach ($result as $key => $installments) {
            asort($installments);
            $finalResult[$key] = $installments;
        }

        $this->setValue($this->serializer->serialize($finalResult));
        return $this;
    }

    /**
     * Process data after load
     *
     * @return $this
     */
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

    /**
     * Encode value to be used in \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
     *
     * @param array $value
     * @return array
     */
    protected function encodeArrayFieldValue(array $value)
    {
        $result = [];

        // first combine the ccTypes together
        $list = [];
        foreach ($value as $ccType => $items) {
            // sort on amount
            ksort($items);

            foreach ($items as $amount => $installment) {
                if (!isset($list[$installment][$amount])) {
                    $list[$installment][$amount] = [$ccType];
                } else {
                    $ccTypes = $list[$installment][$amount];
                    $ccTypes[] = $ccType;
                    $list[$installment][$amount] = $ccTypes;
                }
            }
        }

        // loop through combined ccTypes configuration and pre fill the items
        foreach ($list as $installment => $amounts) {
            foreach ($amounts as $amount => $ccTypes) {
                $resultId = $this->mathRandom->getUniqueHash('_');
                $result[$resultId] = ['amount' => $amount, 'cc_types' => $ccTypes, 'installments' => $installment];
            }
        }

        return $result;
    }
}
