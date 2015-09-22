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
    const METHOD_CODE = 'adyen_abstract';

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
        // Not needed only used for global configuration settings
    }


}