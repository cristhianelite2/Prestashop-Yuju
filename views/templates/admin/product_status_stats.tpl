{*
 * Panel de estadísticas de Estado de Productos.
 * Se renderiza antes del listado administrativo de productos.
 *}
<div class="panel panel-default yuju-module yuju-product-status-summary">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i>
        Estado de Productos
    </div>
    <div class="panel-body">
        {if isset($sync_running) && $sync_running}
            <div class="alert alert-info">
                <i class="icon-refresh"></i> Hay una sincronización en curso.
            </div>
        {/if}

        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="product-stat-card text-center">
                    <h3 class="text-primary">{if isset($stats.total_products)}{$stats.total_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">Productos activos</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="product-stat-card text-center">
                    <h3 class="text-success">{if isset($stats.synced_products)}{$stats.synced_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">Sincronizados</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="product-stat-card text-center">
                    <h3 class="text-warning">{if isset($stats.pending_products)}{$stats.pending_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">Pendientes</p>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="product-stat-card text-center">
                    <h3 class="text-danger">{if isset($stats.error_products)}{$stats.error_products|intval}{else}0{/if}</h3>
                    <p class="text-muted">Con errores</p>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top:20px;">
            <div class="col-md-6">
                <div class="product-stat-card">
                    <h5>Porcentaje de sincronización</h5>
                    {assign var="sync_rate" value=0}
                    {if isset($stats.total_products) && $stats.total_products > 0}
                        {assign var="sync_rate" value=($stats.synced_products / $stats.total_products * 100)}
                    {/if}
                    {if $sync_rate > 100}{assign var="sync_rate" value=100}{/if}
                    <div class="progress">
                        <div class="progress-bar progress-bar-success" role="progressbar"
                             aria-valuenow="{$sync_rate|string_format:'%.1f'|escape:'html':'UTF-8'}"
                             aria-valuemin="0" aria-valuemax="100"
                             style="width:{$sync_rate|string_format:'%.1f'|escape:'html':'UTF-8'}%">
                            {$sync_rate|string_format:"%.1f"|escape:'html':'UTF-8'}%
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="product-stat-card">
                    <h5>Última actividad de sincronización</h5>
                    <p class="text-info">
                        <strong>
                            {if isset($stats.last_sync_date) && $stats.last_sync_date}
                                {$stats.last_sync_date|date_format:"%d/%m/%Y %H:%M"|escape:'html':'UTF-8'}
                            {else}
                                Sin sincronizaciones registradas
                            {/if}
                        </strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="row" style="margin-top:20px;">
            <div class="col-md-6">
                <h4><i class="icon-history"></i> Actividad reciente</h4>
                {if isset($stats.recent_syncs) && count($stats.recent_syncs) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$stats.recent_syncs item=sync}
                                    <tr>
                                        <td>
                                            <strong>{$sync.product_name|escape:'html':'UTF-8'}</strong>
                                            {if $sync.product_id}<br><small class="text-muted">ID: {$sync.product_id|intval}</small>{/if}
                                        </td>
                                        <td>
                                            {if $sync.sync_status == 'synced' || $sync.sync_status == 'synced_with_warnings'}
                                                <span class="label label-success">Sincronizado</span>
                                            {elseif $sync.sync_status == 'error' || $sync.sync_status == 'synced_with_errors'}
                                                <span class="label label-danger">Error</span>
                                            {elseif $sync.sync_status == 'pending'}
                                                <span class="label label-warning">Pendiente</span>
                                            {elseif $sync.sync_status == 'disabled'}
                                                <span class="label label-default">Deshabilitado</span>
                                            {else}
                                                <span class="label label-default">{$sync.sync_status|escape:'html':'UTF-8'}</span>
                                            {/if}
                                        </td>
                                        <td>{if $sync.last_sync_attempt}{$sync.last_sync_attempt|date_format:"%d/%m/%Y %H:%M:%S"|escape:'html':'UTF-8'}{else}<span class="text-muted">Sin fecha</span>{/if}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <p class="text-muted">Todavía no hay actividad de sincronización registrada.</p>
                {/if}
            </div>

            <div class="col-md-6">
                <h4><i class="icon-warning-sign"></i> Errores recientes</h4>
                {if isset($stats.recent_errors) && count($stats.recent_errors) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Detalle del error</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$stats.recent_errors item=error}
                                    <tr>
                                        <td>
                                            <strong>{$error.product_name|escape:'html':'UTF-8'}</strong>
                                            {if $error.product_id}<br><small class="text-muted">ID: {$error.product_id|intval}</small>{/if}
                                        </td>
                                        <td><small class="text-danger">{$error.error_message|escape:'html':'UTF-8'|truncate:100}</small></td>
                                        <td>{if $error.error_date}{$error.error_date|date_format:"%d/%m/%Y %H:%M:%S"|escape:'html':'UTF-8'}{else}<span class="text-muted">Sin fecha</span>{/if}</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <p class="text-muted">No hay errores recientes registrados.</p>
                {/if}
            </div>
        </div>
    </div>
</div>

<style>
.yuju-product-status-summary .product-stat-card {
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    margin-bottom: 10px;
    min-height: 82px;
}
.yuju-product-status-summary .product-stat-card h3 {
    margin: 0 0 5px;
    font-weight: bold;
}
.yuju-product-status-summary .product-stat-card h5 {
    margin: 0 0 12px;
    color: #333;
    font-weight: 600;
}
.yuju-product-status-summary .progress {
    margin-bottom: 0;
}
</style>
