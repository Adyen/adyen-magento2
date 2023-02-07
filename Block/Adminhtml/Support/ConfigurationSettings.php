<?php
/**
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Adminhtml\Support;

use Adyen\Payment\Helper\SupportFormHelper;
use Magento\Backend\Block\Page;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Phrase;

class ConfigurationSettings extends Page implements SupportTabInterface
{
    /**
     * @var SupportFormHelper
     */
    private $supportFormHelper;

    public function __construct(
        Context $context,
        ResolverInterface $localeResolver,
        SupportFormHelper  $supportFormHelper,
        array $data = []
    ) {
        parent::__construct($context, $localeResolver, $data);
        $this->supportFormHelper = $supportFormHelper;
    }

    /**
     * @return string[]
     */
    public function getSupportTopics(): array
    {
        return $this->supportFormHelper->getSupportTopicsByFormType(
            SupportFormHelper::CONFIGURATION_SETTINGS_FORM
        );
    }

    /**
     * @return string
     */
    public function supportFormUrl(): string
    {
        return $this->getUrl('adyen/support/configurationsettingsform');
    }

    /**
     * @return Phrase
     */
    public function getPageTitle()
    {
        return __("Configuration Settings");
    }
}
