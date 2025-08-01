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
        {else}
            <div class="icon error">✗</div>
            <div class="message error">
                <strong>Error de autorización</strong><br>
                {$error|escape:'html':'UTF-8'}
            </div>
        {/if}
        
        <button class="close-btn" onclick="window.close()">
            Cerrar ventana
        </button>
    </div>

    <script>
        // Auto-cerrar la ventana después de 3 segundos si fue exitoso
        {if $success}
            setTimeout(function() {
                window.close();
            }, 3000);
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