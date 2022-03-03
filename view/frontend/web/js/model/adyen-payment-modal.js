/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
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
            showModal: function(adyenPaymentService, fullScreenLoader, messageContainer, orderId, modalLabel, callback=(ko.observable(true))) {
                let popupModal = $('#' + modalLabel).modal({
                    // disable user to hide popup
                    clickableOverlay: false,
                    responsive: true,
                    innerScroll: false,
                    // empty buttons, we don't need that
                    buttons: [],
                    modalClass: modalLabel,
                    closed: function() {
                        // call endpoint with state.data if available
                        let request = {};
                        request.orderId = orderId;
                        request.cancelled = true;

                        adyenPaymentService.paymentDetails(request).fail(function(response) {
                            errorProcessor.process(response, messageContainer);
                            callback(true);
                            fullScreenLoader.stopLoader();
                        });
                    },
                });

                popupModal.modal('openModal');
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
            }
        };
    }
);
