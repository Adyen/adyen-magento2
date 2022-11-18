<?php declare(strict_types=1);

namespace Adyen\Payment\Controller\Adminhtml\Support;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Settings extends Action
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
        $resultPage->setActiveMenu('Adyen_Payment::support')
            ->getConfig()->getTitle()->prepend(__('Adyen Support'));

        $resultPage->getConfig()->getTitle()->prepend(__('Settings'));

        return $resultPage;
    }
}
