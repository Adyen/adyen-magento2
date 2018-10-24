var AdyenCheckout = (function () {
    'use strict';
    return {
        oneClickForms: [],
        dobDateFormat: "yy-mm-dd",
        securedFieldsData: {},
        allowedCards: [],
        cseEncryptedForm: null,
        checkout: null,

        updateCardType: function ( cardType, friendlyName ) {
            $( ".cse-cardtype" ).removeClass( "cse-cardtype-style-active" );

            if ( cardType === "unknown" ) {
                return;
            }

            var activeCard = document.getElementById( 'cse-card-' + cardType );
            if ( activeCard !== null ) {
                activeCard.className = "cse-cardtype cse-cardtype-style-active cse-cardtype-" + cardType;
            }
        },
        enableCardTypeDetection: function ( allowedCards, cardLogosContainer ) {
            var cardTypesHTML = "";
            this.allowedCards = allowedCards;

            for ( var i = allowedCards.length; i-- > 0; ) {
                cardTypesHTML = cardTypesHTML + this.getCardSpan( allowedCards[ i ] );
            }

            cardLogosContainer.innerHTML = cardTypesHTML;
        },
        getCardSpan: function ( type ) {
            return "<span id=\"cse-card-" + type + "\" class=\"cse-cardtype cse-cardtype-style-active cse-cardtype-" + type + "\"></span>";
        },
        getCardBrand: function () {
            if ( this.securedFieldsData.brand ) {
                return this.securedFieldsData.brand;
            }

            var creditCardNumberElement = document.getElementById( 'creditCardNumber' );
            if ( creditCardNumberElement ) {
                var creditCardNumber = creditCardNumberElement.value.replace( / /g, '' );
                return window.adyen.cardTypes.determine( creditCardNumber );
            }
        },
        isAllowedCard: function () {
            var brand = this.getCardBrand();
            return (brand !== null && this.allowedCards.indexOf( brand ) !== -1);
        },
        validateForm: function () {
            var paymentMethod = $( 'input[type=radio][name=paymentMethod]:checked' ).val();

            if ( paymentMethod === "" ) {
                window.alert( "Please select a payment method" );
                return false;
            }

            // Check if it is a valid card and encrypt
            if ( paymentMethod === "adyen_cc" ) {
                if ( !this.isAllowedCard() ) {
                    window.alert( 'This credit card is not allowed' );
                    return false;
                }

                if ( this.cseEncryptedForm ) {
                    // CSE
                    this.fillCSEToken( this.cseEncryptedForm );
                } else {
                    // SECURED FIELDS
                    $( 'input[name="encryptedCardNumber"]' ).val( this.securedFieldsData.new.cardNumber );
                    $( 'input[name="encryptedExpiryMonth"]' ).val( this.securedFieldsData.new.expiryMonth );
                    $( 'input[name="encryptedExpiryYear"]' ).val( this.securedFieldsData.new.expiryYear );
                    $( 'input[name="encryptedSecurityCode"]' ).val( this.securedFieldsData.new.securityCode );
                }
            } else if ( paymentMethod.indexOf( "adyen_oneclick_" ) === 0 ) {
                var recurringReference = paymentMethod.slice( "adyen_oneclick_".length );
                $( "#selectedReference" ).val( recurringReference );

                if ( this.oneClickForms[ recurringReference ] ) {
                    // CSE
                    this.fillCSEToken( this.oneClickForms[ recurringReference ] );
                } else {
                    $( 'input[name="encryptedSecurityCode"]' ).val( this.securedFieldsData[ recurringReference ].securityCode );
                }
            } else {
                // if issuerId is present let the customer select it
                var issuer = $( '#p_method_adyen_hpp_' + paymentMethod + '_issuer' );
                if ( issuer ) {
                    if ( issuer.val() === "" ) {
                        window.alert( 'Please select issuer' );
                        return false;
                    }
                }
            }

            // Remove SF injected elements
            $( '#encrypted-hostedCardNumberField' ).remove();
            $( '#encrypted-month' ).remove();
            $( '#encrypted-year' ).remove();
            $( '#encrypted-hostedSecurityCodeField' ).remove();
            $( 'input[name="txvariant"]' ).remove();

            return true;
        },

        fillCSEToken: function ( form ) {
            var cseToken = form.encrypt();
            if ( cseToken === false ) {
                window.alert( 'This credit card is not valid' );
                return false;
            }
            $( "#cseToken" ).val( cseToken );
        },

        /**
         * Set Custom values for certain payment methods
         */
        setCustomPaymentMethodValues: function () {
            var paymentMethod = $( 'input[type=radio][name=paymentMethod]:checked' ).val();

            var dob = $( '#p_method_adyen_hpp_' + paymentMethod + '_dob' );
            if ( dob ) {
                $( "#dob" ).val( dob.val() );
            }

            var ssn = $( '#p_method_adyen_hpp_' + paymentMethod + '_ssn' );
            if ( ssn ) {
                $( "#socialSecurityNumber" ).val( ssn.val() );
            }
        },

        createForm: function () {
            // The form element to encrypt.
            var form = document.getElementById( 'adyen-encrypted-form' );

            // Form and encryption options. See adyen.encrypt.simple.html for details.
            var options = {};
            options.cvcIgnoreBins = '6703'; // Ignore CVC for BCMC

            // Create the form.
            // Note that the method is on the adyen object, not the adyen.encrypt object.
            this.cseEncryptedForm = window.adyen.createEncryptedForm( form, options );

            this.cseEncryptedForm.addCardTypeDetection( this.updateCardType );

            return this.cseEncryptedForm;
        },

        createOneClickForm: function ( recurringReference ) {
            // The form element to encrypt.
            var form = document.getElementById( 'adyen-encrypted-form' );

            // Form and encryption options. See adyen.encrypt.simple.html for details.
            var options = {};
            options.fieldNameAttribute = "data-encrypted-name-" + recurringReference;
            options.enableValidations = false;

            // Create the form.
            // Note that the method is on the adyen object, not the adyen.encrypt object.
            var encryptedForm = window.adyen.createEncryptedForm( form, options );
            this.oneClickForms[ recurringReference ] = encryptedForm;

            return encryptedForm;
        },

        /**
         * Create DatePicker for Date of Birth field
         *
         * @param element
         */
        createDobDatePicker: function ( element ) {
            $( "." + element ).datepicker( {
                dateFormat: this.dobDateFormat,
                changeMonth: true,
                changeYear: true,
                yearRange: "-120:+0"
            } );
        },


        togglePaymentMethod: function ( paymentMethod ) {
            $( ".payment_method_details" ).hide();
            $( ".chckt-pm__details" ).hide();

            $( "#dd_method_" + paymentMethod ).show();
            $( "#adyen_hpp_" + paymentMethod + "_container" ).show();
        },

        createDfValue: function () {
            window.dfDo( "dfValue" );
        },

        createSecuredFieldsForm: function ( originKey, rootNode ) {
            var self = this;
            var securedFieldsConfiguration = {
                configObject: {
                    originKey: originKey,
                    cardGroupTypes: self.allowedCards
                },
                rootNode: rootNode
            };

            var securedFields = window.csf( securedFieldsConfiguration );
            securedFields.onBrand( function ( brandObject ) {
                if ( brandObject.brand ) {
                    self.securedFieldsData.brand = brandObject.brand;
                    self.updateCardType( brandObject.brand, null );
                }
            } );

            window.addEventListener( "message", this.securedFieldsListener( this ), false );

            return securedFields;
        },

        securedFieldsListener: function ( self ) {
            return function ( message ) {
                if ( !("data" in message) ) {
                    return;
                }
                try {
                    var data = JSON.parse( message.data );
                    if ( "encryptionSuccess" in data && data.encryptionSuccess === true ) {
                        var fieldName = data.cseKey;
                        var encryptedData = data[ fieldName ];
                        var securedFieldsData = self.securedFieldsData;
                        var parts = fieldName.split( "-" );
                        var card = "new";

                        if ( parts.length === 2 ) {
                            card = parts[ 1 ];
                        }

                        if ( !(card in securedFieldsData) ) {
                            securedFieldsData[ card ] = {};
                        }

                        switch ( data.fieldType ) {
                        case "hostedCardNumberField":
                            securedFieldsData[ card ].cardNumber = encryptedData;
                            break;
                        case "month":
                            securedFieldsData[ card ].expiryMonth = encryptedData;
                            break;
                        case "year":
                            securedFieldsData[ card ].expiryYear = encryptedData;
                            break;
                        case "hostedSecurityCodeField":
                            securedFieldsData[ card ].securityCode = encryptedData;
                            break;
                        }
                    }
                } catch (e) {
                    //not json data
                }
            };
        },
        initiateCheckout: function (locale) {
            var configuration = {
                locale: locale // shopper's locale
            };
            this.checkout = new Adyen.Checkout(configuration);
        },

        initiateIdeal: function (idealItems) {
            var idealNode = document.getElementById('adyen_hpp_ideal_container');
            var ideal = this.checkout.create('ideal', {
                items: idealItems, // The array of issuers coming from the /paymentMethods api call
                showImage: true, // Optional, defaults to true
                onChange: handleChange // Gets triggered whenever a user selects a bank// Gets triggered once the state is valid
            });

            function handleChange(event) {
                var issuerIdField = document.getElementById('issuerId');
                var issuerId = event.data.issuer;
                issuerIdField.value = issuerId;
            }

            try {
                ideal.mount(idealNode);
            } catch (e) {
                console.log('Something went wrong trying to mount the iDEAL component: ${e}');
            }
        }
    };
})();
