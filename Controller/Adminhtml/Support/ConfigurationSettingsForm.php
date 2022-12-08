<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class ConfigurationSettingsForm extends Action
{
    const CONFIGURATION_SETTINGS_EMAIL_TEMPLATE = 'configuration_settings_email_template';
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
                $request = $this->getRequest()->getParams();
                $formData = [
                    'topic' => $request['topic'],
                    'issue' => $request['issue'],
                    'subject' => $request['subject'],
                    'email' => $request['email'],
                    'headless' => $request['headless'],
                    'descriptionComments' => $request['descriptionComments']
                ];
                $this->handleSubmit($formData, self::CONFIGURATION_SETTINGS_EMAIL_TEMPLATE);
                return $this->_redirect('*/*/success');
            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(__('Form unsuccessfully submitted'));
            }
        }
        return $resultPage;
    }

    /**
     * @param array $formData
     * @param string $template
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     */
    private function  handleSubmit(array $formData, string $template) : void
    {
        $configurationData = $this->configurationData->getConfigData();
        $templateVars = array_merge($configurationData, $formData);
        $templateOptions = [
            'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
            'store' => $configurationData['storeId']
        ];

        $from = ['email' => 'alexandros.moraitis@adyen.com', 'name' => 'Adyen test'];
        $to = ['email' => 'alexandros.moraitis@adyen.com', 'name' => 'Adyen test'];

        $transport = $this->transportBuilder->setTemplateIdentifier(self::CONFIGURATION_SETTINGS_EMAIL_TEMPLATE,)
            ->setTemplateOptions($templateOptions)
            ->setTemplateVars($templateVars)
            ->setFromByScope($from)
            ->addTo($to)
            ->getTransport();
        $transport->sendMessage();
        $this->messageManager->addSuccess(__('Form successfully submitted'));
    }
}
