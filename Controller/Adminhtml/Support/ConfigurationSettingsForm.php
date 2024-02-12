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

class ConfigurationSettingsForm extends Action
{
    const CONFIGURATION_SETTINGS_EMAIL_TEMPLATE = 'configuration_settings_email_template';

    /**
     * @var SupportFormHelper
     */
    protected $supportFormHelper;

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
                    'topic',
                    'subject',
                    'email'
                ];
                $request = $this->getRequest()->getParams();
                $requiredFieldMissing = $this->supportFormHelper->requiredFieldsMissing($request, $requiredFields);
                if (!empty($requiredFieldMissing)) {
                    $this->messageManager->addErrorMessage(__('Error during form submission!
                    Missing required field(s): ' . $requiredFieldMissing));
                    return $this->_redirect('adyen/support/configurationsettingsform');
                }

                $configurationFormTopics = $this->supportFormHelper->getSupportTopicsByFormType(
                    SupportFormHelper::CONFIGURATION_SETTINGS_FORM
                );
                $request['topic'] = $configurationFormTopics[$request['topic']] ?? $request['topic'];

                $configurationFormIssues = $this->supportFormHelper->getIssuesTopicsByFormType(
                    SupportFormHelper::CONFIGURATION_SETTINGS_FORM
                );
                $request['issue'] = $configurationFormIssues[$request['issue']] ?? $request['issue'];

                $formData = [
                    'topic' => sprintf("Configuration settings / %s", $request['topic']),
                    'issue' => $request['issue'],
                    'subject' => $request['subject'],
                    'email' => $request['email'],
                    'headless' => $request['headless'],
                    'descriptionComments' => $request['descriptionComments'],
                    'sendConfigurationValues' => $request['sendConfigurationValues'],
                    'attachments' => $this->getRequest()->getFiles('attachments'),
                ];
                $this->supportFormHelper->handleSubmit($formData, self::CONFIGURATION_SETTINGS_EMAIL_TEMPLATE);
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
