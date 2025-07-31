# Instrucciones de Instalación - Módulo Yuju PrestaShop

## Problemas Resueltos

✅ **Errores de rutas corregidos**: Se han corregido todas las rutas incorrectas en los archivos de clases que causaban errores de "Failed to open stream".

✅ **Errores de sintaxis corregidos**: Se ha corregido la indentación incorrecta en el array `ps_versions_compliancy`.

✅ **Componentes temporalmente deshabilitados**: Para permitir la instalación, se han comentado temporalmente las inicializaciones de componentes que dependen de PrestaShop.

## Estado Actual

El módulo ahora debería instalarse correctamente en PrestaShop. Los componentes principales están comentados temporalmente para evitar errores durante la instalación.

## Pasos Post-Instalación

### 1. Instalar el Módulo
- Sube el módulo a PrestaShop
- Instálalo desde el panel de administración

### 2. Habilitar Componentes (Después de la Instalación)

Una vez instalado exitosamente, descomenta las siguientes líneas en `prestashopyuju.php` (líneas 66-70):

```php
// Initialize components
$this->logger = new YujuLogger();
$this->api_client = new YujuApiClient();
$this->oauth = new YujuOAuth();
$this->sync_manager = new YujuSyncManager();
$this->webhook_manager = new YujuWebhookManager();
```

### 3. Verificar Funcionamiento
- Accede a la configuración del módulo
- Verifica que todos los componentes funcionen correctamente

## Archivos Modificados

- `prestashopyuju.php`: Corregida indentación y componentes comentados
- `classes/YujuSyncManager.php`: Corregidas rutas de includes
- `classes/YujuCategoryManager.php`: Corregidas rutas de includes
- `classes/YujuWebhookManager.php`: Corregidas rutas de includes
- `classes/YujuOrderManager.php`: Corregidas rutas de includes
- `classes/YujuProductManager.php`: Corregidas rutas de includes
- `classes/YujuAttributeManager.php`: Corregidas rutas de includes

## Notas Importantes

- Los componentes están temporalmente deshabilitados SOLO para permitir la instalación
- Una vez instalado, debes habilitarlos nuevamente para que el módulo funcione completamente
- Todas las rutas de archivos han sido corregidas para usar rutas relativas apropiadas