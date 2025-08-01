# Actualización al Nuevo Sistema de Traducciones de PrestaShop 1.7.6+

## Resumen de Cambios

Este módulo ha sido actualizado para usar el nuevo sistema de traducciones de PrestaShop 1.7.6+ que utiliza archivos XLIFF y dominios de traducción en lugar del sistema legacy con archivos PHP.

## Cambios Realizados

### 1. Archivos de Traducción

#### Eliminados:
- `translations/es.php` (sistema legacy)

#### Creados:
- `translations/es-ES/ModulesPrestashopyujuAdmin.es-ES.xlf` - Traducciones del back office
- `translations/es-ES/ModulesPrestashopyujuShop.es-ES.xlf` - Traducciones del front office

### 2. Código del Módulo

#### Archivo: `prestashopyuju.php`
- Cambiado `$this->l()` por `$this->trans()` con dominio `Modules.Prestashopyuju.Admin`
- Actualizado displayName, description y confirmUninstall
- Actualizado todos los nombres de menús en el método `installTabs()`

#### Archivo: `controllers/admin/AdminYujuConfigurationController.php`
- Cambiado `$this->l()` por `$this->trans()` con dominio `Modules.Prestashopyuju.Admin`
- Actualizado meta_title y toolbar_title

### 3. Estructura de Dominios de Traducción

- **Modules.Prestashopyuju.Admin**: Para todas las traducciones del back office
- **Modules.Prestashopyuju.Shop**: Para todas las traducciones del front office

### 4. Formato XLIFF

Los archivos XLIFF siguen la estructura estándar:
```xml
<trans-unit id="X">
  <source>English text</source>
  <target>Texto en español</target>
</trans-unit>
```

## Beneficios del Nuevo Sistema

1. **Mejor organización**: Separación clara entre back office y front office
2. **Estándar internacional**: XLIFF es un formato estándar para traducciones
3. **Mejor rendimiento**: Sistema optimizado de caché
4. **Compatibilidad**: Funciona con herramientas de traducción profesionales
5. **Mantenimiento**: Más fácil de mantener y actualizar

## Instrucciones de Uso

### Para Desarrolladores

1. **Usar el método `trans()` en lugar de `l()`:**
   ```php
   // Antes (legacy)
   $this->l('Configuration')
   
   // Ahora (nuevo sistema)
   $this->trans('Configuration', array(), 'Modules.Prestashopyuju.Admin')
   ```

2. **Agregar nuevas traducciones:**
   - Editar los archivos XLIFF correspondientes
   - Limpiar caché usando el script proporcionado
   - Regenerar traducciones en el back office

### Para Administradores

1. **Limpiar caché de traducciones:**
   ```bash
   php clear_new_translations_cache.php
   ```

2. **Regenerar traducciones en PrestaShop:**
   - Ir a Internacional > Traducciones
   - Seleccionar "Modificar traducciones"
   - Elegir "Módulos" y seleccionar "prestashopyuju"
   - Guardar para regenerar la caché

## Archivos de Utilidad

- `clear_new_translations_cache.php`: Script para limpiar caché de traducciones
- `TRANSLATION_SYSTEM_UPDATE.md`: Esta documentación

## Compatibilidad

- **PrestaShop**: 1.7.6+ (requerido para el nuevo sistema)
- **PHP**: 7.1+ (recomendado)
- **Navegadores**: Todos los navegadores modernos

## Notas Importantes

1. El sistema legacy (`es.php`) ha sido completamente eliminado
2. Todas las traducciones ahora usan dominios específicos
3. Los archivos XLIFF deben estar en UTF-8
4. Es necesario limpiar caché después de cualquier cambio en traducciones
5. El módulo mantiene compatibilidad hacia atrás con versiones anteriores de PrestaShop

## Solución de Problemas

### Las traducciones no aparecen
1. Verificar que los archivos XLIFF estén en la ubicación correcta
2. Limpiar caché usando el script proporcionado
3. Regenerar traducciones en el back office
4. Verificar que el dominio de traducción sea correcto

### Error de sintaxis en archivos XLIFF
1. Verificar que el XML esté bien formado
2. Verificar codificación UTF-8
3. Verificar que no haya caracteres especiales sin escapar

### Caché no se limpia
1. Verificar permisos de escritura en directorios de caché
2. Ejecutar el script de limpieza como administrador
3. Limpiar manualmente los directorios de caché

---

**Fecha de actualización**: $(date)
**Versión del sistema**: PrestaShop 1.7.6+ Translation System
**Módulo**: Yuju Integration (prestashopyuju)