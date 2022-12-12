<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Adyen\Payment\Helper\SupportFormHelper;

class OrderProcessingForm extends Action
{
    const ORDER_PROCESSING = 'order_processing_email_template';
    /**
     * @var SupportFormHelper
     */
    private $supportFormHelper;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    public function __construct(
        Context          $context,
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
            ->getConfig()->getTitle()->prepend(__('Order processing'));

        if ('POST' === $this->getRequest()->getMethod()){
            try {
                $request = $this->getRequest()->getParams();
                $formData = [
                    'topic' => $request['topic'],
                    'subject' => $request['subject'],
                    'email' => $request['email'],
                    'pspReference' => $request['pspReference'],
                    'merchantReference' => $request['merchantReference'],
                    'headless' => $request['headless'],
                    'paymentMethod' => $request['paymentMethod'],
                    'terminalId' => $request['terminalId'],
                    'orderHistoryComments' => $request['orderHistoryComments'],
                    'orderDescription' => $request['orderDescription']
                ];
                $this->supportFormHelper->handleSubmit($formData, self::ORDER_PROCESSING);
                return $this->_redirect('*/*/success');


            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted'));
            }
        }

        return $resultPage;
    }
}
