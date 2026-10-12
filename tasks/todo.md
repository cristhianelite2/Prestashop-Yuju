# Tasks: Conciliar catálogo PrestaShop → Yuju

- [x] 1. API report, estados persistentes y límite de dos solicitudes UTC por día.
- [x] 2. Descarga privada/validada y parser JSONL con variantes.
- [x] 3. Matching ID/SKU único, diferencias y estados en `yuju_product_status`.
- [x] 4. Envío de diferencias PS→Yuju para productos vinculados y variaciones sin duplicados.
- [x] 5. Progreso reanudable, lista de diferencias y sincronización explícita en back-office.

## Checkpoint de reporte
- [x] POST no reintentado ni duplicado; tarea reanudable.
- [x] Parser/descarga validados offline, sin URL firmada ni productos en logs.

## Checkpoint de sincronización
- [x] No se llama `GET /products` en el nuevo flujo.
- [x] No se crean productos padre sin ID/pareja.
- [x] Sólo PrestaShop→Yuju; variantes se actualizan o quedan pendientes sin duplicarse.