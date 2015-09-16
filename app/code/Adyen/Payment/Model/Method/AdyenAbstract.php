<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Adyen\Payment\Model\Method;

use Magento\Framework\Object;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;
/**
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AdyenAbstract extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{
    const METHOD_CODE = 'adyen_hpp';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_isInitializeNeeded = false;

    /**
     * Post request to gateway and return response
     *
     * @param Object $request
     * @param ConfigInterface $config
     *
     * @return Object
     *
     * @throws \Exception
     */
    public function postRequest(Object $request, ConfigInterface $config)
    {
        // TODO: Implement postRequest() method.
    }


}