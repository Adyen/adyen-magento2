/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (ko, $, errorProcessor) {
        'use strict';

        return {
            showModal: function(
                adyenPaymentService,
                fullScreenLoader,
                messageContainer,
                orderId,
                modalLabel,
                callback=(ko.observable(true)),
                openDefault = true
            ) {
                let popupModal = $('#' + modalLabel).modal({
                    // disable user to hide popup
                    clickableOverlay: false,
                    // disable escape key to hide popup
                    keyEventHandlers: {
                        escapeKey: function () { return; }
                    },
                    responsive: true,
                    innerScroll: false,
                    // empty buttons, we don't need that
                    buttons: [],
                    modalClass: modalLabel
                });

                if (openDefault) {
                    popupModal.modal('openModal');
                }

                return popupModal;
            },
            /**
             * This method is a workaround to close the modal in the right way and reconstruct the threeDS2Modal.
             * This will solve issues when you cancel the 3DS2 challenge and retry the payment
             */
            closeModal: function(modal, modalLabel) {
                modal.modal('closeModal');
                $('.' + modalLabel).remove();
                $('.modals-overlay').remove();
                $('body').removeClass('_has-modal');

                // reconstruct the threeDS2Modal container again otherwise component can not find the threeDS2Modal
                $('#' + modalLabel + 'Wrapper').
                append('<div id=' + modalLabel + '>' +
                    '<div id=' + (modalLabel + "Content") + '></div>' +
                    '</div>');
            },
            hideModalLabel: function (modalLabel) {
                $('.' + modalLabel).css('display','none');
            }
        };
    }
);
