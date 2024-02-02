<?php

namespace Adyen\Payment\Setup;

use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\PaymentMethodsFactory;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class Recurring implements InstallSchemaInterface
{
    private PaymentMethodsFactory $paymentMethodsFactory;

    public function __construct(
        PaymentMethodsFactory $paymentMethodsFactory,
    )
    {
        $this->paymentMethodsFactory = $paymentMethodsFactory;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $paymentMethods = $this->paymentMethodsFactory->create();
        /** @var PaymentMethods $paymentMethods */
        $paymentMethods->togglePaymentMethodsActivation();
        $setup->endSetup();
    }
}
