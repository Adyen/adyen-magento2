<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Adyen\Payment\Controller\Adminhtml\Support\ConfigurationData;

class ConfigurationSettingsForm extends Action
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * @var ConfigurationData
     */
    protected $configurationData;

    public function __construct(
        Context          $context,
        TransportBuilder $transportBuilder,
        ConfigurationData $configurationData
    )
    {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->configurationData = $configurationData;
    }

    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Configuration settings'));
        if ('POST' === $this->getRequest()->getMethod()) {
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
        $configurationData = $this->configurationData->getConfigData();
        $templateVars = [
            'topic' => $request['topic'],
            'issue' => $request['issue'],
            'subject' => $request['subject'],
            'email' => $request['email'],
            'headless' => $request['headless'],
            'descriptionComments' => $request['descriptionComments']
        ];

        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
            'store' => 1
        ];
        $from = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        $to = ['email' => 'test@test.com', 'name' => 'Adyen test'];
        //the template identifier is set in the etc/email_templates.xml
        $transport = $this->transportBuilder->setTemplateIdentifier('configuration_settings_email_template')
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to)
            ->getTransport();
        //$transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }
}
