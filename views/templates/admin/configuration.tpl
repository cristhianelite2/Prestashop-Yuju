{*
* 2024 Yuju Integration
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* @author    Yuju Integration Team
* @copyright 2024 Yuju Integration
* @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}

{extends file="./layout.tpl"}

{block name="content"}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i>
        Configuración de Integración Yuju
    </div>
    
    <div class="panel-body">
        {if isset($oauth_status) && $oauth_status.is_connected}
            <div class="alert alert-success">
                <i class="icon-check"></i>
                Conectado exitosamente a la API de Yuju
                <br>
                <small>Conectado como: {$oauth_status.user_info.name|default:'Desconocido'|escape:'html':'UTF-8'}</small>
            </div>
        {else}
            <div class="alert alert-warning">
                <i class="icon-warning"></i>
                No conectado a la API de Yuju. Registre su aplicación en Yuju (Configuraciones &gt; Aplicaciones) con las URLs de abajo, guarde aquí el Client ID y el Secret Key y pulse &quot;Conectar&quot; desde Yuju.
            </div>
        {/if}
        
        {if isset($api_test_result)}
            {if $api_test_result.success}
                <div class="alert alert-success">
                    <i class="icon-check"></i>
                    Prueba de conexión API exitosa
                </div>
            {else}
                <div class="alert alert-danger">
                    <i class="icon-remove"></i>
                    Prueba de conexión API falló: {$api_test_result.error|escape:'html':'UTF-8'}
                </div>
            {/if}
        {/if}
        
        <form id="configuration_form" class="defaultForm form-horizontal" action="{$current_index|escape:'html':'UTF-8'}&token={$token|escape:'html':'UTF-8'}" method="post" enctype="multipart/form-data">
            
            {* URLs Importantes Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-link"></i>
                        URLs Importantes
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        Estas URLs son requeridas para configurar su aplicación Yuju. Haga clic para copiarlas fácilmente.
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Términos y Condiciones
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}" readonly id="terms_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.terms_conditions|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL a la página de términos y condiciones del módulo (úsela si no tiene una propia para el registro de aplicaciones Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Autenticación
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.auth_url|escape:'html':'UTF-8'}" readonly id="auth_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.auth_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL que Yuju abre con el parámetro code al pulsar "Conectar" (configure esto como URL de Autenticación en su aplicación Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URL de Webhook
                        </label>
                        <div class="col-lg-9">
                            <div class="input-group">
                                <input type="text" class="form-control" value="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}" readonly id="webhook_url">
                                <span class="input-group-btn">
                                    <button class="btn btn-default yuju-copy-button" type="button" data-copy-text="{$yuju_urls.webhook_url|escape:'html':'UTF-8'}">
                                        <i class="icon-copy"></i> Copiar
                                    </button>
                                </span>
                            </div>
                            <p class="help-block">URL para recibir webhooks de Yuju (configure esto en su aplicación Yuju)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            URLs Permitidas
                        </label>
                        <div class="col-lg-9">
                            <input type="text" class="form-control" name="YUJU_ALLOWED_URLS" id="YUJU_ALLOWED_URLS" value="{$config.YUJU_ALLOWED_URLS|escape:'html':'UTF-8'}" placeholder="https://su-tienda.com">
                            <p class="help-block">Dominio permitido para la conexión con Yuju. Puede agregar más separándolos por comas (configúrelos en las URLs permitidas de su aplicación Yuju)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* API Configuration Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-cloud"></i>
                        Configuración de API
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Entorno
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_ENVIRONMENT" class="form-control">
                                <option value="sandbox" {if $config.YUJU_ENVIRONMENT == 'sandbox'}selected{/if}>
                                    Sandbox (Pruebas)
                                </option>
                                <option value="production" {if $config.YUJU_ENVIRONMENT == 'production' || !$config.YUJU_ENVIRONMENT}selected{/if}>
                                    Producción
                                </option>
                            </select>
                            <p class="help-block">Seleccione el entorno de Yuju al que conectarse</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            ID de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_CLIENT_ID" value="{$config.YUJU_CLIENT_ID|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">Su ID de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3 required">
                            Secreto de Cliente
                        </label>
                        <div class="col-lg-9">
                            <input type="password" name="YUJU_CLIENT_SECRET" value="{$config.YUJU_CLIENT_SECRET|escape:'html':'UTF-8'}" class="form-control" required>
                            <p class="help-block">Su Secreto de Cliente de la aplicación Yuju</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Usar ruta alternativa OAuth (/yuju/oauth)
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_USE_ALTERNATIVE_OAUTH_ROUTE" id="alt_oauth_on" value="1" {if $config.YUJU_USE_ALTERNATIVE_OAUTH_ROUTE}checked="checked"{/if}>
                                <label for="alt_oauth_on">Sí</label>
                                <input type="radio" name="YUJU_USE_ALTERNATIVE_OAUTH_ROUTE" id="alt_oauth_off" value="0" {if !$config.YUJU_USE_ALTERNATIVE_OAUTH_ROUTE}checked="checked"{/if}>
                                <label for="alt_oauth_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">
                                <strong>Habilite esto si su servidor bloquea /shop/modules/</strong><br>
                                Usa la ruta <code>/yuju/oauth</code> (archivo directo en <code>/shop/yuju/oauth.php</code>) en lugar de <code>/module/prestashopyuju/oauth</code>.<br>
                                <strong>Importante:</strong> Al cambiar esto, actualice la <strong>URL de Autenticación</strong> en su aplicación Yuju a la que se muestra arriba.
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Secreto de Webhook
                        </label>
                        <div class="col-lg-9">
                            <input type="text" name="YUJU_WEBHOOK_SECRET" value="{$config.YUJU_WEBHOOK_SECRET|escape:'html':'UTF-8'}" class="form-control">
                            <p class="help-block">Clave secreta para verificación de firma de webhook</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Conectividad
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-test-connectivity" class="btn btn-info">
                                <i class="icon-plug"></i> Probar Conectividad
                            </button>
                            <p class="help-block">Probar la conexión con la API de Yuju y mostrar las tiendas disponibles</p>
                            <div id="connectivity-result" class="alert" style="display: none; margin-top: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            {* Synchronization Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-refresh"></i>
                        Configuración de Sincronización
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Habilitar Sincronización Automática
                        </label>
                        <div class="col-lg-9">
                            <span class="switch prestashop-switch fixed-width-lg">
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_on" value="1" {if $config.YUJU_AUTO_SYNC || $config.YUJU_AUTO_SYNC === null}checked="checked"{/if}>
                                <label for="auto_sync_on">Sí</label>
                                <input type="radio" name="YUJU_AUTO_SYNC" id="auto_sync_off" value="0" {if $config.YUJU_AUTO_SYNC === 0}checked="checked"{/if}>
                                <label for="auto_sync_off">No</label>
                                <a class="slide-button btn"></a>
                            </span>
                            <p class="help-block">Sincronizar datos automáticamente cuando ocurran cambios</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Frecuencia de Sincronización (segundos)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_SYNC_FREQUENCY" value="{$config.YUJU_SYNC_FREQUENCY|default:300|escape:'html':'UTF-8'}" class="form-control" min="60">
                            <p class="help-block">Con qué frecuencia ejecutar la sincronización en segundo plano (mínimo 60 segundos)</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Tamaño de Lote
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_BATCH_SIZE" value="{$config.YUJU_BATCH_SIZE|default:100|escape:'html':'UTF-8'}" class="form-control" min="1" max="500">
                            <p class="help-block">Número de elementos a procesar por lote (1-500)</p>
                        </div>
                    </div>
                </div>
            </div>

            {* Logging Settings *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-file-text"></i>
                        Configuración de Registro
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Nivel de Registro
                        </label>
                        <div class="col-lg-9">
                            <select name="YUJU_LOG_LEVEL" class="form-control">
                                <option value="error" {if $config.YUJU_LOG_LEVEL == 'error'}selected{/if}>
                                    Solo errores
                                </option>
                                <option value="warning" {if $config.YUJU_LOG_LEVEL == 'warning'}selected{/if}>
                                    Advertencias y superiores
                                </option>
                                <option value="info" {if $config.YUJU_LOG_LEVEL == 'info'}selected{/if}>
                                    Información y superiores
                                </option>
                                <option value="debug" {if $config.YUJU_LOG_LEVEL == 'debug'}selected{/if}>
                                    Debug (todo)
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Retención de Registros (días)
                        </label>
                        <div class="col-lg-9">
                            <input type="number" name="YUJU_LOG_RETENTION" value="{$config.YUJU_LOG_RETENTION|default:30|escape:'html':'UTF-8'}" class="form-control" min="1">
                            <p class="help-block">Número de días para mantener archivos de registro</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {* OAuth Debug Logs Section *}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <i class="icon-list-alt"></i>
                        Historial de Intentos OAuth (Debug)
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="control-label col-lg-3">
                            Acciones
                        </label>
                        <div class="col-lg-9">
                            <button type="button" id="yuju-load-oauth-attempts" class="btn btn-default">
                                <i class="icon-refresh"></i> Cargar Historial
                            </button>
                            <button type="button" id="yuju-clean-oauth-attempts" class="btn btn-default" style="margin-left: 10px;">
                                <i class="icon-trash"></i> Limpiar > 30 días
                            </button>
                            <p class="help-block">Muestra todos los intentos de intercambio de code por token con detalles completos (request/response/headers/cURL verbose).</p>
                        </div>
                    </div>
                    
                    <div id="oauth-attempts-container" style="display: none; margin-top: 20px;">
                        <div class="alert alert-info">
                            <i class="icon-info-circle"></i>
                            Haz clic en cualquier fila para expandir/colapsar los detalles completos.
                        </div>
                        <div class="panel-group" id="oauth-attempts-accordion" role="tablist" aria-multiselectable="true">
                            <!-- Attempts will be inserted here via JS -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel-footer">
                <button type="submit" value="1" id="configuration_form_submit_btn" name="submitConfiguration" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> Guardar
                </button>
                
                {if $config.YUJU_CLIENT_ID && $config.YUJU_CLIENT_SECRET}
                    <button type="submit" name="testConnection" class="btn btn-info">
                        <i class="icon-check"></i> Probar Conexión API
                    </button>
                {/if}
            </div>
        </form>
    </div>
</div>


{/block}

<!-- ✅ SOLUCIÓN COMPATIBLE CON PRESTASHOP -->
<!-- Reemplaza TODO el bloque <script> en configuration.tpl con esto: -->

<script type="text/javascript">
// Token para AJAX (parseado por Smarty antes del bloque literal)
window.yujuAdminToken = '{$token|escape:"javascript"}';
</script>

{literal}
<script type="text/javascript">
// JavaScript functionality is now handled in admin.js
// This ensures compatibility with PrestaShop 8 module loading system
console.log('✅ Configuration template loaded - JavaScript handled by admin.js');

// OAuth Attempts Accordion Functions
function loadOAuthAttempts() {
    const container = document.getElementById('oauth-attempts-container');
    const accordion = document.getElementById('oauth-attempts-accordion');
    const btn = document.getElementById('yuju-load-oauth-attempts');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="icon-spinner icon-spin"></i> Cargando...';
    
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            controller: 'AdminYujuConfiguration',
            ajax: 'true',
            action: 'GetOAuthAttempts',
            token: window.yujuAdminToken,
            limit: 50
        },
        dataType: 'json',
        success: function(response) {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-refresh"></i> Cargar Historial';
            
            if (response.success && response.data) {
                accordion.innerHTML = '';
                
                if (response.data.length === 0) {
                    accordion.innerHTML = '<div class="alert alert-info">No hay intentos OAuth registrados.</div>';
                } else {
                    response.data.forEach(function(attempt, index) {
                        const panelId = 'oauth-attempt-' + attempt.id;
                        const collapseId = 'collapse-' + attempt.id;
                        const statusClass = attempt.status === 'success' ? 'panel-success' : 
                                           attempt.status === 'error' ? 'panel-danger' : 'panel-warning';
                        const statusIcon = attempt.status === 'success' ? 'icon-check-circle text-success' : 
                                          attempt.status === 'error' ? 'icon-exclamation-circle text-danger' : 'icon-clock text-warning';
                        const statusLabel = attempt.status === 'success' ? 'Éxito' : 
                                           attempt.status === 'error' ? 'Error' : 'Iniciado';
                        
                        const panelHtml = `
                            <div class="panel ${statusClass}" id="${panelId}">
                                <div class="panel-heading" role="tab" id="heading-${attempt.id}">
                                    <h4 class="panel-title">
                                        <a role="button" data-toggle="collapse" data-parent="#oauth-attempts-accordion" href="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}" style="text-decoration: none; color: inherit; display: block; width: 100%;">
                                            <i class="${statusIcon}"></i>
                                            <strong> ${statusLabel}</strong> - ${attempt.created_at}
                                            <span class="pull-right text-muted small">
                                                HTTP ${attempt.http_code || 'N/A'} | 
                                                Client: ${attempt.client_id || 'N/A'}...
                                            </span>
                                        </a>
                                    </h4>
                                </div>
                                <div id="${collapseId}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading-${attempt.id}">
                                    <div class="panel-body" id="detail-${attempt.id}">
                                        <div class="text-center" style="padding: 20px;">
                                            <i class="icon-spinner icon-spin"></i> Cargando detalles...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        accordion.insertAdjacentHTML('beforeend', panelHtml);
                        
                        // Load detail on first expand
                        $('#' + collapseId).one('shown.bs.collapse', function() {
                            loadAttemptDetail(attempt.id);
                        });
                    });
                }
                
                container.style.display = 'block';
            } else {
                alert('Error cargando intentos: ' + (response.message || 'Desconocido'));
            }
        },
        error: function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-refresh"></i> Cargar Historial';
            alert('Error de comunicación con el servidor');
        }
    });
}

function loadAttemptDetail(id) {
    const detailContainer = document.getElementById('detail-' + id);
    
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            controller: 'AdminYujuConfiguration',
            ajax: 'true',
            action: 'GetOAuthAttemptDetail',
            token: window.yujuAdminToken,
            id: id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const d = response.data;
                let html = '';
                
                // Request
                if (d.request_data_decoded) {
                    html += '<h5><i class="icon-upload"></i> Request Enviado</h5>';
                    html += '<pre class="bg-light p-3" style="max-height: 300px; overflow: auto;">' + escapeHtml(JSON.stringify(d.request_data_decoded, null, 2)) + '</pre>';
                }
                
                // Response
                if (d.response_data_decoded) {
                    html += '<h5><i class="icon-download"></i> Response Recibido</h5>';
                    html += '<pre class="bg-light p-3" style="max-height: 300px; overflow: auto;">' + escapeHtml(JSON.stringify(d.response_data_decoded, null, 2)) + '</pre>';
                } else if (d.response_body) {
                    html += '<h5><i class="icon-download"></i> Response Recibido (Raw)</h5>';
                    html += '<pre class="bg-light p-3" style="max-height: 300px; overflow: auto;">' + escapeHtml(d.response_body) + '</pre>';
                }
                
                // HTTP Info
                html += '<h5><i class="icon-info"></i> Info HTTP</h5>';
                html += '<table class="table table-bordered table-striped">';
                html += '<tr><th>HTTP Code</th><td>' + (d.http_code || 'N/A') + '</td></tr>';
                html += '<tr><th>Message</th><td>' + escapeHtml(d.message || 'N/A') + '</td></tr>';
                if (d.curl_error) {
                    html += '<tr><th>cURL Error</th><td class="text-danger">' + escapeHtml(d.curl_error) + '</td></tr>';
                }
                html += '</table>';
                
                // cURL Info
                if (d.curl_info_decoded) {
                    html += '<h5><i class="icon-cogs"></i> cURL Info</h5>';
                    html += '<pre class="bg-light p-3" style="max-height: 300px; overflow: auto;">' + escapeHtml(JSON.stringify(d.curl_info_decoded, null, 2)) + '</pre>';
                }
                
                // Verbose Log
                if (d.verbose_log) {
                    html += '<h5><i class="icon-terminal"></i> cURL Verbose Log</h5>';
                    html += '<pre class="bg-dark text-light p-3" style="max-height: 400px; overflow: auto; font-size: 11px;">' + escapeHtml(d.verbose_log) + '</pre>';
                }
                
                detailContainer.innerHTML = html;
            } else {
                detailContainer.innerHTML = '<div class="alert alert-danger">Error cargando detalle: ' + escapeHtml(response.message || 'Desconocido') + '</div>';
            }
        },
        error: function() {
            detailContainer.innerHTML = '<div class="alert alert-danger">Error de comunicación</div>';
        }
    });
}

function cleanOAuthAttempts() {
    if (!confirm('¿Eliminar intentos OAuth de más de 30 días?')) return;
    
    const btn = document.getElementById('yuju-clean-oauth-attempts');
    btn.disabled = true;
    btn.innerHTML = '<i class="icon-spinner icon-spin"></i> Limpiando...';
    
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            controller: 'AdminYujuConfiguration',
            ajax: 'true',
            action: 'CleanOAuthAttempts',
            token: window.yujuAdminToken,
            days: 30
        },
        dataType: 'json',
        success: function(response) {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-trash"></i> Limpiar > 30 días';
            if (response.success) {
                loadOAuthAttempts();
            } else {
                alert('Error: ' + (response.message || 'Desconocido'));
            }
        },
        error: function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="icon-trash"></i> Limpiar > 30 días';
            alert('Error de comunicación');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('yuju-load-oauth-attempts').addEventListener('click', loadOAuthAttempts);
    document.getElementById('yuju-clean-oauth-attempts').addEventListener('click', cleanOAuthAttempts);
});
</script>
{/literal}


<style>
/* Copy button styles are now in admin.css */

.input-group {
    margin-bottom: 5px;
}

.panel .alert {
    margin-bottom: 20px;
}

.form-group .help-block {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}

/* OAuth Accordion Styles */
#oauth-attempts-accordion .panel {
    margin-bottom: 10px;
    border-radius: 4px;
    overflow: hidden;
}

#oauth-attempts-accordion .panel-heading {
    padding: 0;
    border-radius: 4px 4px 0 0;
}

#oauth-attempts-accordion .panel-heading a {
    display: block;
    padding: 12px 15px;
    color: inherit;
    text-decoration: none;
}

#oauth-attempts-accordion .panel-heading a:hover {
    background-color: #f5f5f5;
    text-decoration: none;
}

#oauth-attempts-accordion .panel-title {
    margin: 0;
    font-size: 14px;
}

#oauth-attempts-accordion .panel-body {
    padding: 15px;
    background-color: #fafafa;
    border-top: 1px solid #e0e0e0;
}

#oauth-attempts-accordion pre {
    margin: 10px 0;
    padding: 15px;
    background-color: #f8f8f8;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.4;
    white-space: pre-wrap;
    word-wrap: break-word;
}

#oauth-attempts-accordion pre.bg-dark {
    background-color: #2d2d2d !important;
    color: #f8f8f2;
    border-color: #444;
}

#oauth-attempts-accordion .table {
    margin-bottom: 15px;
    font-size: 13px;
}

#oauth-attempts-accordion h5 {
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}

#oauth-attempts-accordion h5:first-child {
    margin-top: 0;
}

#oauth-attempts-accordion .text-muted {
    color: #999 !important;
}

#oauth-attempts-accordion .icon-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#oauth-attempts-container .alert-info {
    background-color: #e8f4fd;
    border-color: #bce8f1;
    color: #31708f;
}
</style>