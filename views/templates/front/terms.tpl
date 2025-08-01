{extends file='page.tpl'}

{block name='page_title'}
    <h1>Términos y Condiciones - Integración Yuju</h1>
{/block}

{block name='page_content'}
<div class="terms-content">
    <div class="alert alert-info">
        <strong>Última actualización:</strong> {$current_date|escape:'html':'UTF-8'}
    </div>

    <h2>1. Información General</h2>
    <p>Los presentes términos y condiciones regulan el uso de la integración entre <strong>{$shop_name|escape:'html':'UTF-8'}</strong> y la plataforma Yuju para la sincronización de productos, categorías, pedidos y demás funcionalidades del comercio electrónico.</p>

    <h2>2. Aceptación de los Términos</h2>
    <p>Al utilizar esta integración, usted acepta estar sujeto a estos términos y condiciones. Si no está de acuerdo con alguna parte de estos términos, no debe utilizar la integración.</p>

    <h2>3. Descripción del Servicio</h2>
    <p>La integración Yuju permite:</p>
    <ul>
        <li>Sincronización bidireccional de productos entre {$shop_name|escape:'html':'UTF-8'} y Yuju</li>
        <li>Gestión automatizada de inventario y precios</li>
        <li>Sincronización de categorías y atributos de productos</li>
        <li>Procesamiento de pedidos y actualización de estados</li>
        <li>Webhooks para actualizaciones en tiempo real</li>
    </ul>

    <h2>4. Responsabilidades del Usuario</h2>
    <p>El usuario se compromete a:</p>
    <ul>
        <li>Proporcionar información precisa y actualizada</li>
        <li>Mantener la seguridad de sus credenciales de acceso</li>
        <li>Utilizar la integración de acuerdo con las políticas de Yuju</li>
        <li>Notificar cualquier uso no autorizado de su cuenta</li>
    </ul>

    <h2>5. Privacidad y Protección de Datos</h2>
    <p>La integración procesa datos de productos, pedidos y clientes de acuerdo con:</p>
    <ul>
        <li>Las políticas de privacidad de {$shop_name|escape:'html':'UTF-8'}</li>
        <li>Las políticas de privacidad de Yuju</li>
        <li>La normativa aplicable de protección de datos (RGPD, LOPD, etc.)</li>
    </ul>

    <h2>6. Limitación de Responsabilidad</h2>
    <p>La integración se proporciona "tal como está" sin garantías de ningún tipo. {$shop_name|escape:'html':'UTF-8'} no será responsable de:</p>
    <ul>
        <li>Pérdidas de datos durante la sincronización</li>
        <li>Interrupciones del servicio de terceros</li>
        <li>Errores en la sincronización de información</li>
        <li>Daños indirectos o consecuenciales</li>
    </ul>

    <h2>7. Modificaciones</h2>
    <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Las modificaciones entrarán en vigor inmediatamente después de su publicación en esta página.</p>

    <h2>8. Terminación</h2>
    <p>Cualquiera de las partes puede terminar el uso de esta integración en cualquier momento. Al terminar, se detendrán todas las sincronizaciones y se podrán eliminar los datos almacenados.</p>

    <h2>9. Ley Aplicable</h2>
    <p>Estos términos se regirán por las leyes del país donde esté registrado {$shop_name|escape:'html':'UTF-8'}.</p>

    <h2>10. Contacto</h2>
    <p>Para cualquier consulta sobre estos términos y condiciones, puede contactarnos en:</p>
    <ul>
        <li><strong>Email:</strong> {$shop_email|escape:'html':'UTF-8'}</li>
        <li><strong>Sitio web:</strong> <a href="{$shop_url|escape:'html':'UTF-8'}" target="_blank">{$shop_url|escape:'html':'UTF-8'}</a></li>
    </ul>

    <div class="alert alert-secondary mt-4">
        <small>
            <strong>Módulo Yuju v{$module_version|escape:'html':'UTF-8'}</strong><br>
            Estos términos y condiciones han sido generados automáticamente para facilitar el registro de aplicaciones en Yuju.
        </small>
    </div>
</div>

<style>
.terms-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

.terms-content h2 {
    color: #333;
    margin-top: 30px;
    margin-bottom: 15px;
    border-bottom: 2px solid #007cba;
    padding-bottom: 5px;
}

.terms-content ul {
    margin: 15px 0;
    padding-left: 30px;
}

.terms-content li {
    margin-bottom: 8px;
}

.terms-content p {
    margin-bottom: 15px;
    text-align: justify;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-info {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.alert-secondary {
    color: #6c757d;
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.mt-4 {
    margin-top: 1.5rem;
}
</style>
{/block}