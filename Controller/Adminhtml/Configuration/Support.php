<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Configuration;

use Adyen\Payment\Model\Email\TransportBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Support extends Action
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

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
            $this->save();
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
        //$test = $transport->getMessage()->getBody();
        //$transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));

    }
}
