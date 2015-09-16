<?php


namespace Adyen\Payment\Controller\Process;


use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Json
 * @package Adyen\Payment\Controller\Process
 */
class Cron extends \Magento\Framework\App\Action\Action
{


    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $_resultFactory;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->_resultFactory = $context->getResultFactory();
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {

        $cron = $this->_objectManager->create('Adyen\Payment\Model\Cron');
        $cron->processNotification();
        die();


    }

}