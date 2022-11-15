<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Configuration;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class Support extends Action
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
        $resultPage->setActiveMenu('Adyen_Payment::configuration_support')
            ->getConfig()->getTitle()->prepend(__('Adyen Support'));
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            try {
                $this->save();
                return $this->_redirect('*/*/success');
            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted'));
            }
        }
        return $resultPage;
    }

    private function save()
    {
        $request = $this->getRequest()->getParams();
        $templateVars = [
            'pspReference' => $request['pspReference'],
            'merchantReference' => $request['merchantReference'],
            'headless' => $request['headless'],
            'paymentMethod' => $request['paymentMethod'],
            'terminalId' => $request['terminalId'],
            'orderHistoryComments' => $request['orderHistoryComments']
        ];

        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
            'store' => 1
        ];
        $from = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        $to = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        //the template identifier is set in the etc/email_templates.xml
        $transport = $this->transportBuilder->setTemplateIdentifier('contact_email_template')
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to)
            ->getTransport();
        //$transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }
}
