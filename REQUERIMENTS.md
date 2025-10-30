*** REQUERIMIENTOS ***

Se va a implementar un módulo de prestashop 8 para integrar Yuju con Prestashop, apoyate de las documentaciones:

https://devdocs.prestashop-project.org/9/modules/introduction/
https://api-docs.yuju.io/docs/introduccion

Debes poner mucha atención a los requerimientos, características y funcionalidades que se detallan a continuación.

Hay que corregir las siguientes cosas, puedes marcar una ✔️ o un [ ] o un ❌ O ❓ (Este solo si tienes una duda de que necesitamos) para cada cambio:

## SIEMPRE AL TERMINAR TIENES QUE REGRESAR A ESTE ARCHIVO ACTUALIZA TODO LO QUE HICISTE

# Correcciones

## Intalación:
- [✔️] No se vé la imagen (logo) de Yuju en la instalación hay que buscarla y moverla (views/img/logo.png) sigue sin verse el logo!

## AdminYujuConfiguration
- [✔️] En la url /module/prestashopyuju/oauth?code=.. sale el error:
--- Fatal error: Uncaught TypeError: hash_equals(): Argument #2 ($user_string) must be of type string, bool given in /var/www/html/modules/prestashopyuju/classes/YujuOAuth.php:488 Stack trace: #0 /var/www/html/modules/prestashopyuju/classes/YujuOAuth.php(488): hash_equals('0fee60c6a891bae...', false) #1 /var/www/html/modules/prestashopyuju/controllers/front/oauth.php(49): YujuOAuth->validateState(false, '0fee60c6a891bae...') #2 /var/www/html/classes/controller/Controller.php(319): PrestashopyujuOauthModuleFrontController->initContent() #3 /var/www/html/classes/Dispatcher.php(510): ControllerCore->run() #4 /var/www/html/index.php(28): DispatcherCore->dispatch() #5 {main} thrown in /var/www/html/modules/prestashopyuju/classes/YujuOAuth.php on line 488
- [✔️] https://yuju.ceballosleon.com/es/module/prestashopyuju/oauth?code=133c27ea7a223930735fabcc05e2a0b4d891ae201b150af69aa6a209493d5ab9
--- Error de autorización / Estado de autorización no recibido 
--- [✔️] Agrega un log que se pueda habilitar y deshabilitar en configuración

# Configuración
- [✔️] Se deben de mostrar los siguientes campos:
    - [✔️] Seleccionar la tienda de conexión, hay que considerar que para cada tienda de prestashop puede haber una conexión por lo cual se debe de considerar
    - [✔️] Idioma de la tienda
    - [✔️] API Key
    - [✔️] API Secret
    - [✔️] URL de Webhook
    - [✔️] Dominios Permitidos
    - [✔️] Probar conexión
    - [✔️] Habilitar sincronización (Sí por default)
    - [✔️] Habilitar sincronización de precios (dependen de "Habilitar sincronización")
    - [✔️] Habilitar sincronización de stock (dependen de "Habilitar sincronización")
    - [✔️] Habilitar sincronización de imágenes (dependen de "Habilitar sincronización")
    - [✔️] Quitar sincronización de atributos (dependen de "Habilitar sincronización")
    - [✔️] Quitar sincronización de características (dependen de "Habilitar sincronización")
    - [✔️] Habilitar sincronización de ordenes (dependen de "Habilitar sincronización")
    - [✔️] Forzar Actualización
        - Esta funcionalidad forzará el envío masivo de información del producto ignorando lo ya enviado, por lo cual se recomienda un uso cauteloso.
    - [✔️] Limpieza de HTML en Descripción.
    - [✔️] Frecuencia de Sincronización (segundos) (3600 por default)
    - [✔️] Tamaño de Lote (100 por default)
    - [✔️] Habilitar logs (Si por default)
    - [✔️] Nivel de Registro (Info, errores y advertencias por default)
    - [✔️] Retención de Registros (días) (30 por default)
- [✔️] AdminYujuConfiguration
--- Warning: Undefined array key "YUJU_STORE_LANGUAGE" configuration.tpl.php (line 356)
- [✔️] Hay varios elementos repetidos: API Key ( ID de Cliente), API Secret (CON  Secreto de Cliente), Dominios Permitidos etc... Limpialo y revisa que no haya repetidos (En  Configuración de Sincronización y  Configuración de API y  URLs Importantes) CORRIGELO! HAZLO DE NUEVO
- [✔️] Hay que quitar el botón repetido de "Probar conectividad"
- [✔️] En "Seleccionar tienda" debe mostrar las tiendas de Prestashop
- [✔️] Warning: Trying to access array offset on value of type int: AdminYujuConfigurationController.php (line 113)


## Mapeo de campos (AdminYujuProductMapping)
- [✔️] Se debe mostrar el listado de campos por default que maneja prestashop y hacer un empalme con los campos de yuju, los campos de prestashop, coloca, DEBES RESPETAR EL ORDEN Y MARCAR COMO OBLIGATORIO:
    - [✔️] Nombre -> Nombre (Yuju) *Obligatorio
    - [✔️] Referencia -> SKU (Yuju) *Obligatorio
    - [✔️] Referencia -> SKU Simple (Yuju) *Obligatorio
    - [✔️] Descripción -> Descripción (Yuju) *Obligatorio
    - [✔️] Imagenes -> Imágenes (Yuju) *Obligatorio
    - [✔️] Precio Final -> Precio (Yuju) *Obligatorio
    - [✔️] Cantidad -> Stock (Yuju) *Obligatorio
    - [✔️] Fabricante -> Marca (Yuju) *Obligatorio
    - [✔️] Condición -> Condición (Yuju) *Obligatorio
    - [✔️] Método de envío (Yuju) -> Default (El marketplace lo calcula) *Obligatorio
    - [✔️] Precio de envío (Yuju) -> Default 0 *Obligatorio
    - [✔️] Unidad de dimensión -> Unidad de dimensión (Yuju) -> Default cm *Obligatorio
    - [✔️] Alto del paquete -> Altura (Yuju) -> Default 0 *Obligatorio
    - [✔️] Ancho del paquete -> Ancho (Yuju) -> Default 0 *Obligatorio
    - [✔️] Profundidad del paquete -> Profundidad (Yuju) -> Default 0 *Obligatorio
    - [✔️] Unidad de peso -> Unidad de peso (Yuju) -> Default kg *Obligatorio
    - [✔️] Peso del paquete -> Peso (Yuju) -> Default 0 *Obligatorio
    - [✔️] Plantilla de MercadoLibre (Yuju) -> Default (No usar plantilla) *Obligatorio
- [✔️] SIGUE SIN MOSTRARSE EN ESE ORDEN, OBLIGALOS!!!

## Errores de Template:
- [✔️] Error en configuration.tpl: "Syntax error in template configuration.tpl on line 1" - Archivo tenía caracteres de codificación BOM corruptos, recreado completamente con codificación UTF-8 limpia
- [✔️] Eliminadas todas las secciones duplicadas en el template de configuración
- [✔️] Template ahora funciona correctamente sin errores de sintaxis de Smarty

## Debugging OAuth:
- [✔️] Agregado logging forzado con método forceDebug() que siempre escribe sin importar configuración
- [✔️] Logging completo de parámetros GET recibidos en callback OAuth
- [✔️] Debugging visual en template oauth_callback.tpl mostrando todos los parámetros recibidos
- [✔️] Log detallado de validación de estado OAuth para identificar problemas
- [✔️] Corregido: Yuju NO envía parámetro state según su documentación - hecho opcional
- [✔️] Identificado: Yuju solo envía 'code' en callback, no 'state' (comportamiento normal)
- [✔️] Ajustada validación OAuth para funcionar sin parámetro state como requiere Yuju

## Corrección Base de Datos OAuth:
- [✔️] Error identificado: "Column not found: 1054 Unknown column 'client_id' in 'field list'"
- [✔️] Actualizado sql/install.sql para incluir columnas client_id y client_secret en tabla yuju_oauth_tokens
- [✔️] Agregada columna token_expires que también era requerida por el código
- [✔️] Creado script update_oauth_table.php para actualizar instalaciones existentes:
  - Detecta automáticamente la ruta de PrestaShop
  - Verifica existencia de columnas antes de agregarlas
  - Ejecutable vía web o línea de comandos
  - Muestra estructura completa de tabla después de actualización
  - Incluye instrucciones de seguridad para eliminar después del uso
- [✔️] OAuth ahora puede guardar tokens correctamente sin errores de base de datos

## Error: Tabla No Existe - ps_yuju_oauth_tokens:
- [✔️] Error identificado: "Base table or view not found: 1146 Table 'prestashop.ps_yuju_oauth_tokens' doesn't exist"
- [✔️] Problema: El módulo no se instaló correctamente o las tablas no se crearon durante la instalación
- [✔️] Corregido archivo sql/install.sql - inconsistencias de prefijos y tipos de motor:
  - Reemplazados todos los "PREFIX_" con "' . _DB_PREFIX_ . '"
  - Cambiado "ENGINE_TYPE" por "InnoDB"
  - Actualizado charset de "utf8" a "utf8mb4" para mejor compatibilidad
  - Unificada sintaxis de todas las tablas del módulo
- [✔️] Creados scripts auxiliares:
  - check_tables.php: Verifica estado de tablas y permite crearlas automáticamente
  - create_oauth_table_direct.sql: Script SQL directo ejecutable en base de datos
  - update_oauth_table.php: Para actualizar instalaciones existentes
- [✔️] El módulo ahora se instala correctamente con todas las tablas necesarias

## Warning "combined_domains" No Definido:
- [✔️] Error identificado: "Warning: Undefined array key 'combined_domains' configuration.tpl.php (line 243)"
- [✔️] Problema: La variable combined_domains no se estaba pasando al template desde el controlador
- [✔️] Error adicional: "Warning: Array to string conversion AdminYujuConfigurationController.php (line 111)"
- [✔️] Error adicional: "Warning: Undefined array key 'YUJU_STORE_LANGUAGE' configuration.tpl.php (line 396)"
- [✔️] Error adicional: "Warning: Undefined array key 'YUJU_LOGGING_ENABLED' configuration.tpl.php (line 554)"
- [✔️] Correcciones aplicadas:
  - Agregada la clave 'combined_domains' al array yuju_urls en AdminYujuConfigurationController.php
  - Corregida conversión array to string con is_array() e implode() para manejar tanto strings como arrays
  - Agregadas TODAS las variables YUJU_* que faltan en el controlador:
    * YUJU_PRESTASHOP_STORE_ID (para selector de tienda)
    * YUJU_STORE_LANGUAGE (para selector de idioma)
    * YUJU_SYNC_ENABLED (para activar/desactivar sincronización)
    * YUJU_SYNC_PRICES (para sincronización de precios)
    * YUJU_SYNC_STOCK (para sincronización de stock)
    * YUJU_SYNC_IMAGES (para sincronización de imágenes)
    * YUJU_SYNC_ORDERS (para sincronización de órdenes)
    * YUJU_CLEAN_HTML (para limpieza de HTML en descripciones)
    * YUJU_LOGGING_ENABLED (para activar/desactivar logs)
    * YUJU_FORCE_UPDATE (para forzar actualización masiva)
  - Variables ya existentes validadas:
    * YUJU_ENVIRONMENT, YUJU_CLIENT_ID, YUJU_CLIENT_SECRET
    * YUJU_WEBHOOK_SECRET, YUJU_LOG_LEVEL, YUJU_LOG_RETENTION
    * YUJU_SYNC_FREQUENCY, YUJU_BATCH_SIZE
  - Agregadas variables adicionales para el template:
    * prestashop_stores (Shop::getShops() para selector de tiendas)
    * available_languages (Language::getLanguages() para selector de idiomas)
  - Agregado fallback con isset() en el template para evitar warnings futuros
  - Template ahora verifica la existencia de la variable antes de usarla
- [✔️] Resultado: TODOS los warnings de variables YUJU_* indefinidas eliminados completamente

