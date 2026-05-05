/**
 * Paythor SanalPosPro payment method renderer.
 *
 * This component extends Magento_Checkout/js/view/payment/default rather than
 * Magento_Payment/js/view/payment/cc-form by design: credit card data is never
 * collected on the merchant storefront. Instead, the component opens a secure
 * Paythor-hosted iframe modal where the customer enters card details directly
 * on Paythor's PCI-compliant payment page. This iframe/token flow eliminates
 * PCI scope for the merchant and is the recommended integration pattern.
 */
define([
    'jquery',
    'underscore',
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/set-payment-information',
    'Magento_Customer/js/model/customer',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/model/messageList',
    'mage/url',
    'mage/translate'
], function (
    $,
    _,
    ko,
    Component,
    quote,
    urlBuilder,
    fullScreenLoader,
    selectPaymentMethodAction,
    checkoutData,
    setPaymentInformationAction,
    customer,
    modal,
    globalMessageList,
    urlFormatter,
    $t
) {
    'use strict';

    var config = window.checkoutConfig.payment.paythor_sanalpospro || {};

    return Component.extend({
        defaults: {
            template: 'Paythor_SanalPosPro/payment/sanalpospro',
            redirectAfterPlaceOrder: false,
            paythorEndpoint: 'paythor/payment/create',
            paythorConfirmEndpoint: 'paythor/payment/confirm'
        },

        modalInstance: null,
        modalContainer: null,
        callbackBound: false,
        pendingQuoteId: null,
        paythorToken: ko.observable(null),

        isPlaceOrderActionAllowed: ko.observable(true),

        getCode: function () {
            return 'paythor_sanalpospro';
        },

        isActive: function () {
            return config.isActive === true;
        },

        isSandbox: function () {
            return config.isSandbox === true;
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    paythor_token: this.paythorToken() || '',
                    payment_method: 'credit_card'
                }
            };
        },

        /**
         * 1. Save payment/address info to Magento.
         * 2. Call paythor/payment/create to get the iframe HTML.
         * 3. Show the iframe modal.
         * 4. On payment success postMessage -> call paythor/payment/confirm -> order created.
         * 5. On cancel/failure -> modal closes, cart untouched, customer can retry.
         */
        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (!this.validate() || !this.isPlaceOrderActionAllowed()) {
                return false;
            }

            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.getCode());

            this.isPlaceOrderActionAllowed(false);
            fullScreenLoader.startLoader();

            if (!customer.isLoggedIn() && !quote.guestEmail) {
                fullScreenLoader.stopLoader();
                this.isPlaceOrderActionAllowed(true);
                this._showError($t('Please enter your email address.'));
                return false;
            }

            $.when(
                setPaymentInformationAction(this.messageContainer, this.getData())
            ).done(function () {
                self._bindCallbackListener();
                self._sendCreateRequest()
                    .done(function (response) {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);

                        if (!response || response.success !== true || !response.iframe_html) {
                            self._showError(
                                (response && response.message)
                                    ? response.message
                                    : $t('Unable to initialize payment. Please try again.')
                            );
                            return;
                        }

                        if (response.paythor_token) {
                            self.paythorToken(response.paythor_token);
                        }

                        self.pendingQuoteId = response.quote_id || null;
                        self._openIframeModal(response.iframe_html);
                    })
                    .fail(function (jqXhr) {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);

                        var msg = $t('Unable to initialize payment. Please try again.');
                        if (jqXhr && jqXhr.responseJSON && jqXhr.responseJSON.message) {
                            msg = jqXhr.responseJSON.message;
                        }
                        self._showError(msg);
                    });
            }).fail(function (jqXhr) {
                if (jqXhr && jqXhr.status === 404) {
                    self._bindCallbackListener();
                    self._sendCreateRequest()
                        .done(function (response) {
                            fullScreenLoader.stopLoader();
                            self.isPlaceOrderActionAllowed(true);

                            if (!response || response.success !== true || !response.iframe_html) {
                                self._showError(
                                    (response && response.message)
                                        ? response.message
                                        : $t('Unable to initialize payment. Please try again.')
                                );
                                return;
                            }

                            if (response.paythor_token) {
                                self.paythorToken(response.paythor_token);
                            }

                            self.pendingQuoteId = response.quote_id || null;
                            self._openIframeModal(response.iframe_html);
                        })
                        .fail(function (xhr) {
                            fullScreenLoader.stopLoader();
                            self.isPlaceOrderActionAllowed(true);

                            var fallbackMsg = $t('Unable to initialize payment. Please try again.');
                            if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                                fallbackMsg = xhr.responseJSON.message;
                            }
                            self._showError(fallbackMsg);
                        });
                    return;
                }

                fullScreenLoader.stopLoader();
                self.isPlaceOrderActionAllowed(true);
                self._showError($t('Failed to save payment information. Please check your billing details.'));
            });

            return true;
        },

        _sendCreateRequest: function () {
            var quoteId = null;
            if (typeof quote.getQuoteId === 'function') {
                quoteId = quote.getQuoteId();
            } else if (quote.quoteId) {
                quoteId = (typeof quote.quoteId === 'function') ? quote.quoteId() : quote.quoteId;
            }

            var payload = {
                form_key: $.mage.cookies.get('form_key') || window.checkoutConfig.formKey,
                method:   this.getCode(),
                cart_id:  quoteId,
                email:    quote.guestEmail || (customer.customerData && customer.customerData.email)
            };

            return $.ajax({
                url:        urlFormatter.build(this.paythorEndpoint),
                type:       'POST',
                dataType:   'json',
                data:       payload,
                showLoader: false,
                cache:      false,
                headers:    { 'X-Requested-With': 'XMLHttpRequest' }
            });
        },

        _sendConfirmRequest: function (reference, processId) {
            var self = this;

            $.ajax({
                url:        urlFormatter.build(this.paythorConfirmEndpoint),
                type:       'POST',
                dataType:   'json',
                data: {
                    form_key:   $.mage.cookies.get('form_key') || window.checkoutConfig.formKey,
                    reference:  reference,
                    process_id: processId || ''
                },
                showLoader: false,
                cache:      false,
                headers:    { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (response) {
                fullScreenLoader.stopLoader();
                if (response && response.success && response.redirect_url) {
                    window.location.replace(response.redirect_url);
                } else {
                    self.isPlaceOrderActionAllowed(true);
                    self._showError(
                        (response && response.message)
                            ? response.message
                            : $t('Could not finalize the order. Please try again.')
                    );
                }
            }).fail(function () {
                fullScreenLoader.stopLoader();
                self.isPlaceOrderActionAllowed(true);
                self._showError($t('Could not finalize the order. Please try again.'));
            });
        },

        _openIframeModal: function (iframeHtml) {
            var self = this;

            if (this.modalContainer) {
                try { this.modalContainer.remove(); } catch (e) { /* noop */ }
                this.modalContainer = null;
                this.modalInstance = null;
            }

            this.modalContainer = $(
                '<div class="paythor-sanalpospro-modal-content" data-role="paythor-iframe-host"></div>'
            ).html(iframeHtml);

            $('body').append(this.modalContainer);

            this.modalInstance = modal({
                type:             'popup',
                modalClass:       'paythor-sanalpospro-modal',
                title:            $t('Secure Card Payment'),
                responsive:       true,
                innerScroll:      true,
                clickableOverlay: false,
                buttons: [{
                    text:  $t('Cancel'),
                    class: 'action secondary action-cancel',
                    click: function () {
                        self.modalInstance.closeModal();
                    }
                }],
                closed: function () {
                    self.isPlaceOrderActionAllowed(true);
                    if (self.modalContainer) {
                        try { self.modalContainer.remove(); } catch (e) { /* noop */ }
                        self.modalContainer = null;
                    }
                }
            }, this.modalContainer);

            this.modalInstance.openModal();
        },

        _bindCallbackListener: function () {
            if (this.callbackBound) {
                return;
            }
            this.callbackBound = true;

            var self          = this,
                sameOrigin    = window.location.origin,
                paythorOrigins = [
                    'https://pay.paythor.com',
                    'https://dev-pay.paythor.com'
                ];

            window.addEventListener('message', function (ev) {
                var data = ev.data;

                if (paythorOrigins.indexOf(ev.origin) !== -1) {
                    if (!data || data.isSuccess !== true || !data.processID) {
                        return;
                    }
                    if (self.modalInstance) {
                        try { self.modalInstance.closeModal(); } catch (e) { /* noop */ }
                    }
                    if (self.pendingQuoteId) {
                        fullScreenLoader.startLoader();
                        self._sendConfirmRequest(self.pendingQuoteId, data.processID);
                    }
                    return;
                }

                if (ev.origin !== sameOrigin) {
                    return;
                }
                if (!data || data.source !== 'paythor_sanalpospro') {
                    return;
                }

                if (data.status === 'success') {
                    if (self.modalInstance) {
                        try { self.modalInstance.closeModal(); } catch (e) { /* noop */ }
                    }
                    fullScreenLoader.startLoader();
                    self._sendConfirmRequest(data.reference);
                } else {
                    if (self.modalInstance) {
                        try { self.modalInstance.closeModal(); } catch (e) { /* noop */ }
                    }
                    self.isPlaceOrderActionAllowed(true);
                    self._showError(
                        data.message || $t('Payment was not completed. Please try again.')
                    );
                }
            }, false);
        },

        _showError: function (message) {
            globalMessageList.addErrorMessage({ message: message });
        }
    });
});
