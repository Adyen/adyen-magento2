<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class OrderProcessingForm extends Action
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    public function __construct(
        Context          $context,
        TransportBuilder $transportBuilder
    )
    {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Order processing'));
        if ('POST' === $this->getRequest()->getMethod()){
            try {
                $this->handleSubmit();
                return $this->_redirect('*/*/success');
            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted'));
            }
        }

        return $resultPage;
    }

    private function handleSubmit()
    {
        $request = $this->getRequest()->getParams();
        $templateVars = [
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

        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
            'store' => 1
        ];
        $from = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        $to = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        //the template identifier is set in the etc/email_templates.xml
        $transport = $this->transportBuilder->setTemplateIdentifier('order_processing_email_template')
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to)
            ->getTransport();
        //$transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }
}
