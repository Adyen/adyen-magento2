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
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\Payment\Helper\ManagementHelper;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class WebhookTest extends Action
{
    /**
     * @var ManagementHelper
     */
    private $managementApiHelper;

    public function __construct(
        Context $context,
        ManagementHelper $managementApiHelper

    ) {
        parent::__construct($context);
        $this->managementApiHelper = $managementApiHelper;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute(){
        $response = $this->managementApiHelper->webhookTest();
    }
}