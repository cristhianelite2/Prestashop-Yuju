# AGENTS.md — Prestashop-Yuju

> **Fuente común de directivas:** `_sistema/Workflow.md` del vault Obsidian. Este archivo las especializa para `Prestashop-Yuju` y **no las contradice**. La memoria oficial vive en `Proyectos/Prestashop-Yuju/` del vault (ficha, Bitácora, To-Do, Decisiones).

## Reglas obligatorias

- **Antes de programar:** leer ficha + Bitácora + To-Do + Decisiones en `Proyectos/Prestashop-Yuju/`; revisar la arquitectura y reutilizar lo existente.
- **Rama:** desarrollo en `test`; `main` solo cuando el usuario pida subir (`test` → `main` + push de ambas).
- **CHANGELOG:** actualizar `CHANGELOG.md` antes de publicar cambios a `test`.
- **Cierre:** registrar la tarea en la Bitácora del vault (qué se hizo, archivos, verificación, pendientes).
- **Límites:** no sobrescribir trabajo ajeno ni ejecutar comandos destructivos sin autorización; no afirmar pruebas sin evidencia; no dar por terminado sin cumplir los criterios.

## Proyecto

- **Qué es:** Módulo de integración de Yuju con tiendas PrestaShop.
- **Ubicación:** `D:\Proyectos\Prestashop-Yuju`
- **Stack:** PHP (módulo PrestaShop).
- **Repositorio:** `cristhianelite2/Prestashop-Yuju`

## Comandos

```powershell
php -l <archivo>.php             # lint de sintaxis
vendor/bin/php-cs-fixer fix       # estilo (.php-cs-fixer.php)
```

## Estructura

- `controllers/`, `classes/`, `views/`, `sql/`, `upgrade/`, `translations/`.
- `cron/`, `docs/`; versión 1.1.5.

## Documentación

- Vault: `Proyectos/Prestashop-Yuju/` (ficha, Bitácora, To-Do, Decisiones).
- `CHANGELOG.md` en la raíz del repositorio.
