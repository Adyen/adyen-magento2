<?php declare(strict_types=1);
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Controller\Adminhtml\Support;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Adyen\Payment\Helper\SupportFormHelper;

class OtherTopicsForm extends Action
{
    const OTHER_TOPICS = 'other_topics_email_template';

    /**
     * @var SupportFormHelper
     */
    private $supportFormHelper;

    /**
     * @param Context $context
     * @param SupportFormHelper $supportFormHelper
     */
    public function __construct(Context $context, SupportFormHelper $supportFormHelper)
    {
        $this->supportFormHelper = $supportFormHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Adyen Support Form'));

        if ('POST' === $this->getRequest()->getMethod()) {
            try {
                $requiredFields = [
                    'subject',
                    'email',
                    'pspReference',
                    'merchantReference'
                ];
                $request = $this->getRequest()->getParams();
                $requiredFieldMissing = $this->supportFormHelper->requiredFieldsMissing($request, $requiredFields);

                if (!empty($requiredFieldMissing)) {
                    $this->messageManager->addErrorMessage(__('Error during form submission!
                    Missing required field(s): ' . $requiredFieldMissing));
                    return $this->_redirect('adyen/support/othertopicsform');
                }

                $formData = [
                    'subject' => $request['subject'],
                    'topic' =>  'Other topics',
                    'email' => $request['email'],
                    'pspReference' => $request['pspReference'],
                    'merchantReference' => $request['merchantReference'],
                    'headless' => $request['headless'],
                    'terminalId' => $request['terminalId'],
                    'description' => $request['description'],
                    'sendConfigurationValues' => $request['sendConfigurationValues'],
                    'attachments' => $this->getRequest()->getFiles('attachments'),
                ];

                $this->supportFormHelper->handleSubmit($formData, self::OTHER_TOPICS);

                return $this->_redirect('*/*/success');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Unable to send support message. '
                    . $e->getMessage()));
                $this->_redirect($this->_redirect->getRefererUrl());
            }
        }

        return $resultPage;
    }
}
