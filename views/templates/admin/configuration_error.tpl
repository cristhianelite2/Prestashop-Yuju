{*
 * Yuju configuration error fallback.
 * Shows a useful message instead of a blank legacy admin page.
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-warning-sign"></i>
        Configuración de Integración Yuju
    </div>
    <div class="panel-body">
        <div class="alert alert-danger" role="alert">
            <strong>No se pudo cargar la configuración.</strong>
            <p>{$yuju_page_error|default:'Ocurrió un error al preparar la pantalla de configuración.'|escape:'html':'UTF-8'}</p>
            {if !empty($yuju_page_error_detail)}
                <pre style="white-space:pre-wrap;overflow-wrap:anywhere;">{$yuju_page_error_detail|escape:'html':'UTF-8'}</pre>
            {/if}
        </div>
        <p>Consulta Parámetros avanzados → Registros en PrestaShop y busca una entrada que comience con <code>[Yuju] Error en AdminYujuConfigurationController::initContent</code>.</p>
        <p>Después de corregir la causa, vuelve a cargar esta página.</p>
    </div>
</div>
