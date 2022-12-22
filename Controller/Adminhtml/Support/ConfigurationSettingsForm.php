<?php declare(strict_types=1);

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

    public function __construct(
        Context           $context,
        SupportFormHelper $supportFormHelper
    )
    {
        $this->supportFormHelper = $supportFormHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Configuration settings'));
        if ('POST' === $this->getRequest()->getMethod()) {
            try {
                $requiredFields = [
                    'topic',
                    'subject',
                    'email',
                    'descriptionComments'
                ];
                $request = $this->getRequest()->getParams();
                $requiredFieldsMissing = $this->supportFormHelper->requiredFieldsMissing($request, $requiredFields);
                if(!empty($requiredFieldsMissing)){
                    $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted, Required field '.$requiredFieldsMissing.' is missing'));
                    return $this->supportFormUrl();
                }
                $formData = [
                    'topic' => $request['topic'],
                    'issue' => $request['issue'],
                    'subject' => $request['subject'],
                    'email' => $request['email'],
                    'headless' => $request['headless'],
                    'descriptionComments' => $request['descriptionComments']
                ];
                $this->supportFormHelper->handleSubmit($formData, self::CONFIGURATION_SETTINGS_EMAIL_TEMPLATE);
                return $this->_redirect('*/*/success');
            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted'));
                return $this->supportFormUrl();
            }
        }
        return $resultPage;
    }

    private function supportFormUrl(): \Magento\Framework\App\ResponseInterface
    {
        return $this->_redirect('adyen/support/configurationsettingsform');
    }


}
