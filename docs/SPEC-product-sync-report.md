# Spec: Reconciliar catálogo PrestaShop → Yuju usando reporte general

## Objetivo

Comparar el catálogo existente de PrestaShop con un reporte general de Yuju, completar IDs Yuju en `PREFIX_yuju_product_status` cuando haya una coincidencia inequívoca, indicar qué está sincronizado y enviar a Yuju únicamente las diferencias de productos ya vinculados. **La fuente de verdad siempre es PrestaShop; nunca se importan valores de Yuju a PrestaShop ni se crean en Yuju productos de PrestaShop que no tengan una coincidencia/ID.**

## Stack

- Módulo PHP para PrestaShop 1.7–9; mantener compatibilidad con PHP 7.x.
- Yuju: `POST /products-gral-report`, `GET /products-gral-report/{id_task}` y el URL de descarga devuelto al completar.
- El reporte es JSONL, asíncrono y tiene un máximo documentado de dos solicitudes por día UTC.
- Referencias oficiales: [reporte general](https://api-docs.yuju.io/docs/obtener-informacion-general), [crear variaciones](https://api-docs.yuju.io/docs/agregar-una-o-multiples-variaciones), [actualizar variación](https://api-docs.yuju.io/docs/actualizar-una-variaci%C3%B3n).
- Referencias oficiales: [reporte general](https://api-docs.yuju.io/docs/obtener-informacion-general), [crear variaciones](https://api-docs.yuju.io/docs/agregar-una-o-multiples-variaciones), [actualizar variación](https://api-docs.yuju.io/docs/actualizar-una-variaci%C3%B3n).

## Flujo y contrato

1. «Iniciar sincronización» inicia un reporte general solo cuando no hay uno activo o reutilizable. El `POST` no se reintenta automáticamente; se persisten `id_task`, instante/estado y el límite diario antes de permitir otro intento.
2. La interfaz consulta el estado con peticiones cortas y acotadas; no se mantiene abierta una sola petición web mientras Yuju procesa.
3. Al completar, el servidor valida y descarga el JSONL una sola vez a almacenamiento temporal privado. El URL firmado nunca se presenta al navegador ni se registra en logs.
4. Se comparan los productos padre por ID Yuju existente; si no hay ID, se intenta enlazar por SKU únicamente cuando la coincidencia es única. Las variaciones/combinaciones se comparan por SKU de variante.
5. `yuju_product_status` guarda ID Yuju y estado por producto PrestaShop: `synced` si todos los campos outbound comparables coinciden, `pending` si hay diferencias o no existe pareja, `error` si falla reporte, lectura o sincronización. El escaneo nunca borra un ID guardado ante una ausencia ambigua.
6. Se muestran totales, lista de productos con diferencias/sin pareja y los campos que difieren. SKU duplicados o filas sin IDs quedan para resolución manual; no se enlazan por adivinación.
7. «Sincronizar» manda de PrestaShop a Yuju los campos configurados para salida (`ps_to_yuju`/`bidirectional`) de los productos ya vinculados que difieren, incluidas sus variaciones. No crea productos nuevos ni modifica datos de PrestaShop. Si Yuju confirma la actualización, el estado pasa a `synced`.

## Comparación de valores

- Reutilizar los mapeos activos y transformaciones existentes para campos de salida.
- Comparar las representaciones normalizadas, no diferencias cosméticas (espacios, formato numérico, orden de arrays cuando no sea semántico).
- Solo declarar `synced` cuando todos los campos aplicables tengan representación comparable en el reporte. Si falta un campo requerido para comprobar, indicar comparación incompleta y no fingir sincronía.
- Variaciones: comparar SKU, atributos soportados, precio y stock. Yuju documenta `POST /products/{id_product}/variations` para crear y `PUT /products/{id_product}/variations/{id_variation}` para actualizar; si el reporte no aporta ID de una variación existente, no se repite el `POST` (evitar duplicados), se deja pendiente y se informa.

## Persistencia, cuotas y seguridad

- Respetar el límite de dos reportes por día UTC; reutilizar tareas en curso y reportes completados vigentes. Sin reintentos automáticos del `POST` para evitar consumir cuota duplicada ante timeouts.
- Guardar el estado pequeño de tarea en `Configuration`; guardar el JSONL fuera de la web pública, con permisos privados, TTL y limpieza segura. No guardar el URL firmado en la base de datos/logs más tiempo del necesario.
- La respuesta del reporte y su URL son entrada no confiable. Descargar solo por HTTPS, con host/redirects validados, límite de tamaño y tiempo, sin acceder a IPs privadas/locales. No procesar ni importar filas hasta validar estructura e IDs.
- No cambiar endpoints ni configuración de Yuju, no generar firmas AWS y no enviar datos de Yuju a PrestaShop.

## Estructura prevista

- `classes/YujuApiClient.php`: creación de reporte sin reintentos y consulta de tarea.
- `classes/YujuSyncManager.php`: coordinar estado/cooldown y el flujo reanudable.
- `classes/YujuProductManager.php`: parsear filas, reconciliar por ID/SKU y enviar diferencias PS→Yuju.
- `controllers/admin/AdminYujuSyncController.php` y `views/templates/admin/sync_info.tpl`: inicio, progreso, resultado y acción explícita de envío.
- `tests/`: parser, límites/estados, comparación, matching ambiguo e import idempotente con stubs.

## Convenciones

- Mantener métodos y arrays PHP compatibles con el estilo del módulo; no añadir dependencias.
- Validar todo el reporte antes de escribir estado o sincronizar. Ante una fila inválida, fallar sin dejar una reconciliación parcial.
- Reutilizar las funciones actuales de preparación de payload outbound y las APIs de objetos de PrestaShop; aislar compatibilidad de combinaciones en helpers testeables.

## Comprobaciones

- Node: guía/UI y `node --check`.
- PHP: parser/estado con pruebas CLI, `php -l` de archivos modificados y PHP-CS-Fixer `--dry-run`.
- Smarty: revisar la plantilla compilable y `git diff --check`.
- Comandos base: `node tests/<prueba>.js`, `php tests/<prueba>.php`, `php -l <archivo.php>`, `vendor/bin/php-cs-fixer fix --dry-run --diff`.
- No generar un reporte con credenciales reales durante las pruebas locales; validación end-to-end en la tienda será manual y consumirá la cuota documentada.

## Límites

- No se crean productos en Yuju cuando no hay ID/pareja; se muestran como pendientes para decisión manual.
- El reporte de oferta (`/products-offer-report`) no sustituye el reporte general: solo aporta ID/SKU/stock/precio y también tiene cuota.
- Se preservan estados existentes fuera de los productos examinados; no se borra ni reorganiza el historial actual.

## Comprobaciones de implementación

- No hace falta acceso del desarrollador a la tienda ni que el dueño entregue hostname o URLs. El módulo instalado recibirá el URL en runtime; validará HTTPS, DNS/IP pública, redirects, tamaño y tiempo. El URL firmado no se mostrará ni registrará.
- El parser validará las claves documentadas (`id_product`/SKU, padre y variaciones) y fallará sin actualizar estado si la estructura real no coincide; el diagnóstico seguro se mostrará en el back-office.
- Verificar en fixtures si el JSONL incluye `id_variation`; si no, las variaciones existentes con diferencias quedarán pendientes, no se duplicarán.

## Criterios de aceptación

- No se hace ninguna llamada a `GET /products`.
- Solo se hacen hasta dos solicitudes de generación por día UTC, se reutilizan tareas/reportes y el proceso se puede reanudar tras recargar.
- El reporte enlaza por Yuju ID o por SKU único y deja coincidencias ambiguas sin tocar.
- Los estados y diffs se basan en campos outbound comparables; `synced` solo cuando coinciden.
- «Sincronizar» escribe exclusivamente PrestaShop → Yuju, no crea productos sin pareja y trata las variaciones sin duplicarlas.
- Fallos de red, reporte inválido, URL insegura, límite diario o respuesta incompleta se muestran claramente y no actualizan estados como si hubiera éxito.
