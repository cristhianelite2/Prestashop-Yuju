# Herramientas de Formateo de Código

Este proyecto incluye varias herramientas para mantener un código PHP limpio y consistente.

## Herramientas Instaladas

### 1. PHP_CodeSniffer (PHPCS) + PHP Code Beautifier (PHPCBF)
- **PHPCS**: Detecta violaciones de estándares de código
- **PHPCBF**: Corrige automáticamente problemas de formato
- **Estándar**: PSR-12

### 2. Easy Coding Standard (ECS)
- Herramienta moderna que combina PHP CS Fixer y PHPCS
- Ejecución paralela para mayor velocidad

## Comandos Disponibles

### Scripts de Composer (Recomendado)
```bash
# Verificar problemas de formato
composer cs-check

# Corregir automáticamente problemas de formato
composer cs-fix
# o simplemente:
composer format

# Usar ECS para verificar
composer ecs-check

# Usar ECS para corregir
composer ecs-fix
```

### Comandos Directos
```bash
# PHPCS - Verificar código
./vendor/bin/phpcs --standard=PSR12 archivo.php
./vendor/bin/phpcs --standard=PSR12 directorio/

# PHPCBF - Corregir código automáticamente
./vendor/bin/phpcbf --standard=PSR12 archivo.php
./vendor/bin/phpcbf --standard=PSR12 directorio/

# ECS - Verificar código
./vendor/bin/ecs

# ECS - Corregir código automáticamente
./vendor/bin/ecs --fix
```

## Archivos de Configuración

- **ecs.php**: Configuración para Easy Coding Standard
- **.php-cs-fixer.php**: Configuración para PHP CS Fixer (ya existente)

## Estándares Aplicados

- **PSR-12**: Estándar de codificación PHP moderno
- **Reglas comunes**: Espaciado, indentación, llaves, etc.
- **Reglas de Symplify**: Reglas adicionales para mejor calidad de código

## Integración con Editores

Puedes integrar estas herramientas con tu editor favorito:

- **VS Code**: Extensiones para PHPCS y PHP CS Fixer
- **PhpStorm**: Soporte nativo para PHPCS y PHP CS Fixer
- **Sublime Text**: Plugins disponibles

## Uso Recomendado

1. **Durante el desarrollo**: Ejecuta `composer format` antes de hacer commit
2. **En CI/CD**: Ejecuta `composer cs-check` para verificar el código
3. **Automatización**: Configura hooks de pre-commit para formateo automático

## Resolución de Problemas

Si encuentras errores de memoria con Composer:
```bash
php -d memory_limit=-1 D:/ComposerSetup/composer.phar [comando]
```

## Estado Actual

✅ **Todo el código está correctamente formateado según PSR-12**
✅ **No se encontraron errores de sintaxis**
✅ **Herramientas configuradas y funcionando**