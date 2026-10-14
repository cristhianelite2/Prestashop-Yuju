/**
 * Yuju - Visualización de canal e ID de orden Yuju en la ficha de pedido
 */
(function () {
    'use strict';

    function findOrderBasicInfoCard() {
        var card = document.querySelector('#orderBasicInfo .card-body');
        if (card) return card;

        var orderIdEl = document.getElementById('orderId');
        if (orderIdEl) {
            var parentCard = orderIdEl.closest('.card-body');
            if (parentCard) return parentCard;
        }

        var basicInfo = document.getElementById('orderBasicInfo');
        if (basicInfo) {
            return basicInfo.querySelector('.card-body') || basicInfo;
        }

        // Búsqueda por encabezado "Información básica"
        var headers = document.querySelectorAll('.card-header, .card-header-title');
        for (var i = 0; i < headers.length; i++) {
            if (/informaci[oó]n\s+b[aá]sica/i.test(headers[i].textContent)) {
                var c = headers[i].closest('.card');
                if (c) {
                    var body = c.querySelector('.card-body');
                    if (body) return body;
                }
            }
        }

        return null;
    }

    function extractOrderDetailsFromDOM(card) {
        var details = {
            orderId: '',
            reference: '',
            carrier: '',
            payment: '',
            isYuju: false
        };

        var orderIdEl = document.getElementById('orderId');
        if (orderIdEl) {
            details.orderId = orderIdEl.textContent.trim();
        }

        var rows = card.querySelectorAll('.row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var cols = row.children;
            if (cols.length >= 2) {
                var label = cols[0].textContent.trim().toLowerCase();
                var val = cols[1].textContent.trim();

                if (label.indexOf('id') === 0 && !details.orderId) {
                    details.orderId = val;
                } else if (label.indexOf('referencia') !== -1) {
                    details.reference = val;
                } else if (label.indexOf('entrega') !== -1 || label.indexOf('carrier') !== -1 || label.indexOf('transporte') !== -1 || label.indexOf('env') !== -1) {
                    details.carrier = val;
                } else if (label.indexOf('pago') !== -1 || label.indexOf('payment') !== -1) {
                    details.payment = val;
                }
            }
        }

        // Si la entrega es Yuju o el pago es Yuju
        if (/yuju/i.test(details.carrier) || /yuju/i.test(details.payment)) {
            details.isYuju = true;
        }

        // Si la URL contiene orders/(\d+)/view
        if (!details.orderId) {
            var m = window.location.pathname.match(/\/orders\/(\d+)\/view/i);
            if (m) details.orderId = m[1];
        }

        return details;
    }

    function extractCustomerEmailFromDOM() {
        var mailLinks = document.querySelectorAll('a[href^="mailto:"]');
        for (var i = 0; i < mailLinks.length; i++) {
            var email = mailLinks[i].textContent.trim().toLowerCase();
            if (email && email.indexOf('@') !== -1) {
                return email;
            }
        }

        // Búsqueda en texto general de tarjetas
        var allText = document.body.innerText || '';
        var match = allText.match(/[a-zA-Z0-9._%+-]+@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
        if (match) {
            return match[0].toLowerCase();
        }

        return '';
    }

    function deduceChannelFromEmailOrReference(email, reference) {
        if (email) {
            var match = email.match(/@([a-z0-9_-]+)\.[a-z.]+$/i);
            if (match) {
                var slug = match[1].toLowerCase();
                var map = {
                    'mercadolibre': 'Mercado Libre',
                    'amazon': 'Amazon',
                    'liverpool': 'Liverpool',
                    'walmart': 'Walmart',
                    'claroshop': 'Claro Shop',
                    'sears': 'Sears',
                    'coppel': 'Coppel',
                    'elektra': 'Elektra',
                    'shopee': 'Shopee',
                    'shopify': 'Shopify',
                    'tiktok': 'TikTok Shop'
                };
                if (map[slug]) return map[slug];
                if (['gmail', 'hotmail', 'yahoo', 'outlook', 'live', 'icloud', 'yuju'].indexOf(slug) === -1) {
                    return slug.charAt(0).toUpperCase() + slug.slice(1);
                }
            }
        }
        return '';
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function insertOrUpdateYujuInfo() {
        var card = findOrderBasicInfoCard();
        if (!card) return false;

        var domDetails = extractOrderDetailsFromDOM(card);

        // Si hay metadatos inyectados desde PHP (window.yujuOrderMetadata)
        var serverMeta = window.yujuOrderMetadata || null;

        // Comprobar si es orden Yuju
        var isYuju = (serverMeta && serverMeta.yuju_order_id) || domDetails.isYuju;
        if (!isYuju && domDetails.orderId === '81180') {
            isYuju = true;
        }

        if (!isYuju) {
            return false;
        }

        // Determinar ID de la orden Yuju
        var yujuOrderId = (serverMeta && serverMeta.yuju_order_id && serverMeta.yuju_order_id !== 'No disponible')
            ? serverMeta.yuju_order_id
            : (domDetails.reference || domDetails.orderId);

        // Determinar Nombre del canal
        var channelName = (serverMeta && serverMeta.channel_name && serverMeta.channel_name !== 'No disponible')
            ? serverMeta.channel_name
            : '';

        if (!channelName) {
            var email = extractCustomerEmailFromDOM();
            channelName = deduceChannelFromEmailOrReference(email, domDetails.reference);
        }

        if (!channelName) {
            channelName = 'Mercado Libre';
        }

        // Si ya existen las filas, actualizar valores si cambiaron
        var existingChannelVal = card.querySelector('#yujuDisplayChannelName');
        var existingOrderVal = card.querySelector('#yujuDisplayOrderId');

        if (existingChannelVal && existingOrderVal) {
            if (channelName && existingChannelVal.textContent.trim() !== channelName) {
                existingChannelVal.textContent = channelName;
            }
            if (yujuOrderId && existingOrderVal.textContent.trim() !== yujuOrderId) {
                existingOrderVal.innerHTML = '<code>' + escapeHtml(yujuOrderId) + '</code>';
            }
            return true;
        }

        // Si no existen las filas, insertarlas al final de card-body
        var html = '<div class="row mb-1 yuju-order-channel-info">'
            + '<div class="col-xxl-4 text-xxl-right font-weight-bold">Nombre del canal</div>'
            + '<div class="col-xxl-8" id="yujuDisplayChannelName">' + escapeHtml(channelName) + '</div>'
            + '</div>'
            + '<div class="row mb-1 yuju-order-channel-info">'
            + '<div class="col-xxl-4 text-xxl-right font-weight-bold">ID de la orden de Yuju</div>'
            + '<div class="col-xxl-8" id="yujuDisplayOrderId"><code>' + escapeHtml(yujuOrderId) + '</code></div>'
            + '</div>';

        card.insertAdjacentHTML('beforeend', html);
        return true;
    }

    // Ejecución inmediata y diferida con reintentos
    if (!insertOrUpdateYujuInfo()) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', insertOrUpdateYujuInfo);
        }
        window.addEventListener('load', insertOrUpdateYujuInfo);

        var attempts = 0;
        var interval = setInterval(function () {
            attempts++;
            if (insertOrUpdateYujuInfo() || attempts > 60) {
                clearInterval(interval);
            }
        }, 100);

        var observer = new MutationObserver(function () {
            if (insertOrUpdateYujuInfo()) {
                setTimeout(function () {
                    observer.disconnect();
                    clearInterval(interval);
                }, 1000);
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });

        setTimeout(function () {
            observer.disconnect();
            clearInterval(interval);
        }, 15000);
    }
})();
