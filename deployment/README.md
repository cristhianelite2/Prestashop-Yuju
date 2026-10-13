# Puentes opcionales bajo la raíz de PrestaShop

Los archivos `root/yuju/oauth.php` y `root/yuju/terms.php` son puentes opcionales para instalaciones que conservan estas rutas fuera del directorio del módulo:

- `/shop/yuju/oauth.php`
- `/shop/yuju/terms.php`

Copie cada archivo al directorio equivalente bajo la raíz web de PrestaShop. Estos archivos son externos a `/shop/modules/yuju/`, por lo que el actualizador del módulo no puede desplegarlos allí automáticamente.

El módulo actual se llama `yuju`. No use el nombre antiguo `prestashopyuju` en los puentes.

El flujo principal de OAuth del módulo genera el callback estándar `/shop/module/yuju/oauth`; configure esa URI exacta en Yuju para usar el flujo normal. Los puentes root son solo compatibilidad para instalaciones que explícitamente necesitan las URLs `/shop/yuju/*.php`.
