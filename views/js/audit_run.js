/**
 * Auditoría visual:
 * - sin imágenes → products-offer-report
 * - con imágenes → products-gral-report
 * KPI filtros, buscador, pausa, Excel, timer, detalle de errores.
 */
(function ($) {
    'use strict';

    function readConfig() {
        var el = document.getElementById('yuju-audit-config');
        if (!el) return null;
        try {
            return JSON.parse((el.textContent || el.innerText || '').trim());
        } catch (e) {
            console.error('Yuju audit: config JSON invalido', e);
            return null;
        }
    }

    var cfg = readConfig();
    if (!cfg || !cfg.ajax_url) {
        console.error('Yuju audit: sin configuración');
        return;
    }

    if (window.YujuAdmin && typeof window.YujuAdmin.stopAutoRefresh === 'function') {
        window.YujuAdmin.stopAutoRefresh();
    }

    var ajaxUrl = cfg.ajax_url;
    var token = cfg.token || '';
    var monitoringLink = cfg.monitoring_link || '';
    var running = false;
    var paused = false;
    var runId = null;
    var reportId = null;
    var pollTimer = null;
    var clockTimer = null;
    var startedAtMs = null;
    var pauseAccumMs = 0;
    var pausedAtMs = null;
    var activeFilter = 'total';
    var allRows = [];
    var serverFiltered = false;

    function setStatus(type, html) {
        var $el = $('#yuju-audit-status');
        if (!$el.length) {
            $el = $('<div id="yuju-audit-status" class="alert" style="margin-bottom:12px;"></div>');
            var $anchor = $('#yuju-audit-progress-wrap');
            if ($anchor.length) $anchor.before($el);
            else $('.yuju-audit-run-panel .panel-body').first().prepend($el);
        }
        $el.removeClass('alert-info alert-success alert-warning alert-danger')
            .addClass('alert-' + (type || 'info'))
            .html(html)
            .show();
    }

    function showTask(info) {
        // Caja id_task / reporte / pipeline eliminada de la UI
    }

    function renderPipeline(progress) {
        // no-op (pipeline UI eliminada)
    }

    function setOfferProgressBar(progress, failed) {
        var order = ['api_request', 'got_url', 'json_download', 'file_save'];
        var done = 0;
        order.forEach(function (k) {
            if (progress && progress[k] && progress[k].ok === true) done++;
        });
        var pct = Math.max(8, Math.round((done / order.length) * 100));
        var $bar = $('#yuju-audit-progress-bar');
        $('#yuju-audit-progress-wrap').show();
        $bar.removeClass('progress-bar-info progress-bar-success progress-bar-danger active')
            .addClass(failed ? 'progress-bar-danger' : (pct >= 100 ? 'progress-bar-success' : 'progress-bar-info active'))
            .css('width', pct + '%')
            .text(pct + '%');
        var texts = [];
        order.forEach(function (k) {
            if (progress && progress[k]) {
                var mark = progress[k].ok === true ? '✓' : (progress[k].ok === false ? '✗' : '…');
                texts.push(mark + ' ' + (progress[k].label || k));
            }
        });
        $('#yuju-audit-progress-text').text(texts.length ? texts.join(' · ') : 'Preparando…');
    }

    function fieldsPayload() {
        return {
            audit_stock: $('#yuju-audit-field-stock').is(':checked') ? 1 : 0,
            audit_price: $('#yuju-audit-field-price').is(':checked') ? 1 : 0,
            audit_images: $('#yuju-audit-field-images').is(':checked') ? 1 : 0,
            // Importante: reanalizar JSON local NO debe llamar a Yuju salvo que el usuario lo pida
            apply_fixes: $('#yuju-audit-apply-fixes').is(':checked') ? 1 : 0
        };
    }

    function hasFields() {
        return $('#yuju-audit-field-stock').is(':checked')
            || $('#yuju-audit-field-price').is(':checked')
            || $('#yuju-audit-field-images').is(':checked');
    }

    function wantsImages() {
        return $('#yuju-audit-field-images').is(':checked');
    }

    function updateQuotaLabel() {
        var $label = $('#yuju-audit-quota-label');
        if (!$label.length) return;
        if (wantsImages()) {
            var g = cfg.gral_quota || {};
            $label.text(
                'products-gral-report · cupo UTC ' + (g.used || 0) + '/' + (g.max || 2) +
                (g.allowed === false ? ' · AGOTADO' : '')
            );
            $('#yuju-audit-start-btn').prop('disabled', g.allowed === false);
        } else {
            var o = cfg.offer_quota || {};
            $label.text(
                'products-offer-report · cada ' + (o.interval_hours || 12) + 'h' +
                (o.allowed === false
                    ? (' · faltan ' + (o.remaining_human || '…') + (o.unlock_at ? (' (hasta ' + o.unlock_at + ')') : ''))
                    : '')
            );
            // Offer puede reutilizar histórico si el cupo está cerrado
            $('#yuju-audit-start-btn').prop('disabled', false);
        }
    }

    function parseJsonLoose(raw) {
        if (!raw) return null;
        if (typeof raw === 'object') return raw;
        try { return JSON.parse(raw); } catch (e1) {
            var start = String(raw).indexOf('{');
            var end = String(raw).lastIndexOf('}');
            if (start >= 0 && end > start) {
                try { return JSON.parse(String(raw).substring(start, end + 1)); } catch (e2) {}
            }
        }
        return null;
    }

    function ajaxFailMessage(xhr, fallback) {
        var msg = fallback || 'Error de comunicación';
        if (xhr && xhr.status) msg += ' (HTTP ' + xhr.status + ')';
        var body = xhr && xhr.responseText ? String(xhr.responseText).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() : '';
        if (body) msg += ': ' + body.substring(0, 280);
        return msg;
    }

    function escapeHtml(v) {
        return $('<div/>').text(v == null ? '' : String(v)).html();
    }

    function formatDuration(ms) {
        var sec = Math.max(0, Math.floor(ms / 1000));
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        var ss = (s < 10 ? '0' : '') + s;
        if (h > 0) return h + 'h ' + m + 'm ' + ss + 's';
        return m + 'm ' + ss + 's';
    }

    function elapsedMs() {
        if (!startedAtMs) return 0;
        var extra = pauseAccumMs;
        if (pausedAtMs) extra += (Date.now() - pausedAtMs);
        return Math.max(0, Date.now() - startedAtMs - extra);
    }

    function startClock() {
        $('#yuju-audit-timer').show();
        if (clockTimer) clearInterval(clockTimer);
        clockTimer = setInterval(function () {
            $('#yuju-audit-timer').text('⏱ ' + formatDuration(elapsedMs()));
        }, 500);
    }

    function stopClock(finalSeconds) {
        if (clockTimer) {
            clearInterval(clockTimer);
            clockTimer = null;
        }
        if (typeof finalSeconds === 'number') {
            $('#yuju-audit-timer').text('⏱ ' + formatDuration(finalSeconds * 1000)).show();
        }
    }

    function resultBadge(result) {
        if (result === 'matched') return '<span class="label label-success">OK</span>';
        if (result === 'diff_fixed') return '<span class="label label-primary">Corregido</span>';
        if (result === 'diff_found') return '<span class="label label-warning">Diff</span>';
        if (result === 'diff_error') return '<span class="label label-danger">Error</span>';
        if (result === 'not_found') return '<span class="label label-default">No encontrado</span>';
        return '<span class="label label-info">' + escapeHtml(result) + '</span>';
    }

    function truncateName(name, max) {
        max = max || 30;
        name = name == null ? '' : String(name);
        if (name.length <= max) return name;
        return name.substring(0, max).replace(/\s+$/, '') + '…';
    }

    function formatPriceVal(v) {
        if (v == null || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return String(v);
        return '$' + n.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function comparePairHtml(ps, yuju, opts) {
        opts = opts || {};
        var missing = (ps == null || ps === '') && (yuju == null || yuju === '');
        if (missing) {
            return '<span class="yuju-audit-cmp yuju-audit-cmp--na">—</span>';
        }
        var psShow = opts.format === 'price' ? formatPriceVal(ps) : (ps == null || ps === '' ? '—' : String(ps));
        var yujuShow = opts.format === 'price' ? formatPriceVal(yuju) : (yuju == null || yuju === '' ? '—' : String(yuju));
        var equal;
        if (opts.format === 'price') {
            var a = Number(ps);
            var b = Number(yuju);
            equal = !isNaN(a) && !isNaN(b) && Math.abs(a - b) <= 0.01;
        } else {
            equal = String(ps) === String(yuju);
        }
        var cls = equal ? 'yuju-audit-cmp--ok' : 'yuju-audit-cmp--diff';
        var title = 'PrestaShop: ' + psShow + ' · Yuju: ' + yujuShow;
        return '<span class="yuju-audit-cmp ' + cls + '" title="' + escapeHtml(title) + '">' +
            escapeHtml(psShow) + '/' + escapeHtml(yujuShow) +
            '</span>';
    }

    function nameCellHtml(name, opts) {
        opts = opts || {};
        var full = name == null ? '' : String(name).trim();
        var emptyMark = opts.emptyMark != null ? opts.emptyMark : '—';
        if (!full) {
            return '<span class="text-muted">' + escapeHtml(emptyMark) + '</span>';
        }
        var short = truncateName(full, 30);
        var tip = escapeHtml(full);
        var html = '<span class="yuju-audit-name">' + escapeHtml(short) + '</span>';
        if (full.length > 30) {
            html += ' <i class="icon-info-sign yuju-audit-tip" title="' + tip + '" data-toggle="tooltip" data-placement="top"></i>';
        } else if (!opts.hideTip) {
            html += ' <i class="icon-info-sign yuju-audit-tip text-muted" title="' + tip + '" data-toggle="tooltip" data-placement="top"></i>';
        }
        return html;
    }

    function statusCellHtml(row, rowIndex) {
        var result = row.result || '';
        var msg = row.message || '';
        var tip = msg || result;
        var icon = 'icon-info-sign';
        var cls = 'yuju-audit-status';
        var label = result;

        if (result === 'matched') {
            icon = 'icon-ok-circle';
            cls += ' yuju-audit-status--ok';
            label = 'OK';
        } else if (result === 'diff_fixed') {
            icon = 'icon-ok';
            cls += ' yuju-audit-status--fixed';
            label = 'Corregido';
        } else if (result === 'diff_found') {
            icon = 'icon-warning-sign';
            cls += ' yuju-audit-status--diff';
            label = 'Diff';
        } else if (result === 'diff_error') {
            icon = 'icon-remove-sign';
            cls += ' yuju-audit-status--error';
            label = 'Error';
        } else if (result === 'not_found') {
            icon = 'icon-question-sign';
            cls += ' yuju-audit-status--missing';
            label = 'No encontrado';
        }

        var tipAttr = escapeHtml(tip);
        var html = '<span class="' + cls + '" title="' + tipAttr + '" data-toggle="tooltip" data-placement="left">' +
            '<i class="' + icon + '"></i>' +
            '</span>';
        if (result === 'diff_error' && row.error_detail) {
            html = '<a href="#" class="yuju-audit-error-link" data-idx="' + rowIndex + '" title="' + tipAttr + '" data-toggle="tooltip" data-placement="left">' +
                '<span class="' + cls + '"><i class="' + icon + '"></i></span>' +
                '</a>';
        }
        return '<div class="yuju-audit-status-wrap" title="' + tipAttr + '">' + html +
            '<span class="sr-only">' + escapeHtml(label) + '</span></div>';
    }

    function refreshTooltips($root) {
        if (!$root || !$.fn.tooltip) return;
        $root.find('[data-toggle="tooltip"]').each(function () {
            var $el = $(this);
            try { $el.tooltip('destroy'); } catch (e1) { /* BS3 */ }
            try { $el.tooltip('dispose'); } catch (e2) { /* BS4 */ }
            $el.tooltip({ container: 'body', html: false, trigger: 'hover focus' });
        });
    }

    function filterLabel(f) {
        var map = {
            total: 'todos',
            matched: 'sincronizados',
            diff: 'con diferencias',
            diff_fixed: 'corregidos',
            diff_error: 'errores',
            not_found: 'no encontrados'
        };
        return map[f] || f;
    }

    function rowMatchesFilter(row, filter) {
        if (!filter || filter === 'total' || filter === 'all') return true;
        if (filter === 'diff') {
            return row.result === 'diff_fixed' || row.result === 'diff_found' || row.result === 'diff_error';
        }
        return row.result === filter;
    }

    function rowMatchesSearch(row, q) {
        if (!q) return true;
        q = q.toLowerCase();
        var hay = ((row.sku || '') + ' ' + (row.product_name || '') + ' ' + (row.message || '')).toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    function diffFieldLabel(field) {
        if (field === 'price') return 'Precio';
        if (field === 'stock') return 'Stock';
        if (field === 'images') return 'Imágenes';
        return field || 'Diff';
    }

    function diffBadgesHtml(row) {
        var diffs = row.diffs || [];
        var fields = [];
        diffs.forEach(function (d) {
            if (d && d.field && fields.indexOf(d.field) === -1) {
                fields.push(d.field);
            }
        });
        if (!fields.length) {
            return '';
        }
        return fields.map(function (f) {
            return '<span class="label label-warning yuju-audit-diff-badge">' + escapeHtml(diffFieldLabel(f)) + '</span>';
        }).join(' ');
    }

    function diffBadgesBlock(row, extraClass) {
        var badges = diffBadgesHtml(row);
        if (!badges) {
            return '';
        }
        return '<div class="' + (extraClass || 'yuju-audit-child-diffs') + '">' + badges + '</div>';
    }

    function worstResult(rows) {
        var order = ['diff_error', 'not_found', 'diff_found', 'diff_fixed', 'matched'];
        var best = 'matched';
        var bestIdx = order.length;
        rows.forEach(function (r) {
            var idx = order.indexOf(r.result || '');
            if (idx >= 0 && idx < bestIdx) {
                bestIdx = idx;
                best = r.result;
            }
        });
        return best;
    }

    function rowGroupKey(row, fallbackIdx) {
        if (row.group_key) return String(row.group_key);
        if (row.prestashop_product_id) return 'ps:' + row.prestashop_product_id;
        if (row.parent_id) return 'yuju:' + row.parent_id;
        if (row.sku_simple) return 'simple:' + row.sku_simple;
        if (row.sku) return 'sku:' + row.sku;
        return 'idx:' + fallbackIdx;
    }

    /** @type {Object.<string, boolean>} */
    var groupExpanded = {};

    function isGroupExpanded(key) {
        if (typeof groupExpanded[key] === 'boolean') {
            return groupExpanded[key];
        }
        return true; // por defecto abiertos (hay diferencias)
    }

    function parentStatusHtml(parent) {
        var fake = {
            result: parent.result,
            message: parent.message || '',
            error_detail: null
        };
        return statusCellHtml(fake, -1);
    }

    function renderFlatRow(row, rowIndex, opts) {
        opts = opts || {};
        var trClass = '';
        if (row.result === 'matched') trClass = 'success';
        else if (row.result === 'diff_fixed' || row.result === 'diff_found') trClass = 'warning';
        else if (row.result === 'diff_error') trClass = 'danger';
        if (opts.child) trClass += ' yuju-audit-child';
        if (opts.hidden) trClass += ' is-hidden';

        var skuCell = escapeHtml(row.sku || '—');

        var nameExtra = opts.child ? diffBadgesBlock(row, 'yuju-audit-child-diffs') : '';

        return '<tr class="' + trClass.trim() + '"'
            + (opts.groupKey ? ' data-group="' + escapeHtml(opts.groupKey) + '"' : '')
            + '>' +
            '<td class="yuju-audit-sku">' + skuCell + '</td>' +
            '<td class="yuju-audit-name-cell">' +
                nameCellHtml(row.product_name, opts.child ? { emptyMark: '-' } : {}) +
                nameExtra +
            '</td>' +
            '<td class="text-center">' + comparePairHtml(row.ps_stock, row.yuju_stock) + '</td>' +
            '<td class="text-center">' + comparePairHtml(row.ps_price, row.yuju_price, { format: 'price' }) + '</td>' +
            '<td class="text-center">' + comparePairHtml(row.ps_images_count, row.yuju_images_count) + '</td>' +
            '<td class="text-center">' + statusCellHtml(row, rowIndex) + '</td>' +
            '</tr>';
    }

    function groupToggleIcon(expanded) {
        return expanded ? '▼' : '▶';
    }

    function renderParentRow(group, expanded) {
        var p = group.parent;
        var trClass = 'yuju-audit-parent info';
        if (p.result === 'diff_error') trClass = 'yuju-audit-parent danger';
        else if (p.result === 'diff_fixed' || p.result === 'diff_found') trClass = 'yuju-audit-parent warning';

        return '<tr class="' + trClass + '" data-group="' + escapeHtml(group.key) + '">' +
            '<td class="yuju-audit-sku">' +
                '<a href="#" class="yuju-audit-toggle-group" data-group="' + escapeHtml(group.key) + '" title="Expandir / contraer">' +
                    '<span class="yuju-audit-chevron" aria-hidden="true">' + groupToggleIcon(expanded) + '</span>' +
                '</a>' +
                escapeHtml(p.sku || '—') +
            '</td>' +
            '<td class="yuju-audit-name-cell">' +
                nameCellHtml(p.product_name) +
                ' <small class="text-muted">(' + p.child_count + ' var.)</small>' +
                diffBadgesBlock(p, 'yuju-audit-parent-diffs') +
            '</td>' +
            '<td class="text-center text-muted">—</td>' +
            '<td class="text-center text-muted">—</td>' +
            '<td class="text-center text-muted">—</td>' +
            '<td class="text-center">' + parentStatusHtml(p) + '</td>' +
            '</tr>';
    }

    function renderTable() {
        var q = ($('#yuju-audit-search').val() || '').trim();
        var rows = serverFiltered
            ? allRows
            : allRows.filter(function (r) {
                return rowMatchesFilter(r, activeFilter) && rowMatchesSearch(r, q);
            });
        var $tbody = $('#yuju-audit-diff-table tbody');
        if (!rows.length) {
            $tbody.html('<tr class="yuju-audit-empty"><td colspan="6" class="text-center text-muted">Sin filas para este filtro/búsqueda.</td></tr>');
            $('#yuju-audit-filter-label').text('Filtro: ' + filterLabel(activeFilter) + ' · 0 filas');
            return;
        }

        var indexed = rows.map(function (r) {
            var realIdx = allRows.indexOf(r);
            return { row: r, rowIndex: realIdx >= 0 ? realIdx : 0 };
        });

        var map = {};
        var order = [];
        indexed.forEach(function (item) {
            var key = rowGroupKey(item.row, item.rowIndex);
            if (!map[key]) {
                map[key] = { key: key, children: [] };
                order.push(key);
            }
            map[key].children.push(item);
        });

        var groups = order.map(function (key) {
            var kids = map[key].children;
            var first = kids[0].row;
            var fields = {};
            kids.forEach(function (c) {
                (c.row.diffs || []).forEach(function (d) {
                    if (d && d.field) fields[d.field] = true;
                });
            });
            return {
                key: key,
                useGroup: kids.length > 1 || !!first.is_variation || !!first.parent_id,
                parent: {
                    sku: first.sku_simple || first.sku || '—',
                    product_name: first.product_name || '',
                    prestashop_product_id: first.prestashop_product_id,
                    result: worstResult(kids.map(function (c) { return c.row; })),
                    message: kids.length + ' variación(es) con diferencias'
                        + (Object.keys(fields).length
                            ? (': ' + Object.keys(fields).map(diffFieldLabel).join(', '))
                            : ''),
                    diffs: Object.keys(fields).map(function (f) { return { field: f }; }),
                    child_count: kids.length
                },
                children: kids
            };
        });

        var html = '';
        var shown = 0;
        var maxRows = 400;

        for (var gi = 0; gi < groups.length && shown < maxRows; gi++) {
            var g = groups[gi];
            if (!g.useGroup) {
                var only = g.children[0];
                html += renderFlatRow(only.row, only.rowIndex, {});
                shown++;
                continue;
            }
            var expanded = isGroupExpanded(g.key);
            html += renderParentRow(g, expanded);
            shown++;
            for (var ci = 0; ci < g.children.length && shown < maxRows; ci++) {
                var ch = g.children[ci];
                html += renderFlatRow(ch.row, ch.rowIndex, {
                    child: true,
                    hidden: !expanded,
                    groupKey: g.key
                });
                shown++;
            }
        }

        $tbody.html(html);
        refreshTooltips($tbody);
        $('#yuju-audit-filter-label').text(
            'Filtro: ' + filterLabel(activeFilter) +
            ' · mostrando ' + Math.min(rows.length, 400) + ' de ' + rows.length
            + ' · ' + groups.length + ' producto(s)'
        );
    }

    $(document).on('click', '.yuju-audit-toggle-group', function (e) {
        e.preventDefault();
        var key = String($(this).attr('data-group') || $(this).data('group') || '');
        if (!key) return;
        groupExpanded[key] = !isGroupExpanded(key);
        var expanded = groupExpanded[key];
        $(this).find('.yuju-audit-chevron').text(groupToggleIcon(expanded));
        $('#yuju-audit-diff-table tbody tr.yuju-audit-child').filter(function () {
            return String($(this).attr('data-group') || $(this).data('group') || '') === key;
        }).toggleClass('is-hidden', !expanded);
    });

    var renderDebounce = null;
    function appendRows(rows) {
        if (!rows || !rows.length) return;
        serverFiltered = false;
        rows.forEach(function (r) { allRows.push(r); });
        // Evitar re-pintar la tabla en cada lote (era muy lento con cientos/miles de filas)
        if (renderDebounce) clearTimeout(renderDebounce);
        renderDebounce = setTimeout(function () {
            renderDebounce = null;
            renderTable();
        }, 250);
    }

    function updateProgress(p) {
        if (!p) return;
        $('#yuju-audit-progress-wrap').show();
        $('#yuju-audit-kpis').show();
        $('#yuju-audit-table-tools').show();
        $('#kpi-total').text(p.total || 0);
        $('#kpi-matched').text(p.matched || 0);
        $('#kpi-diff').text(p.diff_found || 0);
        $('#kpi-fixed').text(p.fixed || 0);
        $('#kpi-errors').text(p.errors || 0);
        $('#kpi-notfound').text(p.not_found || 0);
        var pct = p.percent || 0;
        $('#yuju-audit-progress-bar').css('width', pct + '%').text(pct + '%');
        var dur = p.duration_human || formatDuration(elapsedMs());
        $('#yuju-audit-progress-text').text(
            'Procesados ' + (p.processed || 0) + ' / ' + (p.total || 0) +
            ' · tiempo ' + dur +
            (p.status === 'paused' ? ' · Pausado' : (p.status === 'completed' ? ' · Finalizado' : ' · En curso'))
        );
        if (p.duration_seconds != null) {
            $('#yuju-audit-timer').text('⏱ ' + formatDuration((p.duration_seconds || 0) * 1000)).show();
        }
        $('#yuju-audit-export-btn').prop('disabled', !runId);
    }

    function setPauseUi(isPaused) {
        paused = !!isPaused;
        if (paused) {
            $('#yuju-audit-pause-btn').hide();
            $('#yuju-audit-resume-btn').show();
            $('#yuju-audit-progress-bar').removeClass('active');
        } else if (running) {
            $('#yuju-audit-pause-btn').show();
            $('#yuju-audit-resume-btn').hide();
            $('#yuju-audit-progress-bar').addClass('active');
        } else {
            $('#yuju-audit-pause-btn').hide();
            $('#yuju-audit-resume-btn').hide();
        }
    }

    function unlockUi() {
        running = false;
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
        $('#yuju-audit-start-btn').prop('disabled', false)
            .html('<i class="icon-cloud-download"></i> Solicitar reporte y auditar');
        $('#yuju-audit-reanalyze-btn').prop('disabled', false);
        setPauseUi(false);
    }

    function finishUi(progress) {
        unlockUi();
        stopClock(progress && progress.duration_seconds);
        $('#yuju-audit-progress-bar').removeClass('active progress-bar-info').addClass('progress-bar-success');
        var p = progress || {};
        var mon = monitoringLink
            ? ' · <a href="' + escapeHtml(monitoringLink) + '">Ver en Monitoreo</a>'
            : '';
        setStatus(
            'success',
            '<strong>Auditoría finalizada.</strong> ' +
            'Sincronizados: ' + (p.matched || 0) +
            ' · Diferencias: ' + (p.diff_found || 0) +
            ' · Corregidos: ' + (p.fixed || 0) +
            ' · Errores: ' + (p.errors || 0) +
            ' · No encontrados: ' + (p.not_found || 0) +
            ' · Tiempo: ' + (p.duration_human || formatDuration(elapsedMs())) + mon
        );
        // Recargar desde BD (durante el run solo se streameron diffs / no_found)
        applyKpiFilter('diff');
    }

    function processChunk() {
        if (paused) return;
        var applying = $('#yuju-audit-apply-fixes').is(':checked');
        setStatus(
            'info',
            '<i class="icon-spinner icon-spin"></i> Comparando lote… run #' + runId +
            (applying ? ' · corrigiendo Yuju' : ' · solo local (sin API)')
        );
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            timeout: 180000,
            data: {
                ajax: 1,
                action: 'auditChunk',
                token: token,
                run_id: runId
                // sin limit: el servidor usa chunk grande + presupuesto por petición
            }
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (!resp || !resp.success) {
                unlockUi();
                stopClock();
                $('#yuju-audit-progress-bar').removeClass('active progress-bar-info').addClass('progress-bar-danger');
                setStatus('danger', '<strong>Error:</strong> ' + ((resp && resp.message) ? resp.message : 'Error al procesar chunk'));
                return;
            }
            if (resp.paused) {
                setPauseUi(true);
                updateProgress(resp.progress);
                setStatus('warning', 'Auditoría pausada.');
                return;
            }
            updateProgress(resp.progress);
            appendRows(resp.rows || []);
            if (resp.finished) {
                finishUi(resp.progress);
            } else if (!paused) {
                setTimeout(processChunk, 10);
            }
        }).fail(function (xhr) {
            unlockUi();
            stopClock();
            setStatus('danger', '<strong>Error:</strong> ' + ajaxFailMessage(xhr, 'Error al procesar'));
        });
    }

    function beginCompare(resp) {
        runId = resp.run_id;
        reportId = resp.report_id || reportId;
        allRows = [];
        groupExpanded = {};
        // Durante el stream solo llegan diffs; el filtro total se carga al final desde BD
        activeFilter = 'diff';
        startedAtMs = Date.now();
        pauseAccumMs = 0;
        pausedAtMs = null;
        startClock();
        running = true;
        setPauseUi(false);
        $('#yuju-audit-export-btn').prop('disabled', false);
        $('#yuju-audit-table-tools').show();
        var applying = !!(resp.apply_fixes);
        var rType = resp.report_type || (wantsImages() ? 'gral' : 'offer');
        var typeLabel = rType === 'offer' ? 'OFERTA (stock/precio)' : 'GENERAL (con imágenes)';
        showTask({
            id_task: resp.id_task,
            report_id: reportId,
            api_status: (rType === 'offer' ? 'offer' : 'gral') + ' · ' +
                (applying ? 'comparando+corrigiendo' : 'solo comparación local')
        });
        setStatus(
            'info',
            '<i class="icon-ok"></i> JSONL ' + typeLabel + ' listo. Run #' + runId +
            ' · ' + (resp.total || 0) + ' filas' +
            (applying
                ? ' · <strong>corrigiendo en Yuju</strong>'
                : ' · <strong>sin llamar a la API</strong>')
        );
        updateProgress({
            total: resp.total || 0,
            processed: 0,
            matched: 0,
            diff_found: 0,
            fixed: 0,
            errors: 0,
            not_found: 0,
            percent: 0,
            status: 'running',
            duration_seconds: 0
        });
        $('#yuju-audit-diff-table tbody').html(
            '<tr class="yuju-audit-empty"><td colspan="6" class="text-center text-muted">Comparando productos…</td></tr>'
        );
        processChunk();
    }

    function pollGral() {
        var data = $.extend({
            ajax: 1,
            action: 'pollGralForAudit',
            token: token,
            report_id: reportId
        }, fieldsPayload());

        setStatus('warning', '<i class="icon-spinner icon-spin"></i> Esperando generación del reporte…');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            timeout: 120000,
            data: data
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (!resp || !resp.success) {
                unlockUi();
                stopClock();
                setStatus('danger', '<strong>Error al consultar tarea:</strong> ' + ((resp && resp.message) ? resp.message : 'falló'));
                return;
            }
            if (resp.phase === 'waiting') {
                showTask({
                    id_task: resp.id_task,
                    report_id: resp.report_id,
                    api_status: resp.api_status || 'PROCESSING'
                });
                pollTimer = setTimeout(pollGral, 4000);
                return;
            }
            if (resp.phase === 'compare' && resp.run_id) {
                beginCompare(resp);
                return;
            }
            unlockUi();
            stopClock();
            setStatus('danger', 'Respuesta inesperada al consultar la tarea.');
        }).fail(function (xhr) {
            unlockUi();
            stopClock();
            setStatus('danger', '<strong>Error:</strong> ' + ajaxFailMessage(xhr, 'Error al consultar id_task'));
        });
    }

    function requestNewReport() {
        if (running) return;
        if (!hasFields()) {
            setStatus('warning', 'Seleccione al menos un campo (stock, precio o imágenes).');
            return;
        }
        running = true;
        allRows = [];
        groupExpanded = {};
        var useGral = wantsImages();
        $('#yuju-audit-start-btn').prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Solicitando…');
        $('#yuju-audit-reanalyze-btn').prop('disabled', true);
        $('#yuju-audit-progress-wrap').show();
        $('#yuju-audit-progress-bar').removeClass('progress-bar-success progress-bar-danger').addClass('progress-bar-info active').css('width', '8%').text('…');
        setStatus(
            'info',
            '<i class="icon-spinner icon-spin"></i> Solicitando ' +
            (useGral ? 'products-gral-report…' : 'products-offer-report…')
        );

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            timeout: 180000,
            data: $.extend({ ajax: 1, action: 'requestReportForAudit', token: token }, fieldsPayload())
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (!resp || !resp.success) {
                unlockUi();
                updateQuotaLabel();
                if (resp && resp.offer_quota) cfg.offer_quota = resp.offer_quota;
                if (resp && resp.quota) cfg.offer_quota = resp.quota;
                showTask({
                    id_task: (resp && resp.id_task) || '—',
                    report_id: (resp && resp.report_id) || '—',
                    api_status: (resp && resp.blocked) ? 'API BLOQUEADA' : 'FALLÓ',
                    progress: (resp && resp.progress) || null
                });
                if (resp && resp.progress) {
                    setOfferProgressBar(resp.progress, true);
                }
                var detailMsg = (resp && resp.message) ? resp.message : 'No se pudo solicitar';
                if (resp && resp.api_message) {
                    detailMsg += '<br><small class="text-muted">API: ' + escapeHtml(resp.api_message) + '</small>';
                }
                if (resp && resp.unlock_at) {
                    detailMsg += '<br><small>Desbloqueo: <code>' + escapeHtml(resp.unlock_at) + '</code></small>';
                }
                setStatus('danger', '<strong>Error:</strong> ' + detailMsg);
                return;
            }
            if (resp.gral_quota) cfg.gral_quota = resp.gral_quota;
            if (resp.quota) cfg.gral_quota = resp.quota;
            if (resp.offer_quota) cfg.offer_quota = resp.offer_quota;
            updateQuotaLabel();
            reportId = resp.report_id;
            showTask({
                id_task: resp.id_task,
                report_id: reportId,
                api_status: (resp.report_type || (useGral ? 'gral' : 'offer')) + ' · ' +
                    (resp.phase === 'compare' ? 'COMPLETED' : 'PROCESSING'),
                progress: resp.progress || null
            });
            if (resp.progress) {
                setOfferProgressBar(resp.progress, false);
            }
            if (resp.phase === 'compare' && resp.run_id) {
                beginCompare(resp);
                return;
            }
            // Solo gral es asíncrono (waiting)
            pollGral();
        }).fail(function (xhr) {
            unlockUi();
            updateQuotaLabel();
            setStatus('danger', '<strong>Error:</strong> ' + ajaxFailMessage(xhr, 'Error al solicitar reporte'));
        });
    }

    function reanalyzeSelected() {
        if (running) return;
        if (!hasFields()) {
            setStatus('warning', 'Seleccione al menos un campo (stock, precio o imágenes).');
            return;
        }
        var selected = parseInt($('#yuju-audit-report-select').val(), 10) || 0;
        if (!selected) {
            setStatus('warning', 'Elija un reporte completado para reanalizar.');
            return;
        }
        running = true;
        allRows = [];
        groupExpanded = {};
        $('#yuju-audit-start-btn').prop('disabled', true);
        $('#yuju-audit-reanalyze-btn').prop('disabled', true);
        setStatus('info', '<i class="icon-spinner icon-spin"></i> Reanalizando reporte #' + selected + '…');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            timeout: 180000,
            data: $.extend({
                ajax: 1,
                action: 'reanalyzeReport',
                token: token,
                report_id: selected
            }, fieldsPayload())
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (!resp || !resp.success) {
                unlockUi();
                setStatus('danger', '<strong>Error al reanalizar:</strong> ' + ((resp && resp.message) ? resp.message : 'falló'));
                return;
            }
            beginCompare(resp);
        }).fail(function (xhr) {
            unlockUi();
            setStatus('danger', '<strong>Error:</strong> ' + ajaxFailMessage(xhr, 'Error al reanalizar'));
        });
    }

    function pauseAudit() {
        if (!runId || !running || paused) return;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            data: { ajax: 1, action: 'pauseAudit', token: token, run_id: runId }
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (resp && resp.success) {
                pausedAtMs = Date.now();
                setPauseUi(true);
                updateProgress(resp.progress);
                setStatus('warning', 'Auditoría pausada. Puede continuar cuando quiera.');
            } else {
                setStatus('danger', (resp && resp.message) || 'No se pudo pausar');
            }
        });
    }

    function resumeAudit() {
        if (!runId || !paused) return;
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            data: { ajax: 1, action: 'resumeAudit', token: token, run_id: runId }
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (resp && resp.success) {
                if (pausedAtMs) {
                    pauseAccumMs += (Date.now() - pausedAtMs);
                    pausedAtMs = null;
                }
                running = true;
                setPauseUi(false);
                updateProgress(resp.progress);
                setStatus('info', 'Reanudando auditoría…');
                processChunk();
            } else {
                setStatus('danger', (resp && resp.message) || 'No se pudo reanudar');
            }
        });
    }

    function exportCsv() {
        if (!runId) return;
        var result = activeFilter === 'total' ? '' : activeFilter;
        var search = $('#yuju-audit-search').val() || '';
        var url = ajaxUrl +
            (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') +
            'ajax=1&action=exportAuditCsv&token=' + encodeURIComponent(token) +
            '&run_id=' + encodeURIComponent(runId) +
            '&result=' + encodeURIComponent(result) +
            '&search=' + encodeURIComponent(search);
        window.location.href = url;
    }

    function applyKpiFilter(filter) {
        activeFilter = filter || 'total';
        $('.yuju-kpi-filter').css('outline', '');
        $('.yuju-kpi-filter[data-filter="' + activeFilter + '"]').css('outline', '2px solid #337ab7');
        if (!runId) {
            serverFiltered = false;
            renderTable();
            return;
        }
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'text',
            data: {
                ajax: 1,
                action: 'getAuditRows',
                token: token,
                run_id: runId,
                result: activeFilter === 'total' ? '' : activeFilter,
                search: $('#yuju-audit-search').val() || '',
                limit: 5000,
                offset: 0
            }
        }).done(function (raw) {
            var resp = parseJsonLoose(raw);
            if (resp && resp.success) {
                allRows = resp.rows || [];
                serverFiltered = true;
                renderTable();
                serverFiltered = false;
            } else {
                serverFiltered = false;
                renderTable();
            }
        }).fail(function () {
            serverFiltered = false;
            renderTable();
        });
    }

    $(function () {
        updateQuotaLabel();
        $('#yuju-audit-field-images, #yuju-audit-field-stock, #yuju-audit-field-price')
            .on('change', updateQuotaLabel);
        $('#yuju-audit-start-btn').on('click', function (e) {
            e.preventDefault();
            requestNewReport();
        });
        $('#yuju-audit-reanalyze-btn').on('click', function (e) {
            e.preventDefault();
            reanalyzeSelected();
        });
        $('#yuju-audit-pause-btn').on('click', function (e) {
            e.preventDefault();
            pauseAudit();
        });
        $('#yuju-audit-resume-btn').on('click', function (e) {
            e.preventDefault();
            resumeAudit();
        });
        $('#yuju-audit-export-btn').on('click', function (e) {
            e.preventDefault();
            exportCsv();
        });
        $('#yuju-audit-search-btn').on('click', function () {
            applyKpiFilter(activeFilter);
        });
        $('#yuju-audit-search').on('keypress', function (e) {
            if (e.which === 13) applyKpiFilter(activeFilter);
        });
        $(document).on('click', '.yuju-kpi-filter', function () {
            applyKpiFilter($(this).data('filter'));
        });
        $(document).on('click', '.yuju-audit-error-link', function (e) {
            e.preventDefault();
            var idx = parseInt($(this).data('idx'), 10);
            var viewRows = serverFiltered
                ? allRows
                : allRows.filter(function (r) {
                    return rowMatchesFilter(r, activeFilter) && rowMatchesSearch(r, ($('#yuju-audit-search').val() || '').trim());
                });
            // Al renderizar usamos slice(0,400) de esos rows
            var row = viewRows[idx];
            var detail = row && row.error_detail ? row.error_detail : { message: row && row.message };
            $('#yuju-audit-error-json').text(JSON.stringify(detail, null, 2));
            $('#yujuAuditErrorModal').modal('show');
        });
    });
})(jQuery);
