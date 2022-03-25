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
 * Copyright (c) 2022 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;

class Download extends \Magento\Backend\App\Action
{

    private $fileFactory;

    public function __construct(
        FileFactory $fileFactory,
        Context $context
    )
    {
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    /**
     *
     * @return \Magento\Framework\View\Result\Raw
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);

        return $resultPage;
    }
}
