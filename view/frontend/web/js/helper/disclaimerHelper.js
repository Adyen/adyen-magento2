/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define([], function () {
    'use strict';

    const MARKDOWN_LINK_PATTERN = /\[([^\[\]\n]+)\]\(([^()\s]+)\)/g;

    return {
        /**
         * Converts a disclaimer containing Markdown links into the Adyen Web `disclaimerMessage` format.
         *
         * @param {string|null} disclaimer e.g. 'Read the [terms](https://example.com/terms).'
         * @returns {{message: string, linkText: string[], link: string[]}|null}
         */
        buildDisclaimerMessage: function (disclaimer) {
            if (typeof disclaimer !== 'string' || disclaimer.trim() === '') {
                return null;
            }

            const linkText = [];
            const link = [];
            const message = disclaimer
                .trim()
                .replace(/%(?=[{#])/g, '%\u200B')
                .replace(MARKDOWN_LINK_PATTERN, (match, text, url) => {
                    if (!this.isHttpsUrl(url)) {
                        return text;
                    }

                    linkText.push(text);
                    link.push(url);

                    return '%{' + this.getPlaceholderName(text, linkText.length) + '}';
                });

            return {message: message, linkText: linkText, link: link};
        },

        /**
         * @param {string} text e.g. 'privacy policy'
         * @param {number} position 1-based position of the link in the message
         * @returns {string} e.g. 'privacyPolicy'
         */
        getPlaceholderName: function (text, position) {
            const words = text.toLowerCase().match(/[\p{L}\p{N}]+/gu) || [];
            const name = words
                .map((word, index) => index === 0 ? word : word.charAt(0).toUpperCase() + word.slice(1))
                .join('');

            return name || 'link' + position;
        },

        /**
         * @param {string} url
         * @returns {boolean}
         */
        isHttpsUrl: function (url) {
            try {
                return new URL(url).protocol === 'https:';
            } catch (e) {
                return false;
            }
        }
    };
});
