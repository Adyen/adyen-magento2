<?php declare(strict_types=1);

namespace Adyen\Payment\Block\Adminhtml\Support\Edit;

use Magento\Backend\Block\Widget\Form\Generic;

class Form extends Generic
{
    /**
     * Prepare form before rendering HTML
     */
    protected function _prepareForm(): self
    {
        $form = $this->_formFactory->create([
            'data' => [
                'id' => 'support_form',
                'action' => $this->getData('action'),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            ]
        ]);
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
