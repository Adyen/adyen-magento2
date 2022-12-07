<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class ConfigurationSettingsForm extends Action
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Configuration settings'));

        if ('POST' === $this->getRequest()->getMethod()) {
            $this->handleSubmit();
        }

        return $resultPage;
    }

    private function handleSubmit()
    {
        $topic = $this->getRequest()->getParam('topic');
        $issue = $this->getRequest()->getParam('issue');
        $subject = $this->getRequest()->getParam('subject');
        $email = $this->getRequest()->getParam('email');
        $headless = $this->getRequest()->getParam('headless');
        $description = $this->getRequest()->getParam('description');

        // TODO Process form data
    }
}
