*** REQUERIMIENTOS ***

Se va a implementar un módulo de prestashop 8 para integrar Yuju con Prestashop, apoyate de las documentaciones:

https://devdocs.prestashop-project.org/9/modules/introduction/
https://api-docs.yuju.io/docs/introduccion

Debes poner mucha atención a los requerimientos, características y funcionalidades que se detallan a continuación.

## ESTADO DE IMPLEMENTACIÓN

### ✅ COMPLETADO
- **Mapeo de categorías**: Interfaz completa con modal, tabla interactiva, operaciones AJAX
- **Mapeo de productos**: Interfaz completa con campos personalizables, reglas de transformación
- **Mapeo de atributos**: Interfaz completa con soporte para grupos de atributos y valores
- **Estructura base del módulo**: Controladores, clases, templates y base de datos
- **Sistema de logging**: Implementado en todas las operaciones
- **Validaciones**: Prevención de mapeos duplicados y validación de datos
- **Sincronización de datos**: Métodos para refrescar categorías y atributos desde Yuju

### ⏳ PENDIENTE
- **Dashboard**: Estadísticas y métricas de sincronización
- **Configuración**: Panel de configuración completo
- **Mapeo de atributos de valores**: Gestión detallada de valores de atributos
- **Sincronización automática**: Implementación del sistema de cron
- **Webhooks**: Sistema de notificaciones en tiempo real
- **OAuth**: Autenticación con Yuju

---

# Dashboard

- ⏳ Se debe de mostrar la cantidad de errores y advertencias que ha tenido la última semana
- ⏳ Se debe de mostrar la cantidad de productos, categorías y atributos que se han sincronizado la última semana (Solo mostrar los últimos 10)
- ⏳ Se debe de mostrar la última fecha de sincronización

# Configuración

- ⏳ Se deben de mostrar los siguientes campos:
    - ⏳ Seleccionar la tienda de conexión, hay que considerar que para cada tienda de prestashop puede haber una conexión por lo cual se debe de considerar
    - ⏳ Idioma de la tienda
    - ⏳ API Key
    - ⏳ API Secret
    - ⏳ URL de Webhook
    - ⏳ Dominios Permitidos
    - ⏳ Probar conexión
    - ⏳ Habilitar sincronización (Sí por default)
    - ⏳ Habilitar sincronización de precios (dependen de "Habilitar sincronización")
    - ⏳ Habilitar sincronización de stock (dependen de "Habilitar sincronización")
    - ⏳ Habilitar sincronización de imágenes (dependen de "Habilitar sincronización")
    - ⏳ Habilitar sincronización de atributos (dependen de "Habilitar sincronización")
    - ⏳ Habilitar sincronización de características (dependen de "Habilitar sincronización")
    - ⏳ Habilitar sincronización de ordenes (dependen de "Habilitar sincronización")
    - ⏳ Forzar Actualización
        - Esta funcionalidad forzará el envío masivo de información del producto ignorando lo ya enviado, por lo cual se recomienda un uso cauteloso.
    - ⏳ Limpieza de HTML en Descripción.
    - ⏳ Frecuencia de Sincronización (segundos) (3600 por default)
    - ⏳ Tamaño de Lote (100 por default)
    - ⏳ Habilitar logs (Si por default)
    - ⏳ Nivel de Registro (Info, errores y advertencias por default)
    - ⏳ Retención de Registros (días) (30 por default)

# Mapeo de categorías ✅ COMPLETADO
Usa !(/requirements/mapeo_de_categoria.jpg) como referencia
Usa !(/requirements/mapeo_de_categoria2.jpg) como referencia

- ✅ Se podrán crear mapeos en un modal donde deberá poder seleccionar dentro del árbol de categorías, abrir los padres para poder seleccionar un hijo y mapearlo con el listado de categorías que cuenta Yuju
- ✅ Cada mapeo se puede bloquear / desbloquear su sincronización
- ✅ Se debe indicar si el padre está sincronizado dentro del árbol de categorías, si está sincronizado, no se deberá poder seleccionar los hijos.

**Funcionalidades implementadas:**
- Interfaz con tabla interactiva de mapeos existentes
- Modal para crear/editar mapeos con árbol de categorías
- Operaciones AJAX para guardar, editar, eliminar y sincronizar
- Validación de mapeos duplicados
- Sistema de logging integrado
- Botón para refrescar categorías desde Yuju

# Mapeo de campos ✅ COMPLETADO

Usa !(/requirements/mapeo_de_productos.jpg) como referencia
Usa !(/requirements/mapeo_de_productos2.jpg) como referencia

- ✅ La intención es mapear TODOS los campos disponibles en prestashop contra TODOS los campos disponibles en Yuju, para ello es importante obtener los de yuju y prestashop, para ello se debe de hacer una solicitud a yuju.
- ✅ Se debe colocar una columna para colocar valores "Por default" para así poder tener campos de prestashop vacíos.
- ✅ Se debe mostrar el listado de campos por default que maneja prestashop y hacer un empalme con los campos de yuju, los campos de prestashop, coloca:
    - ✅ Nombre -> Nombre (Yuju) *Obligatorio
    - ✅ Referencia -> SKU (Yuju) *Obligatorio
    - ✅ Referencia -> SKU Simple (Yuju) *Obligatorio
    - ✅ Descripción -> Descripción (Yuju) *Obligatorio
    - ✅ Imagenes -> Imágenes (Yuju) *Obligatorio
    - ✅ Precio Final -> Precio (Yuju) *Obligatorio
    - ✅ Cantidad -> Stock (Yuju) *Obligatorio
    - ✅ Fabricante -> Marca (Yuju) *Obligatorio
    - ✅ Condición -> Condición (Yuju) *Obligatorio
    - ✅ Método de envío (Yuju) -> Default (El marketplace lo calcula) *Obligatorio
    - ✅ Precio de envío (Yuju) -> Default 0 *Obligatorio
    - ✅ Unidad de dimensión -> Unidad de dimensión (Yuju) -> Default cm *Obligatorio
    - ✅ Alto del paquete -> Altura (Yuju) -> Default 0 *Obligatorio
    - ✅ Ancho del paquete -> Ancho (Yuju) -> Default 0 *Obligatorio
    - ✅ Profundidad del paquete -> Profundidad (Yuju) -> Default 0 *Obligatorio
    - ✅ Unidad de peso -> Unidad de peso (Yuju) -> Default kg *Obligatorio
    - ✅ Peso del paquete -> Peso (Yuju) -> Default 0 *Obligatorio
    - ✅ Plantilla de MercadoLibre (Yuju) -> Default (No usar plantilla) *Obligatorio

**Funcionalidades implementadas:**
- Interfaz completa para mapeo de campos de productos
- Modal con dropdowns para seleccionar campos de PrestaShop y Yuju
- Soporte para reglas de transformación personalizadas (incluyendo código PHP)
- Campos para valores por defecto
- Validación de campos obligatorios y opcionales
- Operaciones AJAX para todas las acciones CRUD
- Sistema de activación/desactivación de mapeos

# Mapeo de atributos ✅ COMPLETADO

- ✅ Interfaz para mapear atributos de PrestaShop con atributos de Yuju
- ✅ Soporte para diferentes tipos de atributos (select, text, number, boolean)
- ✅ Configuración de dirección de sincronización (PS→Yuju, Yuju→PS, Bidireccional)
- ✅ Auto-creación de valores de atributos durante la sincronización
- ✅ Gestión de mapeos de valores de atributos
- ⏳ Interfaz detallada para mapeo de valores individuales de atributos

**Funcionalidades implementadas:**
- Tabla interactiva con mapeos de atributos existentes
- Modal para crear/editar mapeos de atributos
- Sincronización de atributos desde Yuju con cache local
- Operaciones AJAX completas
- Validación y logging integrado
- Contador de mapeos de valores por atributo







