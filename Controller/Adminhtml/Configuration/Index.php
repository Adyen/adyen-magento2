<?php

use Magento\Backend\App\Action\Context;
use Adyen\Payment\Model\Email\TransportBuilder;

class Index extends \Magento\Backend\App\Action
{
    private TransportBuilder $transportBuilder;

    public function __construct(
        Context          $context,
        TransportBuilder $transportBuilder)
    {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
    }

    public function execute()
    {
        $this->transportBuilder->sendEmail();
    }
}
