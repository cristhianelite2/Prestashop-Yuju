<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Autorización Yuju</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .close-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .close-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        {if $success}
            <div class="icon success">✓</div>
            <div class="message success">
                <strong>¡Autorización exitosa!</strong><br>
                La conexión con Yuju se ha establecido correctamente.
            </div>
            {if isset($admin_redirect_url) && $admin_redirect_url}
                <div style="margin-bottom: 15px; font-size: 13px; color: #666;">
                    Redirigiendo al módulo de configuración...
                </div>
            {/if}
        {else}
            <div class="icon error">✗</div>
            <div class="message error">
                <strong>Error de autorización</strong><br>
                {$error|escape:'html':'UTF-8'}
            </div>
            
            {if isset($show_debug) && $show_debug}
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; text-align: left; font-size: 12px;">
                    <strong>Información de debugging:</strong><br>
                    <strong>URL:</strong> {$debug_info.request_uri|escape:'html':'UTF-8'}<br>
                    <strong>Query String:</strong> {$debug_info.query_string|escape:'html':'UTF-8'}<br>
                    <strong>Parámetro code:</strong> {if $debug_info.code_param}{$debug_info.code_param|escape:'html':'UTF-8'}{else}NO RECIBIDO{/if}<br>
                    <strong>Parámetro state:</strong> {if $debug_info.state_param}{$debug_info.state_param|escape:'html':'UTF-8'}{else}NO RECIBIDO{/if}<br>
                    <strong>Parámetro error:</strong> {if $debug_info.error_param}{$debug_info.error_param|escape:'html':'UTF-8'}{else}No{/if}<br>
                    <strong>Todos los parámetros GET:</strong><br>
                    {foreach from=$debug_info.get_params key=key item=value}
                        &nbsp;&nbsp;{$key|escape:'html':'UTF-8'} = {$value|escape:'html':'UTF-8'}<br>
                    {/foreach}

                    {if isset($debug_info.oauth_trace)}
                        <hr style="margin: 10px 0;">
                        <strong>Diagnóstico OAuth:</strong><br>
                        {if isset($debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime)}
                            <strong>Estado runtime:</strong><br>
                            &nbsp;&nbsp;is_configured = {$debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime.is_configured|escape:'html':'UTF-8'}<br>
                            &nbsp;&nbsp;client_id_current = {$debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime.client_id_current|default:'(vacío)'|escape:'html':'UTF-8'}<br>
                            &nbsp;&nbsp;client_secret_current = {$debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime.client_secret_current|default:'(vacío)'|escape:'html':'UTF-8'}<br>
                            &nbsp;&nbsp;client_id_length = {$debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime.client_id_length|escape:'html':'UTF-8'}<br>
                            &nbsp;&nbsp;client_secret_length = {$debug_info.oauth_trace.oauth_debug.credential_diagnostics.runtime.client_secret_length|escape:'html':'UTF-8'}<br>
                        {/if}

                        {if isset($debug_info.oauth_trace.oauth_debug.credential_diagnostics.sources)}
                            <strong>Fuentes revisadas:</strong><br>
                            {foreach from=$debug_info.oauth_trace.oauth_debug.credential_diagnostics.sources key=source_name item=source_values}
                                &nbsp;&nbsp;<strong>{$source_name|escape:'html':'UTF-8'}</strong><br>
                                {foreach from=$source_values key=field_name item=field_info}
                                    {if is_array($field_info)}
                                        &nbsp;&nbsp;&nbsp;&nbsp;{$field_name|escape:'html':'UTF-8'} => exists={$field_info.exists|escape:'html':'UTF-8'}, len={$field_info.length|escape:'html':'UTF-8'}, masked={$field_info.masked|default:'(vacío)'|escape:'html':'UTF-8'}<br>
                                    {else}
                                        &nbsp;&nbsp;&nbsp;&nbsp;{$field_name|escape:'html':'UTF-8'} => {$field_info|escape:'html':'UTF-8'}<br>
                                    {/if}
                                {/foreach}
                            {/foreach}
                        {/if}
                    {/if}
                </div>
            {/if}
        {/if}
        
        {if $success && isset($admin_redirect_url) && $admin_redirect_url}
            <button class="close-btn" onclick="window.location.href = '{$admin_redirect_url|escape:'javascript':'UTF-8'}'">
                Ir al módulo
            </button>
        {else}
            <button class="close-btn" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }">
                Atrás
            </button>
        {/if}
    </div>

    <script>
        // Redirigir al módulo de configuración después de 2 segundos si fue exitoso
        {if $success}
            setTimeout(function() {
                {if isset($admin_redirect_url) && $admin_redirect_url}
                    window.location.href = '{$admin_redirect_url|escape:'javascript':'UTF-8'}';
                {else}
                    window.close();
                {/if}
            }, 2000);
        {/if}
        
        // Notificar a la ventana padre si existe
        if (window.opener) {
            window.opener.postMessage({
                type: 'yuju_oauth_result',
                success: {if $success}true{else}false{/if},
                message: '{if $success}Autorización exitosa{else}{$error|escape:"javascript":"UTF-8"}{/if}'
            }, '*');
        }
    </script>
</body>
</html>