-- Diagnóstico y limpieza conservadora de claims pendientes de pedidos Yuju.
-- Alcance: SOLO psla_yuju_order_mapping (tabla propia del módulo Yuju).
-- No consulta ni modifica tablas de pedidos de PrestaShop.
--
-- IMPORTANTE:
-- 1) Ejecutar primero el SELECT de vista previa.
-- 2) La limpieza elimina ÚNICAMENTE placeholders negativos con más de 24 horas,
--    sin datos outbound. Nunca elimina mappings con prestashop_order_id > 0.
-- 3) Para conservar todo sin borrar, no ejecutes el DELETE del final.
-- 4) Si el prefijo de tu tienda no es psla_, cambia el nombre de tabla.

-- A. Resumen: mappings pendientes (IDs negativos o cero).
SELECT
    id,
    prestashop_order_id,
    yuju_order_id,
    id_channel,
    channel_name,
    outbound_external_pk,
    outbound_status,
    created_at,
    updated_at,
    CASE
        WHEN created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'CANDIDATO: pendiente >24h'
        ELSE 'RECIENTE: conservar'
    END AS diagnostico
FROM psla_yuju_order_mapping
WHERE prestashop_order_id <= 0
ORDER BY created_at ASC;

-- B. Vista previa EXACTA de las filas que cumplirían la regla de limpieza.
-- No se borra nada en esta sección.
SELECT
    id,
    prestashop_order_id,
    yuju_order_id,
    created_at,
    updated_at
FROM psla_yuju_order_mapping
WHERE prestashop_order_id < 0
  AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND (outbound_external_pk IS NULL OR outbound_external_pk = '')
  AND (outbound_status IS NULL OR outbound_status = '')
ORDER BY created_at ASC;

-- C. LIMPIEZA OPCIONAL. Descomenta y ejecuta SOLO después de revisar la vista previa.
-- Elimina únicamente claims pendientes negativos, antiguos (>24h) y sin datos outbound.
-- No modifica mappings de pedidos reales (prestashop_order_id > 0).
--
-- DELETE FROM psla_yuju_order_mapping
-- WHERE prestashop_order_id < 0
--   AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
--   AND (outbound_external_pk IS NULL OR outbound_external_pk = '')
--   AND (outbound_status IS NULL OR outbound_status = '');
