# ESTRUCTURA DEL MÓDULO PRESTASHOP-YUJU

## Estructura Principal del Módulo

```
prestashopYuju/
├── prestashopyuju.php                    # Archivo principal del módulo
├── config.xml                            # Configuración del módulo
├── index.php                             # Archivo de seguridad
├── logo.png                              # Logo del módulo
├── README.md                             # Documentación

├── classes/
│   ├── index.php                         # Archivo de seguridad
│   ├── YujuApiClient.php                 # Cliente principal para API de Yuju
│   ├── YujuOAuth.php                     # Manejo de autenticación OAuth
│   ├── YujuProduct.php                   # Gestión de productos
│   ├── YujuOrder.php                     # Gestión de órdenes
│   ├── YujuCategory.php                  # Gestión de categorías
│   ├── YujuSync.php                      # Sincronización principal
│   ├── YujuMapping.php                   # Mapeos de campos
│   ├── YujuAttribute.php                 # Mapeo de atributos
│   ├── YujuStatus.php                    # Gestión de estados
│   ├── YujuAuditor.php                   # Auditoría del módulo
│   ├── YujuLogger.php                    # Sistema de logs
│   ├── YujuError.php                     # Catálogo de errores
│   ├── YujuEmail.php                     # Notificaciones por email
│   └── YujuWebhook.php                   # Manejo de webhooks

├── controllers/
│   ├── admin/
│   │   ├── index.php                     # Archivo de seguridad
│   │   ├── AdminYujuConfigurationController.php      # Configuración general
│   │   ├── AdminYujuSyncController.php               # Configuración de sincronización
│   │   ├── AdminYujuCategoryMappingController.php    # Mapeo de categorías
│   │   ├── AdminYujuProductMappingController.php     # Mapeo de productos
│   │   ├── AdminYujuAttributeMappingController.php   # Mapeo de atributos
│   │   ├── AdminYujuOrderStatusController.php        # Mapeo de estados de órdenes
│   │   ├── AdminYujuCategoryStatusController.php     # Gestión por categoría
│   │   ├── AdminYujuProductStatusController.php      # Gestión por producto
│   │   ├── AdminYujuDesyncController.php             # Productos desincronizados
│   │   └── AdminYujuAuditorController.php            # Auditor del módulo
│   ├── front/
│   │   ├── index.php                     # Archivo de seguridad
│   │   └── webhook.php                   # Endpoint para webhooks de Yuju
│   └── index.php                         # Archivo de seguridad

├── views/
│   ├── templates/
│   │   ├── admin/
│   │   │   ├── configuration/
│   │   │   │   ├── index.php             # Archivo de seguridad
│   │   │   │   ├── oauth_setup.tpl       # Configuración OAuth
│   │   │   │   └── general_config.tpl    # Configuración general
│   │   │   ├── sync/
│   │   │   │   ├── index.php             # Archivo de seguridad
│   │   │   │   └── sync_config.tpl       # Configuración de sincronización
│   │   │   ├── mapping/
│   │   │   │   ├── index.php             # Archivo de seguridad
│   │   │   │   ├── category_mapping.tpl  # Mapeo de categorías
│   │   │   │   ├── product_mapping.tpl   # Mapeo de productos
│   │   │   │   ├── attribute_mapping.tpl # Mapeo de atributos
│   │   │   │   └── order_status_mapping.tpl # Mapeo de estados
│   │   │   ├── status/
│   │   │   │   ├── index.php             # Archivo de seguridad
│   │   │   │   ├── category_status.tpl   # Estado por categoría
│   │   │   │   ├── product_status.tpl    # Estado por producto
│   │   │   │   └── desync_products.tpl   # Productos desincronizados
│   │   │   ├── auditor/
│   │   │   │   ├── index.php             # Archivo de seguridad
│   │   │   │   ├── auditor_config.tpl    # Configuración del auditor
│   │   │   │   └── auditor_report.tpl    # Reportes del auditor
│   │   │   └── index.php                 # Archivo de seguridad
│   │   └── index.php                     # Archivo de seguridad
│   ├── css/
│   │   ├── index.php                     # Archivo de seguridad
│   │   ├── admin.css                     # Estilos del admin
│   │   └── yuju-module.css               # Estilos específicos del módulo
│   ├── js/
│   │   ├── index.php                     # Archivo de seguridad
│   │   ├── admin.js                      # JavaScript del admin
│   │   ├── mapping.js                    # JavaScript para mapeos
│   │   ├── sync.js                       # JavaScript para sincronización
│   │   └── auditor.js                    # JavaScript para auditor
│   └── index.php                         # Archivo de seguridad

├── sql/
│   ├── index.php                         # Archivo de seguridad
│   ├── install.sql                       # Script de instalación
│   └── uninstall.sql                     # Script de desinstalación

├── translations/
│   ├── index.php                         # Archivo de seguridad
│   ├── es.php                            # Traducciones en español
│   └── en.php                            # Traducciones en inglés

├── logs/
│   ├── index.php                         # Archivo de seguridad
│   ├── sync_logs/                        # Logs de sincronización
│   ├── error_logs/                       # Logs de errores
│   └── audit_reports/                    # Reportes de auditoría

├── config/
│   ├── index.php                         # Archivo de seguridad
│   ├── yuju_endpoints.php                # Endpoints de la API de Yuju
│   ├── default_mappings.php              # Mapeos por defecto
│   └── error_catalog.php                 # Catálogo de errores

└── cron/
    ├── index.php                         # Archivo de seguridad
    ├── sync_products.php                 # Cron para sincronización de productos
    ├── sync_orders.php                   # Cron para sincronización de órdenes
    ├── audit_differences.php             # Cron para auditoría
    └── cleanup_logs.php                  # Cron para limpieza de logs
```

## Descripción de Componentes Principales

### 1. Archivo Principal (prestashopyuju.php)
- Configuración del módulo
- Hooks de PrestaShop
- Instalación/desinstalación
- Configuración de menús del admin

### 2. Clases Principales (/classes/)
- **YujuApiClient.php**: Cliente HTTP para comunicación con API de Yuju
- **YujuOAuth.php**: Manejo completo de autenticación OAuth2
- **YujuProduct.php**: CRUD de productos entre PrestaShop y Yuju
- **YujuOrder.php**: Gestión de órdenes y webhooks
- **YujuSync.php**: Motor de sincronización principal
- **YujuMapping.php**: Sistema de mapeo de campos
- **YujuAuditor.php**: Sistema de auditoría y reportes

### 3. Controladores Admin (/controllers/admin/)
- **AdminYujuConfigurationController.php**: Configuración OAuth y general
- **AdminYujuSyncController.php**: Configuración de sincronización
- **AdminYujuCategoryMappingController.php**: Mapeo de categorías
- **AdminYujuProductMappingController.php**: Mapeo de campos de productos
- **AdminYujuAttributeMappingController.php**: Mapeo de atributos/características
- **AdminYujuOrderStatusController.php**: Mapeo de estados de órdenes
- **AdminYujuCategoryStatusController.php**: Gestión masiva por categoría
- **AdminYujuProductStatusController.php**: Gestión individual de productos
- **AdminYujuDesyncController.php**: Productos desincronizados
- **AdminYujuAuditorController.php**: Configuración y reportes de auditoría

### 4. Vistas (/views/templates/admin/)
- **configuration/**: Plantillas para configuración OAuth y general
- **sync/**: Plantillas para configuración de sincronización
- **mapping/**: Plantillas para todos los tipos de mapeo
- **status/**: Plantillas para gestión de estados y productos
- **auditor/**: Plantillas para auditoría y reportes

### 5. Base de Datos (/sql/)
- Tablas para configuración
- Tablas para mapeos
- Tablas para logs de sincronización
- Tablas para estados de productos
- Tablas para auditoría

### 6. Sistema de Logs (/logs/)
- Logs de sincronización por fecha
- Logs de errores categorizados
- Reportes diarios de auditoría

### 7. Tareas Programadas (/cron/)
- Sincronización automática de productos
- Sincronización de órdenes
- Auditoría programada
- Limpieza de logs antiguos

## Fases de Desarrollo Sugeridas

### FASE 1: Configuración Base
- prestashopyuju.php
- YujuApiClient.php
- YujuOAuth.php
- AdminYujuConfigurationController.php
- Plantillas de configuración
- Scripts SQL básicos

### FASE 2: Mapeos
- YujuMapping.php
- YujuCategory.php
- YujuAttribute.php
- Controladores de mapeo
- Plantillas de mapeo

### FASE 3: Sincronización de Productos
- YujuProduct.php
- YujuSync.php
- AdminYujuSyncController.php
- Plantillas de sincronización

### FASE 4: Gestión de Estados
- YujuStatus.php
- YujuError.php
- Controladores de estado
- Plantillas de gestión

### FASE 5: Órdenes y Webhooks
- YujuOrder.php
- YujuWebhook.php
- webhook.php
- AdminYujuOrderStatusController.php

### FASE 6: Auditoría y Reportes
- YujuAuditor.php
- YujuLogger.php
- YujuEmail.php
- AdminYujuAuditorController.php
- Sistema de cron

### FASE 7: Productos Desincronizados
- AdminYujuDesyncController.php
- Plantillas de desincronización

### FASE 8: Optimización y Testing
- Optimización de rendimiento
- Testing completo
- Documentación final

Esta estructura modular permite desarrollo incremental y mantenimiento eficiente del código.