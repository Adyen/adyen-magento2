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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
namespace Adyen\Payment\Model;

use Adyen\Payment\Api\Data\PaymentResponseInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\SerializerInterface;


class PaymentResponse extends AbstractModel implements PaymentResponseInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;


    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        SerializerInterface $serializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->_construct();
        $this->serializer = $serializer;
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    public function _construct(
    )
    {
        $this->_init(ResourceModel\PaymentResponse::class);
    }

    /**
     * @return array|int|mixed|null
     */
    public function getPaymentId()
    {
        return $this->getData(self::PAYMENT_ID);
    }

    /**
     * @param int $paymentId
     * @return PaymentResponse
     */
    public function setPaymentId(int $paymentId)
    {
        return $this->setData(self::PAYMENT_ID, $paymentId);
    }


    /**
     * @return array|mixed|null
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * @param int $storeId
     * @return PaymentResponse|mixed
     */
    public function setStoreId(int $storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @return mixed
     */
    public function getMerchantReference()
    {
        return $this->getData(self::MERCHANT_REFERENCE);
    }

    /**
     * @param string $merchantReference
     * @return mixed
     */
    public function setMerchantReference($merchantReference)
    {
        return $this->setData(self::MERCHANT_REFERENCE, $merchantReference);
    }

    /**
     * @return mixed
     */
    public function getResultCode()
    {
        return $this->getData(self::RESULT_CODE);
    }

    /**
     * @param string $resultCode
     * @return mixed
     */
    public function setResultCode($resultCode)
    {
        return $this->setData(self::RESULT_CODE, $resultCode);
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->getData(self::RESPONSE);
    }

    /**
     * @param string $response
     * @return mixed
     */
    public function setResponse($response)
    {
        return $this->setData(self::RESPONSE, $response);
    }

    /**
     * @return array|mixed|string|null
     */
    public function getAdditionalInformation() {
        return $this->serializer->unserialize($this->getData(self::ADDITIONAL_INFORMATION)); //TODO: Change to correct serializer
    }

    /**s
     * @param string[] $additionalInformation
     * @return PaymentResponse
     */
    public function setAdditionalInformation($additionalInformation)
    {
        return $this->setData(self::ADDITIONAL_INFORMATION, $this->serializer->serialize($additionalInformation)); //TODO: Change to correct serializer
    }

    /**
     * @param string $field
     * @param $additionalInformation
     * @return array|mixed|string
     */
    public function setAdditionalInformationByField(string $field, $additionalInformation) {
        $data = $this->getAdditionalInformation();
        if ($data !== null && array_key_exists($field, $data)) {
            // Special case if additionalInfo is a new object (e.g. additionalData)
            if(is_array($additionalInformation)) {
                $data[$field] = array_merge((array) $data[$field], (array) $additionalInformation);
            } else {
                $data[$field] = $additionalInformation;
            }
        } else {
            $data = (array)$data;
            $data[$field] = $additionalInformation;
        }

        return $this->setData(self::ADDITIONAL_INFORMATION, json_encode($data));
    }
}
