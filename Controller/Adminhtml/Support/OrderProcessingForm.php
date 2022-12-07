<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class OrderProcessingForm extends Action
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Order processing'));

        if ('POST' === $this->getRequest()->getMethod()) {
            $this->handleSubmit();
        }

        return $resultPage;
    }

    private function handleSubmit()
    {
        $topic = $this->getRequest()->getParam('topic');
        $subject = $this->getRequest()->getParam('subject');
        $email = $this->getRequest()->getParam('email');
        $pspReference = $this->getRequest()->getParam('pspReference');
        $merchantReference = $this->getRequest()->getParam('merchantReference');
        $headless = $this->getRequest()->getParam('headless');
        $paymentMethod = $this->getRequest()->getParam('paymentMethod');
        $terminalId = $this->getRequest()->getParam('terminalId');
        $orderHistoryComments = $this->getRequest()->getParam('orderHistoryComments');
        $description = $this->getRequest()->getParam('description');

        // TODO Process form data
    }
}
