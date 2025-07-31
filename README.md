# Módulo de Integración PrestaShop - Yuju

## ¿Qué es Yuju?

Yuju es una plataforma SaaS omnicanal que ayuda a las empresas a crecer sus ventas en los mejores canales online de América Latina, optimizando y automatizando sus procesos de e-commerce. <mcreference link="https://yuju.io/" index="1">1</mcreference>

### Características principales de Yuju:
- **Gestión multicanal**: Conecta con +20 marketplaces en LATAM <mcreference link="https://yuju.io/" index="1">1</mcreference>
- **Sincronización en tiempo real**: Inventarios, productos y pedidos siempre actualizados
- **Integración con múltiples plataformas**: +5 ERPs, +4 ecommerces <mcreference link="https://yuju.io/" index="1">1</mcreference>
- **Volumen empresarial**: Gestiona +800,000 pedidos mensuales de +3,000 marcas <mcreference link="https://yuju.io/" index="1">1</mcreference>
- **GMV significativo**: Más de 700 millones USD anuales se transaccionan a través de Yuju <mcreference link="https://yuju.io/" index="1">1</mcreference>

## Sobre este Módulo

Este módulo oficial conecta tu tienda PrestaShop con la plataforma Yuju, permitiendo una gestión centralizada de todos tus canales de venta desde una sola interfaz.

## Features

### Core Functionality
- **OAuth 2.0 Authentication** - Secure connection to Yuju API
- **Bidirectional Synchronization** - Sync data between PrestaShop and Yuju
- **Real-time Webhooks** - Instant updates from Yuju platform
- **Comprehensive Logging** - Detailed logs for debugging and monitoring
- **Flexible Field Mapping** - Customize how data is synchronized

### Synchronization Capabilities
- **Products** - Full product data synchronization
- **Categories** - Category hierarchy and mapping
- **Stock** - Real-time inventory updates
- **Prices** - Price synchronization with currency support
- **Orders** - Order creation and status updates
- **Attributes** - Product attributes and variations
- **Customers** - Customer data synchronization

### Advanced Features
- **Batch Processing** - Efficient bulk operations
- **Error Handling** - Robust error recovery and retry mechanisms
- **Email Notifications** - Automated alerts for sync issues
- **Admin Dashboard** - Comprehensive management interface
- **Cron Job Support** - Automated background synchronization

## Requirements

- PrestaShop 1.6.x or higher
- PHP 5.6 or higher
- MySQL 5.6 or higher
- cURL extension enabled
- OpenSSL extension enabled
- Valid Yuju API credentials

## Installation

### Manual Installation

1. Download the module files
2. Upload the `prestashopyuju` folder to your PrestaShop `modules` directory
3. Go to your PrestaShop admin panel
4. Navigate to **Modules and Services** > **Modules & Services**
5. Search for "Yuju Integration"
6. Click **Install**

### Composer Installation

```bash
composer require yuju/prestashop-integration
```

## Configuration

### 1. API Credentials Setup

1. Go to **Configure** > **Yuju Integration** > **Configuration**
2. Enter your Yuju API credentials:
   - **Client ID**: Your Yuju application client ID
   - **Client Secret**: Your Yuju application client secret
   - **Environment**: Choose between Sandbox and Production
3. Click **Save**

### 2. OAuth Authorization

1. Click **Authorize with Yuju** button
2. You'll be redirected to Yuju's authorization page
3. Grant permissions to your PrestaShop store
4. You'll be redirected back with a success message

### 3. Synchronization Settings

1. Configure sync options:
   - **Enable Auto Sync**: Automatic synchronization on data changes
   - **Sync Frequency**: How often to run background sync (in seconds)
   - **Batch Size**: Number of items to process per batch
2. Enable specific sync types:
   - Product Sync
   - Category Sync
   - Stock Sync
   - Price Sync
   - Order Sync

### 4. Field Mapping

1. Go to **Product Mapping** to configure field mappings
2. Map PrestaShop fields to corresponding Yuju fields
3. Set transformation rules if needed
4. Configure sync direction (PrestaShop → Yuju, Yuju → PrestaShop, or Bidirectional)

### 5. Webhook Configuration

1. Go to **Webhooks** section
2. Register webhooks for real-time updates
3. Configure webhook events you want to receive
4. Test webhook connectivity

## Usage

### Manual Synchronization

1. Go to **Synchronization** section
2. Choose sync type:
   - **Full Sync**: Complete synchronization of all data
   - **Incremental Sync**: Only sync changed items
3. Select specific categories:
   - Products
   - Categories
   - Stock
   - Prices
4. Click **Start Sync**

### Monitoring

1. **Product Status**: View sync status of individual products
2. **Logs**: Check detailed synchronization logs
3. **Webhooks**: Monitor webhook activity and responses

### Automated Sync

#### Cron Job Setup

Add this line to your server's crontab for automated synchronization:

```bash
# Run every 5 minutes
*/5 * * * * /usr/bin/php /path/to/your/prestashop/modules/prestashopyuju/cron/sync.php

# Run every hour
0 * * * * /usr/bin/php /path/to/your/prestashop/modules/prestashopyuju/cron/sync.php
```

#### Webhook URL

Configure this webhook URL in your Yuju dashboard:
```
https://yourstore.com/modules/prestashopyuju/webhook.php
```

Or use the front controller:
```
https://yourstore.com/index.php?fc=module&module=prestashopyuju&controller=webhook
```

## API Endpoints

The module integrates with these Yuju API endpoints:

- **Products**: `/api/v1/products`
- **Categories**: `/api/v1/categories`
- **Orders**: `/api/v1/orders`
- **Stock**: `/api/v1/stock`
- **Prices**: `/api/v1/prices`
- **Attributes**: `/api/v1/attributes`
- **Webhooks**: `/api/v1/webhooks`
- **Customers**: `/api/v1/customers`
- **Manufacturers**: `/api/v1/manufacturers`

## Database Schema

The module creates the following database tables:

- `ps_yuju_oauth_tokens` - OAuth token storage
- `ps_yuju_category_mapping` - Category mappings
- `ps_yuju_product_mapping` - Product field mappings
- `ps_yuju_attribute_mapping` - Attribute mappings
- `ps_yuju_product_status` - Product sync status
- `ps_yuju_sync_logs` - Synchronization logs
- `ps_yuju_logs` - General module logs
- `ps_yuju_webhook_logs` - Webhook activity logs
- `ps_yuju_webhook_registrations` - Registered webhooks
- `ps_yuju_config` - Module configuration

## Troubleshooting

### Common Issues

#### 1. OAuth Authorization Failed
- Verify your Client ID and Client Secret
- Check that your redirect URI is correctly configured
- Ensure your server can make HTTPS requests

#### 2. Sync Errors
- Check the logs in **Logs** section
- Verify API credentials are valid
- Ensure products have required fields mapped

#### 3. Webhook Not Working
- Verify webhook URL is accessible from internet
- Check webhook secret configuration
- Review webhook logs for error details

#### 4. Performance Issues
- Reduce batch size in configuration
- Increase sync frequency interval
- Check server resources (CPU, memory)

### Debug Mode

Enable debug logging:
1. Go to Configuration
2. Set **Log Level** to "debug"
3. Enable **Debug Logging**
4. Check logs for detailed information

### Log Files

Logs are stored in:
- `modules/prestashopyuju/logs/yuju.log` - General logs
- `modules/prestashopyuju/logs/sync.log` - Sync logs
- `modules/prestashopyuju/logs/webhook.log` - Webhook logs
- `modules/prestashopyuju/logs/error.log` - Error logs

## Development

### File Structure

```
prestashopyuju/
├── classes/
│   ├── YujuApiClient.php
│   ├── YujuOAuthManager.php
│   ├── YujuLogger.php
│   ├── YujuSyncManager.php
│   ├── YujuProductManager.php
│   ├── YujuCategoryManager.php
│   ├── YujuAttributeManager.php
│   ├── YujuOrderManager.php
│   └── YujuWebhookManager.php
├── controllers/
│   ├── admin/
│   │   ├── AdminYujuConfigurationController.php
│   │   ├── AdminYujuSyncController.php
│   │   ├── AdminYujuProductMappingController.php
│   │   ├── AdminYujuAttributeMappingController.php
│   │   ├── AdminYujuProductStatusController.php
│   │   ├── AdminYujuWebhookController.php
│   │   └── AdminYujuLogsController.php
│   └── front/
│       └── WebhookModuleFrontController.php
├── config/
│   └── config.php
├── cron/
│   └── sync.php
├── sql/
│   ├── install.sql
│   └── uninstall.sql
├── views/
│   ├── templates/
│   │   ├── admin/
│   │   └── front/
│   ├── css/
│   └── js/
├── webhook.php
├── prestashopyuju.php
└── README.md
```

### Adding Custom Field Mappings

```php
// Add custom transformation rule
$mapping = new YujuProductMapping();
$mapping->prestashop_field = 'custom_field';
$mapping->yuju_field = 'custom_yuju_field';
$mapping->transformation_rule = 'strtoupper($value)';
$mapping->save();
```

### Custom Webhook Handlers

```php
// Register custom webhook handler
class CustomWebhookHandler
{
    public function handleCustomEvent($data)
    {
        // Custom logic here
    }
}

// In webhook processing
$webhook_manager->registerHandler('custom.event', 'CustomWebhookHandler::handleCustomEvent');
```

## Security

### Best Practices

1. **Use HTTPS**: Always use HTTPS for API communications
2. **Secure Credentials**: Store API credentials securely
3. **Webhook Verification**: Always verify webhook signatures
4. **Access Control**: Limit admin access to module configuration
5. **Regular Updates**: Keep the module updated

### Webhook Security

Webhooks are verified using HMAC-SHA256 signatures:

```php
$signature = hash_hmac('sha256', $payload, $webhook_secret);
if (!hash_equals($signature, $received_signature)) {
    throw new Exception('Invalid webhook signature');
}
```

## Support

### Documentation
- [Yuju API Documentation](https://docs.yuju.com)
- [PrestaShop Module Development](https://devdocs.prestashop.com)

### Getting Help

1. Check the **Logs** section for error details
2. Review this documentation
3. Contact Yuju support team
4. Submit issues on GitHub repository

### Version History

#### v1.0.0
- Initial release
- OAuth 2.0 authentication
- Product, category, and order synchronization
- Webhook support
- Admin interface
- Comprehensive logging

## License

This module is licensed under the Academic Free License (AFL 3.0).

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Changelog

### [1.0.0] - 2024-01-01
#### Added
- Initial module release
- OAuth 2.0 authentication system
- Product synchronization
- Category synchronization
- Order synchronization
- Stock synchronization
- Price synchronization
- Attribute mapping
- Webhook support
- Comprehensive admin interface
- Detailed logging system
- Cron job support
- Email notifications
- Field mapping configuration
- Batch processing
- Error handling and retry mechanisms