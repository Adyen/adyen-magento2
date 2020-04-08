<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\System\Config;

class Header extends \Magento\Config\Block\System\Config\Form\Fieldset implements
    \Magento\Framework\Data\Form\Element\Renderer\RendererInterface
{

    /**
     * Return header html for fieldset
     *
     * @param Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderHtml($element)
    {
        $this->isCollapsedDefault = true;
        if ($element->getIsNested()) {
            $html = '<tr class="nested"><td colspan="4"><div class="' . $this->_getFrontendClass($element) . '">';
        } else {
            $html = '<div class="' . $this->_getFrontendClass($element) . '">';
        }

        $html .= '<div class="entry-edit-head admin__collapsible-block">' .
            '<span id="' .
            $element->getHtmlId() .
            '-link" class="entry-edit-head-link"></span>';

        $html .= $this->_getHeaderTitleHtml($element);

        $html .= '</div>';
        $html .= '<input id="' .
            $element->getHtmlId() .
            '-state" name="config_state[' .
            $element->getId() .
            ']" type="hidden" value="' .
            (int)$this->_isCollapseState(
                $element
            ) . '" />';
        $html .= '<fieldset class="' . $this->_getFieldsetCss() . '" id="' . $element->getHtmlId() . '">';
        $html .= '<legend>' . $element->getLegend() . '</legend>';

        // field label column
        $html .= '<table cellspacing="0" class="form-list"><colgroup class="label" /><colgroup class="value" />';
        if ($this->getRequest()->getParam('website') || $this->getRequest()->getParam('store')) {
            $html .= '<colgroup class="use-default" />';
        }
        $html .= '<colgroup class="scope-label" /><colgroup class="" /><tbody>';

        return $html;
    }

    /**
     * Return header title part of html for fieldset
     *
     * @param Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div class="adyen-config-column adyen-config-logo"></div>';
        $html .= $this->_getHeaderCommentHtml($element);
        $html .= '<div class="adyen-config-column adyen-config-button">' . $this->_getConfigureButtonHtml($element) . '</div>';
        return $html;
    }

    /**
     * Return header comment part of html for fieldset
     *
     * @param Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        return $element->getComment() ? '<div class="adyen-config-column adyen-config-comment comment">' . $element->getComment() . '</div>' : '';
    }

    /**
     * Return header title part of html for fieldset
     *
     * @param Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getConfigureButtonHtml($element)
    {
        return '<a id="' .
            $element->getHtmlId() .
            '-head" href="#' .
            $element->getHtmlId() .
            '-link" onclick="Fieldset.toggleCollapse(\'' .
            $element->getHtmlId() .
            '\', \'' .
            $this->getUrl(
                '*/*/state'
            ) . '\'); return false;"><button class="button action-configure" type="button">Configure</button></a>';
    }

}
