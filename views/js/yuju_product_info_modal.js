/**
 * Modal único de producto Yuju (Info + Historial + Mandar de nuevo / Buscar webhook).
 * Compartido por AdminYujuProductStatus y AdminYujuCategoryBulk.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var historyPage = 1;
    var bound = false;
    var onChangedCb = null;
    var historyLoadSeq = 0;
    var currentModalCtx = {
        yuju_product_id: '',
        sync_status: '',
        last_error: ''
    };

    function cfgEl() {
        return $('#yuju-product-info-modal-cfg');
    }

    function ajaxUrl() {
        if (window.YUJU_PS_AJAX_URL) {
            return window.YUJU_PS_AJAX_URL;
        }
        var $c = cfgEl();
        return $c.data('ajaxUrl') || $c.attr('data-ajax-url') || '';
    }

    function ajaxToken() {
        if (window.YUJU_PS_TOKEN) {
            return window.YUJU_PS_TOKEN;
        }
        var $c = cfgEl();
        return $c.data('token') || $c.attr('data-token') || '';
    }

    function productAdminUrl() {
        var $c = cfgEl();
        return $c.data('productAdminUrl') || $c.attr('data-product-admin-url') || '';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function buildProductAdminEditUrl(productId) {
        productId = parseInt(productId, 10) || 0;
        var raw = String(productAdminUrl() || '');
        if (!productId || !raw) {
            return '#';
        }
        var qIndex = raw.indexOf('?');
        var qs = qIndex >= 0 ? raw.substring(qIndex) : '';
        qs = qs
            .replace(/([?&])(id_product|updateproduct)=[^&]*/g, '$1')
            .replace(/[?&]$/, '')
            .replace(/[?&]{2,}/g, '?')
            .replace(/\?&/, '?');
        if (qs === '?' || qs === '&') {
            qs = '';
        }
        var v2Idx = raw.indexOf('/sell/catalog/products-v2');
        if (v2Idx !== -1) {
            var v2Base = raw.substring(0, v2Idx + '/sell/catalog/products-v2'.length).replace(/\/$/, '');
            return v2Base + '/' + productId + '/edit' + qs;
        }
        var v1Idx = raw.indexOf('/sell/catalog/products');
        if (v1Idx !== -1) {
            return raw.substring(0, v1Idx) + '/sell/catalog/products-v2/' + productId + '/edit' + qs;
        }
        var sep = raw.indexOf('?') >= 0 ? '&' : '?';
        return raw + sep + 'id_product=' + encodeURIComponent(String(productId)) + '&updateproduct=1';
    }

    function parseMaybeJson(value) {
        if (value == null || value === '') {
            return null;
        }
        if (typeof value === 'object') {
            return value;
        }
        try {
            return JSON.parse(String(value));
        } catch (e) {
            return String(value);
        }
    }

    function formatYujuResponsePreview(responseData) {
        var obj = parseMaybeJson(responseData);
        if (!obj || typeof obj !== 'object') {
            return '<small class="text-muted">Sin respuesta estructurada de Yuju.</small>';
        }
        var lines = [];
        if (obj.success && Array.isArray(obj.success) && obj.success.length) {
            var s0 = obj.success[0] || {};
            if (s0.id_product) {
                lines.push('<strong>ID Yuju (API):</strong> ' + escapeHtml(String(s0.id_product)));
            }
            if (s0.warning) {
                var warns = Array.isArray(s0.warning) ? s0.warning : [s0.warning];
                lines.push('<strong>Warnings:</strong> ' + escapeHtml(warns.join('; ')));
            }
            lines.push('<span class="text-success"><i class="icon-ok"></i> Yuju aceptó el alta (success)</span>');
        }
        if (obj.errors && Array.isArray(obj.errors) && obj.errors.length) {
            lines.push('<span class="text-danger"><i class="icon-remove"></i> La respuesta incluye errores</span>');
        }
        if (!lines.length) {
            lines.push('<small class="text-muted">Respuesta recibida (abra «Ver detalles» para el JSON completo).</small>');
        }
        return '<div class="yuju-history-reply-preview" style="margin-top:6px; padding:6px 8px; background:#f7fbff; border:1px solid #d9edf7; border-radius:3px;">'
            + lines.join('<br>') + '</div>';
    }

    function yujuHistoryErrorOneLine(raw) {
        var t = String(raw || '').replace(/\s+/g, ' ').trim();
        if (t.toLowerCase().indexOf('sku simple is not editable') !== -1) {
            return '';
        }
        return t;
    }

    function extractErrorFromResponseData(responseData) {
        if (!responseData) {
            return '';
        }
        try {
            var parsed = typeof responseData === 'string' ? JSON.parse(responseData) : responseData;
            if (!parsed || typeof parsed !== 'object') {
                return '';
            }
            if (parsed.message) {
                return Array.isArray(parsed.message) ? parsed.message.join('; ') : String(parsed.message);
            }
            if (parsed.error) {
                return typeof parsed.error === 'string' ? parsed.error : JSON.stringify(parsed.error);
            }
            if (parsed.errors && Array.isArray(parsed.errors)) {
                return parsed.errors.map(function (e) {
                    if (typeof e === 'string') {
                        return e;
                    }
                    if (e && e.message) {
                        return Array.isArray(e.message) ? e.message.join('; ') : String(e.message);
                    }
                    return JSON.stringify(e);
                }).join('; ');
            }
            if (parsed.data) {
                return extractErrorFromResponseData(parsed.data);
            }
        } catch (ignore) {
            // ignore
        }
        return '';
    }

    function resolveHistoryErrorText(record) {
        var status = String((record && record.status) || '');
        var one = yujuHistoryErrorOneLine(record && record.error_message);
        var low = one.toLowerCase();
        if (one && low !== 'error' && low !== 'error desconocido' && low.indexOf('error al enviar producto: error') === -1) {
            return one;
        }
        var fromResp = extractErrorFromResponseData(record && record.response_data);
        if (fromResp) {
            return fromResp;
        }
        // Éxito (u otro no-error): no inventar mensaje de fallo
        if (status !== 'error') {
            return one || '';
        }
        return one || 'Sin detalle de error guardado. Abra «Ver detalles» para revisar request/response.';
    }

    /**
     * True si la respuesta de Yuju indica éxito real (HTTP 200 + success sin errors).
     */
    function isSuccessfulYujuResponse(record) {
        if (!record) {
            return false;
        }
        if (String(record.status || '') === 'success') {
            var http = parseInt(record.http_status_code, 10) || 0;
            if (http && http !== 200 && http !== 201) {
                return false;
            }
            try {
                var raw = record.response_data;
                var parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!parsed || typeof parsed !== 'object') {
                    return true;
                }
                if (parsed.success === false) {
                    return false;
                }
                var data = parsed.data && typeof parsed.data === 'object' ? parsed.data : parsed;
                if (data.errors && Array.isArray(data.errors) && data.errors.length) {
                    return false;
                }
                return true;
            } catch (e) {
                return true;
            }
        }
        return false;
    }

    function renderPendingCreatePanel(pc) {
        if (!pc) {
            return '';
        }
        var broken = !!pc.broken;
        var html = '<div class="panel panel-' + (broken ? 'danger' : 'warning') + '" style="margin-bottom:0;">';
        html += '<div class="panel-heading"><strong><i class="icon-time"></i> ';
        html += broken ? 'Estado inconsistente (creación)' : 'En espera de respuesta (creación)';
        html += '</strong></div><div class="panel-body">';
        html += '<p>' + escapeHtml(pc.message || '') + '</p>';
        if (pc.wait && pc.wait.since) {
            html += '<p class="text-muted"><small>Desde: ' + escapeHtml(String(pc.wait.since))
                + ' (~' + Math.floor((pc.wait.seconds || 0) / 60) + ' min)</small></p>';
        }
        html += '<div class="row">';
        html += '<div class="col-md-6">';
        html += '<h5 style="margin-top:0;"><i class="icon-download"></i> Lo que respondió Yuju</h5>';
        if (pc.yuju_response) {
            html += formatYujuResponsePreview(pc.yuju_response);
            html += '<pre style="max-height:220px; overflow:auto; background:#f5f5f5; padding:8px; font-size:11px; margin-top:8px;">'
                + escapeHtml(JSON.stringify(pc.yuju_response, null, 2)) + '</pre>';
        } else {
            html += '<p class="text-muted">' + (broken
                ? 'No hay respuesta de Yuju porque el producto no llegó a enviarse.'
                : 'Aún no hay respuesta de Yuju guardada en el historial.') + '</p>';
        }
        html += '</div><div class="col-md-6">';
        html += '<h5 style="margin-top:0;"><i class="icon-upload"></i> Lo que se envió</h5>';
        if (pc.sent_payload) {
            var payload = pc.sent_payload;
            var summary = [];
            if (payload.sku) summary.push('<strong>SKU:</strong> ' + escapeHtml(String(payload.sku)));
            if (payload.name) summary.push('<strong>Nombre:</strong> ' + escapeHtml(String(payload.name)));
            if (payload.id_category != null) summary.push('<strong>id_category:</strong> ' + escapeHtml(String(payload.id_category)));
            if (summary.length) {
                html += '<p>' + summary.join('<br>') + '</p>';
            }
            html += '<pre style="max-height:220px; overflow:auto; background:#f5f5f5; padding:8px; font-size:11px;">'
                + escapeHtml(JSON.stringify(payload, null, 2)) + '</pre>';
        } else {
            html += '<p class="text-muted">' + (broken
                ? 'No hay payload: el intento anterior falló antes de armar/enviar el producto.'
                : 'No hay payload de envío en el historial.') + '</p>';
        }
        html += '</div></div>';
        if (!broken) {
            html += '<p class="help-block">Cuando llegue el webhook <code>product-created</code>, el estado pasará a <strong>Sincronizado / Creado</strong>.</p>';
            var pid = escapeHtml(String(pc.product_id || $('#modal-product-id').text() || ''));
            html += '<button type="button" class="btn btn-sm btn-primary yuju-find-created-webhook" data-product-id="'
                + pid + '"><i class="icon-search"></i> Buscar webhook</button> ';
            if (pc.wait && pc.wait.can_resend) {
                html += '<button type="button" class="btn btn-sm btn-warning yuju-resend-pending-create" data-product-id="'
                    + pid + '" title="Han pasado más de 1 hora sin webhook"><i class="icon-refresh"></i> Mandar de nuevo</button>';
            }
            html += '<div class="yuju-find-webhook-result" style="margin-top:10px;"></div>';
        }
        html += '</div></div>';
        return html;
    }

    function historyMeta(record) {
        var isPendingCreate = record.action === 'create'
            && record.status === 'success'
            && String(record.error_message || '').toLowerCase().indexOf('pendiente') !== -1;
        var yujuId = String(record.yuju_product_id || currentModalCtx.yuju_product_id || '').trim();
        var hasYuju = yujuId !== '' && yujuId !== '0' && yujuId.toLowerCase() !== 'null';
        var statusClass = record.status === 'success'
            ? (isPendingCreate ? 'info' : 'success')
            : (record.status === 'error' ? 'danger' : 'warning');
        var statusIcon = record.status === 'success'
            ? (isPendingCreate ? 'icon-time' : 'icon-check')
            : (hasYuju && record.status === 'error' ? 'icon-warning-sign' : 'icon-remove');
        var statusText = record.status === 'success'
            ? (isPendingCreate ? 'En espera' : 'OK')
            : (record.status === 'error' ? 'Error' : String(record.status || '—'));
        if (record.status === 'error') {
            if (hasYuju) {
                statusText = record.action === 'update'
                    ? 'Error al actualizar (ya en Yuju)'
                    : (record.action === 'create'
                        ? 'Error en creación (ya tenía ID Yuju)'
                        : 'Error (producto ya en Yuju)');
                statusClass = 'warning';
            } else if (record.action === 'create') {
                statusText = 'Error al crear';
            } else if (record.action === 'update') {
                statusText = 'Error al actualizar';
            }
        }
        var actionLabel = record.action === 'create' ? 'Crear'
            : (record.action === 'update' ? 'Actualización' : 'Eliminar');
        var ui = (record && record.ui) ? record.ui : {};
        var originLabel = ui.origin_label || '';
        var changedLabels = Array.isArray(ui.changed_fields_labels) ? ui.changed_fields_labels : [];
        var sentSummary = ui.sent_summary || '';
        var responseSummary = ui.response_summary || '';
        return {
            isPendingCreate: isPendingCreate,
            hasYuju: hasYuju,
            statusClass: statusClass,
            statusIcon: statusIcon,
            statusText: statusText,
            actionLabel: actionLabel,
            originLabel: originLabel,
            changedLabels: changedLabels,
            sentSummary: sentSummary,
            responseSummary: responseSummary
        };
    }

    function historyDetailsBtnAttrs(record, meta) {
        meta = meta || historyMeta(record);
        var errText = '';
        if (!isSuccessfulYujuResponse(record) && (record.status === 'error' || meta.isPendingCreate)) {
            errText = resolveHistoryErrorText(record);
        }
        return 'data-request="' + escapeHtml(record.request_data || '') + '" '
            + 'data-response="' + escapeHtml(record.response_data || '') + '" '
            + 'data-http-status="' + escapeHtml(String(record.http_status_code || 'N/A')) + '" '
            + 'data-duration="' + escapeHtml(String(parseFloat(record.sync_duration || 0).toFixed(3)) + 's') + '" '
            + 'data-error="' + escapeHtml(errText) + '" '
            + 'data-origin="' + escapeHtml(meta.originLabel || '') + '" '
            + 'data-updates="' + escapeHtml((meta.changedLabels && meta.changedLabels.length) ? meta.changedLabels.join(', ') : '') + '" '
            + 'data-sent-summary="' + escapeHtml(meta.sentSummary || '') + '" '
            + 'data-response-summary="' + escapeHtml(meta.responseSummary || '') + '"';
    }

    function historyDetailsBtn(record) {
        var meta = historyMeta(record);
        return '<button type="button" class="btn btn-xs btn-default yuju-hist-latest__details view-sync-details" '
            + historyDetailsBtnAttrs(record, meta)
            + ' title="Ver request/response"><i class="icon-search"></i> Ver detalles</button>';
    }

    function isSkuSimpleNotEditableError(text) {
        return String(text || '').toLowerCase().indexOf('sku simple is not editable') !== -1;
    }

    /**
     * Resumen compacto del fallo/aviso (sin cajas anidadas).
     * "SKU simple is not editable" → solo icono con tooltip.
     */
    function renderLatestIssueRow(record, meta) {
        var text = resolveHistoryErrorText(record);
        var fromResp = extractErrorFromResponseData(record && record.response_data);
        var skuErr = isSkuSimpleNotEditableError(record && record.error_message)
            || isSkuSimpleNotEditableError(fromResp)
            || isSkuSimpleNotEditableError(text);
        var parts = [];

        if (meta.hasYuju && record.status === 'error') {
            parts.push('<span class="yuju-hist-latest__pill yuju-hist-latest__pill--ok">'
                + '<i class="icon-cloud"></i> En Yuju</span>');
            if (record.yuju_product_id) {
                parts.push('<code class="yuju-hist-latest__id">' + escapeHtml(String(record.yuju_product_id)) + '</code>');
            }
        }

        if (skuErr) {
            parts.push('<i class="icon-exclamation-circle yuju-hist-latest__tip"'
                + ' title="SKU simple is not editable"></i>');
            return '<div class="yuju-hist-latest__issue">' + parts.join('') + '</div>';
        }

        if (!text && record.status !== 'error' && !meta.isPendingCreate) {
            return parts.length ? ('<div class="yuju-hist-latest__issue">' + parts.join('') + '</div>') : '';
        }

        if (!text || isSkuSimpleNotEditableError(text)) {
            text = 'Sin detalle de error. Use «Ver detalles».';
        }

        var tone = meta.isPendingCreate ? 'info' : (record.status === 'success' ? 'warn' : (meta.hasYuju ? 'warn' : 'bad'));
        var label = meta.isPendingCreate ? 'Estado' : (record.status === 'success' ? 'Advertencia' : 'Qué falló');
        parts.push('<span class="yuju-hist-latest__fail yuju-hist-latest__fail--' + tone + '">'
            + '<strong>' + label + ':</strong> ' + escapeHtml(text) + '</span>');

        return '<div class="yuju-hist-latest__issue">' + parts.join('') + '</div>';
    }

    function renderLatestHistoryCard(record) {
        var meta = historyMeta(record);
        var tone = meta.statusClass === 'danger' ? 'bad'
            : (meta.statusClass === 'warning' ? 'warn'
                : (meta.statusClass === 'info' ? 'info' : 'ok'));

        var html = '<div class="yuju-hist-latest__card yuju-hist-latest__card--' + tone + '">';
        html += '<div class="yuju-hist-latest__top">';
        html += '<div class="yuju-hist-latest__title">Último registro</div>';
        html += '<div class="yuju-hist-latest__when">' + escapeHtml(record.created_at || '') + '</div>';
        html += '</div>';

        html += '<div class="yuju-hist-latest__badges">';
        html += '<span class="label label-' + meta.statusClass + '"><i class="' + meta.statusIcon + '"></i> '
            + escapeHtml(meta.statusText) + '</span> ';
        html += '<span class="label label-default">' + escapeHtml(meta.actionLabel) + '</span>';
        html += '</div>';

        html += '<div class="yuju-hist-latest__stats">';
        html += '<span class="yuju-hist-latest__stat"><em>Duración</em> '
            + escapeHtml(parseFloat(record.sync_duration || 0).toFixed(3) + 's') + '</span>';
        if (record.http_status_code) {
            html += '<span class="yuju-hist-latest__stat"><em>HTTP</em> '
                + escapeHtml(String(record.http_status_code)) + '</span>';
        }
        html += '</div>';

        html += renderLatestIssueRow(record, meta);
        html += '<div class="yuju-hist-latest__actions">' + historyDetailsBtn(record) + '</div>';
        html += '</div>';
        return html;
    }

    function historyErrorHtml(record, meta) {
        // Compat: filas antiguas / otros usos
        return renderLatestIssueRow(record, meta);
    }

    function renderOlderHistoryRow(record) {
        var meta = historyMeta(record);
        var errorMsg = '';
        var text = resolveHistoryErrorText(record);
        if (text && (record.status === 'error' || record.error_message || meta.isPendingCreate)) {
            var messageLabel = meta.isPendingCreate ? 'Estado'
                : (record.status === 'success' ? 'Advertencia' : 'Qué falló');
            var messageClass = meta.isPendingCreate ? 'text-info'
                : (record.status === 'success' ? 'text-warning' : 'text-danger');
            var yujuNote = (meta.hasYuju && record.status === 'error')
                ? '<br><small class="text-success"><i class="icon-cloud"></i> Sí está en Yuju'
                    + (record.yuju_product_id ? (' · ID ' + escapeHtml(String(record.yuju_product_id))) : '')
                    + '</small>'
                : '';
            errorMsg = yujuNote + '<br><small class="' + messageClass + '"><strong>' + messageLabel + ':</strong> '
                + escapeHtml(text) + '</small>';
        }
        var detailsBtn = '<button type="button" class="btn btn-xs btn-info view-sync-details" '
            + historyDetailsBtnAttrs(record, meta)
            + ' title="Ver detalles"><i class="icon-search"></i></button>';
        return '<tr>'
            + '<td><small>' + escapeHtml(record.created_at || '') + '</small></td>'
            + '<td><span class="label label-info">' + escapeHtml(meta.actionLabel) + '</span></td>'
            + '<td><span class="label label-' + meta.statusClass + '" title="' + escapeHtml(meta.statusText) + '"><i class="'
            + meta.statusIcon + '"></i> ' + escapeHtml(meta.statusText) + '</span></td>'
            + '<td><small>' + escapeHtml(parseFloat(record.sync_duration || 0).toFixed(3) + 's') + '</small></td>'
            + '<td>' + detailsBtn + errorMsg + '</td>'
            + '</tr>';
    }

    function loadHistory(page) {
        var productId = String($('#modal-product-id').text() || '').trim();
        var url = ajaxUrl();
        var token = ajaxToken();
        if (!productId || productId === '—' || !url) {
            $('#sync-history-loading').hide();
            $('#sync-history-error-message').text(
                !url ? 'URL AJAX no configurada para cargar el historial' : 'ID de producto no disponible'
            );
            $('#sync-history-error').show();
            return;
        }
        historyPage = parseInt(page, 10) || 1;
        var loadSeq = ++historyLoadSeq;

        $('#sync-history-loading').show();
        $('#sync-history-content').hide();
        $('#sync-history-error').hide();
        $('#sync-history-info').hide().empty();
        $('#sync-history-pending-create').hide().empty();
        $('#sync-history-pager').hide().empty();
        $('#sync-history-latest').hide().empty();
        $('#sync-history-older-wrap').hide();
        $('#sync-history-empty').hide();
        $('#sync-history-tbody').empty();
        $('#sync-history-older-body').removeClass('in').attr('aria-expanded', 'false').css({ height: '', display: '' });
        $('#sync-history-older-wrap .yuju-hist-older__toggle').addClass('collapsed').attr('aria-expanded', 'false');

        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'getProductHistory',
                token: token,
                product_id: productId,
                page: historyPage
            },
            success: function (response) {
                if (loadSeq !== historyLoadSeq) {
                    return; // respuesta obsoleta (doble click/shown)
                }
                $('#sync-history-loading').hide();
                if (!response || !response.success) {
                    $('#sync-history-error-message').text((response && response.message) ? response.message : 'Error al cargar historial');
                    $('#sync-history-error').show();
                    return;
                }

                try {
                    if (response.history_info) {
                        $('#sync-history-info').text(response.history_info).show();
                    }
                    if (response.pending_create) {
                        $('#sync-history-pending-create').html(renderPendingCreatePanel(response.pending_create)).show();
                    }

                    var history = (response.history && response.history.length) ? response.history : [];
                    if (history.length) {
                        var latest = history[0];
                        var older = history.slice(1);
                        $('#sync-history-latest').html(renderLatestHistoryCard(latest)).show();
                        if (older.length) {
                            var rows = '';
                            older.forEach(function (record) {
                                rows += renderOlderHistoryRow(record);
                            });
                            $('#sync-history-tbody').html(rows);
                            $('#sync-history-older-count').text(String(older.length));
                            $('#sync-history-older-wrap').show();
                        }
                    } else if (!response.pending_create) {
                        $('#sync-history-empty').show();
                    }

                    if (response.pagination && response.pagination.total_pages > 1) {
                        var p = response.pagination;
                        var ph = '<div class="text-center">Página ' + p.current_page + ' de ' + p.total_pages
                            + ' <span class="btn-group" style="margin-left:6px;">';
                        if (p.current_page > 1) {
                            ph += '<button type="button" class="btn btn-default btn-xs yuju-pim-history-page" data-page="'
                                + (p.current_page - 1) + '">&laquo;</button>';
                        }
                        if (p.current_page < p.total_pages) {
                            ph += '<button type="button" class="btn btn-default btn-xs yuju-pim-history-page" data-page="'
                                + (p.current_page + 1) + '">&raquo;</button>';
                        }
                        ph += '</span></div>';
                        $('#sync-history-pager').html(ph).show();
                    }

                    $('#sync-history-content').show();
                } catch (renderErr) {
                    $('#sync-history-error-message').text('Error al renderizar historial: ' + (renderErr && renderErr.message ? renderErr.message : 'desconocido'));
                    $('#sync-history-error').show();
                }
            },
            error: function (xhr) {
                if (loadSeq !== historyLoadSeq) {
                    return;
                }
                $('#sync-history-loading').hide();
                var msg = 'Error de conexión al cargar historial';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr && xhr.status) {
                    msg += ' (HTTP ' + xhr.status + ')';
                }
                $('#sync-history-error-message').text(msg);
                $('#sync-history-error').show();
            }
        });
    }

    /**
     * Activa el tab de historial y fuerza la carga (aunque el tab ya estuviera activo).
     */
    function showHistoryTabAndLoad() {
        historyPage = 1;
        var $link = $('#tab-history-link');
        var alreadyActive = $link.parent().hasClass('active') || $link.attr('aria-expanded') === 'true';

        // Resetear al tab info y volver a historial fuerza shown.bs.tab en Bootstrap 3
        $('a[href="#tab-product-info"]').tab('show');
        setTimeout(function () {
            $link.tab('show');
            // Si ya estaba activo o el evento no dispara, cargar igual
            loadHistory(1);
        }, alreadyActive ? 0 : 30);
    }

    function findWebhook(productId, $btn) {
        productId = parseInt(productId, 10) || 0;
        if (!productId) {
            alert('ID de producto no válido');
            return;
        }
        var $btnEl = $btn && $btn.jquery ? $btn : $($btn);
        var $resultBox = $('#sync-history-pending-create .yuju-find-webhook-result').first();
        if (!$resultBox.length) {
            $resultBox = $btnEl.closest('.panel-body, .modal-body').find('.yuju-find-webhook-result').first();
        }
        var originalHtml = $btnEl.html();
        $btnEl.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Buscando…');
        if ($resultBox.length) {
            $resultBox.html('<p class="text-muted"><i class="icon-spinner icon-spin"></i> Buscando…</p>');
        }

        $.ajax({
            url: ajaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'findProductCreatedWebhook',
                token: ajaxToken(),
                product_id: productId
            },
            success: function (response) {
                $btnEl.prop('disabled', false).html(originalHtml);
                var msg = (response && response.message) ? response.message : 'Sin mensaje';
                var applied = !!(response && (response.applied || response.already_linked));
                if ($resultBox.length) {
                    var cls = (!response || !response.success) ? 'alert-danger'
                        : (applied ? 'alert-success' : (response.found ? 'alert-warning' : 'alert-info'));
                    $resultBox.html('<div class="alert ' + cls + '" style="margin-bottom:0;">' + escapeHtml(msg) + '</div>');
                } else {
                    alert(msg);
                }
                if (applied) {
                    loadHistory(1);
                    if (typeof onChangedCb === 'function') {
                        onChangedCb({ action: 'webhook_linked', product_id: productId });
                    }
                }
            },
            error: function () {
                $btnEl.prop('disabled', false).html(originalHtml);
                if ($resultBox.length) {
                    $resultBox.html('<div class="alert alert-danger" style="margin-bottom:0;">Error de conexión.</div>');
                } else {
                    alert('Error de conexión al buscar el webhook');
                }
            }
        });
    }

    function resendPending(productId, $btn) {
        productId = parseInt(productId, 10) || 0;
        if (!productId) {
            alert('ID de producto no válido');
            return;
        }
        if (!window.confirm('¿Reenviar este producto a Yuju?\n\nSolo use esta opción si pasó más de 1 hora sin recibir el webhook product-created.')) {
            return;
        }
        var $btnEl = $btn && $btn.jquery ? $btn : $($btn);
        var $resultBox = $('#sync-history-pending-create .yuju-find-webhook-result').first();
        var originalHtml = $btnEl.html();
        $btnEl.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Enviando…');

        $.ajax({
            url: ajaxUrl(),
            method: 'POST',
            dataType: 'json',
            data: {
                ajax: true,
                action: 'resendPendingCreate',
                token: ajaxToken(),
                product_id: productId
            },
            success: function (response) {
                $btnEl.prop('disabled', false).html(originalHtml);
                var msg = (response && response.message) ? response.message : 'Sin respuesta';
                var ok = !!(response && response.success);
                if ($resultBox.length) {
                    $resultBox.html('<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + '" style="margin-bottom:0;">'
                        + escapeHtml(msg) + '</div>');
                } else {
                    alert(msg);
                }
                if (ok) {
                    loadHistory(1);
                    if (typeof onChangedCb === 'function') {
                        onChangedCb({ action: 'resend', product_id: productId });
                    }
                }
            },
            error: function () {
                $btnEl.prop('disabled', false).html(originalHtml);
                alert('Error de conexión al reenviar el producto');
            }
        });
    }

    function open(product, options) {
        options = options || {};
        product = product || {};
        var id = parseInt(product.id || product.id_product || product.product_id, 10) || 0;
        if (!id) {
            return;
        }

        var reference = product.reference != null ? String(product.reference) : '';
        var name = product.name != null ? String(product.name) : '';
        var category = product.category != null ? String(product.category) : (product.category_name || '');
        var yujuProductId = product.yuju_product_id != null ? String(product.yuju_product_id).trim() : '';
        if (yujuProductId === '' || yujuProductId === '0' || yujuProductId.toLowerCase() === 'null') {
            yujuProductId = '';
        }
        var processHint = product.processHint || product.process_hint || '';
        var idImage = product.id_image != null ? String(product.id_image).trim() : '';
        var syncStatus = product.sync_status != null ? String(product.sync_status) : (product.yuju_status || '');
        var lastError = product.last_error != null ? String(product.last_error).trim() : '';

        currentModalCtx = {
            yuju_product_id: yujuProductId,
            sync_status: syncStatus,
            last_error: lastError
        };

        $('#modal-product-id').text(id);
        $('#modal-product-reference').text(reference || '—');
        $('#modal-product-name').text(name || '—');
        $('#modal-product-category').text(category || '—');
        $('#modal-yuju-product-id').text(yujuProductId || '—');
        $('#modal-product-link').attr('href', buildProductAdminEditUrl(id));

        if (yujuProductId) {
            $('#modal-yuju-in-yuju-badge').show();
        } else {
            $('#modal-yuju-in-yuju-badge').hide();
        }

        var statusLabel = '—';
        if (syncStatus === 'synced') {
            statusLabel = '<span class="label label-success">Sincronizado</span>';
        } else if (syncStatus === 'synced_with_warnings') {
            statusLabel = '<span class="label label-success">Sincronizado</span> <span class="label label-warning">con advertencias</span>';
        } else if (syncStatus === 'synced_with_errors') {
            statusLabel = '<span class="label label-success">En Yuju</span> <span class="label label-warning">error en última sync</span>';
        } else if (syncStatus === 'error' && yujuProductId) {
            statusLabel = '<span class="label label-warning">En Yuju · Error</span>';
        } else if (syncStatus === 'error') {
            statusLabel = '<span class="label label-danger">Error</span>';
        } else if (syncStatus === 'creating_in_yuju') {
            statusLabel = '<span class="label label-info">En espera de respuesta</span>';
        } else if (syncStatus === 'updating_in_yuju') {
            statusLabel = '<span class="label label-warning">Actualizando…</span>';
        } else if (syncStatus === 'queued') {
            statusLabel = '<span class="label label-info">En cola</span>';
        } else if (syncStatus) {
            statusLabel = '<span class="label label-default">' + escapeHtml(syncStatus) + '</span>';
        }
        $('#modal-product-sync-status').html(statusLabel);

        if (lastError) {
            $('#modal-product-last-error-text').text(lastError);
            $('#modal-product-last-error')
                .removeClass('alert-danger alert-warning')
                .addClass(yujuProductId ? 'alert-warning' : 'alert-danger')
                .show();
        } else {
            $('#modal-product-last-error').hide();
            $('#modal-product-last-error-text').text('');
        }

        if (processHint) {
            $('#modal-sync-process-text').text(processHint);
            $('#modal-sync-process-banner').show();
        } else {
            $('#modal-sync-process-banner').hide();
        }

        if (idImage && idImage !== '0' && idImage.toLowerCase() !== 'null') {
            var imgUrl = '/img/p/' + idImage.split('').join('/') + '/' + idImage + '.jpg';
            $('#modal-product-image').attr('src', imgUrl).show();
        } else {
            $('#modal-product-image').hide().attr('src', '');
        }

        $('#sync-history-loading').hide();
        $('#sync-history-content').hide();
        $('#sync-history-error').hide();
        $('#sync-history-pending-create').hide().empty();
        $('#sync-history-latest').hide().empty();
        $('#sync-history-older-wrap').hide();
        $('#sync-history-empty').hide();

        // Partir siempre del tab Info para que Historial pueda re-disparar shown.bs.tab
        try {
            $('a[href="#tab-product-info"]').tab('show');
        } catch (eTab) { /* ignore */ }
        $('#tab-product-info').addClass('active in');
        $('#tab-sync-history').removeClass('active in');
        $('#tab-history-link').parent().removeClass('active');
        $('a[href="#tab-product-info"]').parent().addClass('active');

        $('#productInfoModal').off('shown.bs.modal.yujuPim');

        if (options.focusHistory) {
            $('#productInfoModal').one('shown.bs.modal.yujuPim', function () {
                showHistoryTabAndLoad();
            });
            $('#productInfoModal').modal('show');
            if ($('#productInfoModal').hasClass('in') || $('#productInfoModal').is(':visible')) {
                showHistoryTabAndLoad();
            }
        } else {
            $('#productInfoModal').modal('show');
        }
    }

    function bindEvents() {
        if (bound) {
            return;
        }
        bound = true;

        // Click + shown: shown no siempre dispara si el tab ya estaba activo
        $(document).on('click.yujuPimHist', '#tab-history-link', function () {
            historyPage = 1;
            setTimeout(function () {
                loadHistory(1);
            }, 50);
        });

        $(document).on('shown.bs.tab.yujuPimHist', '#tab-history-link', function () {
            historyPage = 1;
            loadHistory(1);
        });

        $(document).on('click', '.yuju-pim-history-page', function (e) {
            e.preventDefault();
            loadHistory($(this).data('page'));
        });

        $(document).on('click', '.yuju-find-created-webhook', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            findWebhook($btn.data('product-id') || $btn.attr('data-product-id'), $btn);
            return false;
        });

        $(document).on('click', '.yuju-resend-pending-create', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            resendPending($btn.data('product-id') || $btn.attr('data-product-id'), $btn);
            return false;
        });

        $(document).on('click', '.view-sync-details', function () {
            var $btn = $(this);
            var request = $btn.attr('data-request') || '';
            var response = $btn.attr('data-response') || '';
            var httpStatus = $btn.attr('data-http-status') || 'N/A';
            var duration = $btn.attr('data-duration') || '';
            var error = $btn.attr('data-error') || '';
            var origin = $btn.attr('data-origin') || '';
            var updates = $btn.attr('data-updates') || '';
            var sentSummary = $btn.attr('data-sent-summary') || '';
            var responseSummary = $btn.attr('data-response-summary') || '';

            try {
                var requestObj = request ? JSON.parse(request) : null;
                $('#sync-detail-request').text(requestObj ? JSON.stringify(requestObj, null, 2) : (request || 'No hay datos de request'));
            } catch (e1) {
                $('#sync-detail-request').text(request || 'Error al parsear request');
            }
            try {
                var responseObj = response ? JSON.parse(response) : null;
                $('#sync-detail-response').text(responseObj ? JSON.stringify(responseObj, null, 2) : (response || 'No hay datos de response'));
            } catch (e2) {
                $('#sync-detail-response').text(response || 'Error al parsear response');
            }

            $('#sync-detail-origin').text(origin || '—');
            $('#sync-detail-updates').text(updates || '—');
            $('#sync-detail-sent-summary').text(sentSummary || '—');
            $('#sync-detail-response-summary').text(responseSummary || '—');
            $('#sync-detail-http-status').text(httpStatus);
            $('#sync-detail-duration').text(duration);
            if (error) {
                $('#sync-detail-error-user').text(error);
                $('#sync-detail-error-row').show();
            } else {
                $('#sync-detail-error-row').hide();
            }
            $('#syncDetailsModal').modal('show');
        });
    }

    window.YujuProductInfoModal = {
        open: open,
        loadHistory: loadHistory,
        showHistoryTabAndLoad: showHistoryTabAndLoad,
        setOnChanged: function (cb) {
            onChangedCb = typeof cb === 'function' ? cb : null;
        },
        configure: function (opts) {
            opts = opts || {};
            var $c = cfgEl();
            if (opts.ajaxUrl) {
                if ($c.length) {
                    $c.attr('data-ajax-url', opts.ajaxUrl).data('ajaxUrl', opts.ajaxUrl);
                }
                window.YUJU_PS_AJAX_URL = opts.ajaxUrl;
            }
            if (opts.token) {
                if ($c.length) {
                    $c.attr('data-token', opts.token).data('token', opts.token);
                }
                window.YUJU_PS_TOKEN = opts.token;
            }
            if (opts.productAdminUrl && $c.length) {
                $c.attr('data-product-admin-url', opts.productAdminUrl).data('productAdminUrl', opts.productAdminUrl);
            }
        },
        bind: bindEvents
    };

    // Auto-bind en Product Status y Category Bulk (idempotente)
    $(function () {
        bindEvents();
    });
})(window, window.jQuery);
