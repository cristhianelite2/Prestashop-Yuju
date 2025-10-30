/*
* 2024 Yuju Integration
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* @author    Yuju Integration Team
* @copyright 2024 Yuju Integration
* @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

/**
 * Yuju Module Admin JavaScript
 */
var YujuAdmin = {
    // Configuration
    config: {
        ajaxUrl: '',
        token: '',
        refreshInterval: 5000,
        maxLogEntries: 100
    },

    // State
    state: {
        isSyncing: false,
        syncInterval: null,
        logInterval: null
    },

    /**
     * Initialize the admin interface
     */
    init: function(config) {
        // Merge config and ensure access_token is set from ps_yuju_oauth_tokens
        this.config = Object.assign(this.config, config);
        if (config.oauth_token && config.oauth_token.access_token) {
            this.config.token = config.oauth_token.access_token;
        }
        this.bindEvents();
        this.initTooltips();
        this.initCopyButtons();
        this.startAutoRefresh();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function() {
        var self = this;

        // API Test button
        $(document).on('click', '#yuju-test-api', function(e) {
            e.preventDefault();
            self.testApiConnection();
        });

        // Test Connectivity button
        $(document).on('click', '#yuju-test-connectivity', function(e) {
            e.preventDefault();
            self.testConnectivity();
        });

        // Test Products button
        $(document).on('click', '#yuju-test-products', function(e) {
            e.preventDefault();
            self.testProducts();
        });

        // OAuth Authorization button
        $(document).on('click', '#yuju-oauth-authorize', function(e) {
            e.preventDefault();
            self.startOAuthAuthorization();
        });

        // Sync buttons
        $(document).on('click', '.yuju-sync-button', function(e) {
            e.preventDefault();
            var syncType = $(this).data('sync-type');
            var syncMode = $(this).data('sync-mode');
            self.startSync(syncType, syncMode);
        });

        // Stop sync button
        $(document).on('click', '#yuju-stop-sync', function(e) {
            e.preventDefault();
            self.stopSync();
        });

        // Clean logs button
        $(document).on('click', '#yuju-clean-logs', function(e) {
            e.preventDefault();
            self.cleanLogs();
        });

        // View log details
        $(document).on('click', '.yuju-view-log', function(e) {
            e.preventDefault();
            var logId = $(this).data('log-id');
            self.viewLogDetails(logId);
        });

        // Copy to clipboard functionality is now handled in initCopyButtons()

        // Form validation
        $(document).on('submit', '#yuju-config-form', function(e) {
            if (!self.validateForm(this)) {
                e.preventDefault();
            }
        });

        // Auto-save configuration
        $(document).on('change', '.yuju-auto-save', function() {
            self.autoSaveConfig();
        });
    },

    /**
     * Initialize tooltips
     */
    initTooltips: function() {
        $('.yuju-tooltip').each(function() {
            var $this = $(this);
            var tooltip = $this.attr('title') || $this.data('tooltip');
            if (tooltip) {
                $this.attr('data-tooltip', tooltip).removeAttr('title');
            }
        });
    },

    /**
     * Start auto-refresh for sync status
     */
    startAutoRefresh: function() {
        var self = this;
        if (this.state.syncInterval) {
            clearInterval(this.state.syncInterval);
        }
        this.state.syncInterval = setInterval(function() {
            self.refreshSyncStatus();
        }, this.config.refreshInterval);
    },

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh: function() {
        if (this.state.syncInterval) {
            clearInterval(this.state.syncInterval);
            this.state.syncInterval = null;
        }
    },

    /**
     * Test API connection
     */
    testApiConnection: function() {
        var self = this;
        var $button = $('#yuju-test-api');
        var $status = $('#yuju-api-status');

        $button.prop('disabled', true).html('<i class="icon-refresh yuju-spin"></i> Testing...');
        $status.removeClass().addClass('yuju-status syncing').text('Testing...');

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'testApi',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $status.removeClass().addClass('yuju-status connected').text('Connected');
                    self.showAlert('API connection successful!', 'success');
                } else {
                    $status.removeClass().addClass('yuju-status disconnected').text('Disconnected');
                    self.showAlert('API connection failed: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                $status.removeClass().addClass('yuju-status error').text('Error');
                self.showAlert('Failed to test API connection', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="icon-check"></i> Test Connection');
            }
        });
    },

    /**
     * Test connectivity with webhook-sub endpoint
     */
    testConnectivity: function() {
        console.log('🔄 Iniciando testConnectivity');
        var self = this;
        var $button = $('#yuju-test-connectivity');
        var $result = $('#connectivity-result');

        console.log('📋 Elementos DOM obtenidos:', {
            button: $button.length,
            result: $result.length,
            ajaxUrl: this.config.ajaxUrl,
            token: this.config.token ? 'presente' : 'ausente'
        });

        $button.prop('disabled', true).html('<i class="icon-refresh yuju-spin"></i> Probando...');
        
        // Show initial progress message
        var progressHtml = '<div class="alert alert-info"><strong>Probando conectividad con webhook-sub endpoint...</strong><br>';
        progressHtml += '<div id="progress-steps"></div></div>';
        $result.html(progressHtml).show();
         
        var $progressSteps = $('#progress-steps');
        
        // Step 1: Obtener token de acceso
        $progressSteps.append('<div><i class="icon-refresh yuju-spin"></i> Obteniendo token de acceso...</div>');
        
        console.log('🌐 Enviando petición AJAX para obtener token');
        
        // Primero obtener el token del servidor
         
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'testConnectivity',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                console.log('✅ Respuesta del servidor recibida:', response);
                
                if (response.success && response.data) {
                    console.log('🔑 Conectividad probada desde el servidor:', {
                        endpoint: response.data.endpoint,
                        httpCode: response.data.http_code,
                        statusText: response.data.status_text
                    });
    
                    // Update first step as completed
                    $progressSteps.find('div:first').html('✓ Token de acceso obtenido');
                    $progressSteps.append('<div>✓ Petición GET completada desde el servidor</div>');
                    
                    console.log('📄 Procesando respuesta de la API:', {
                        status: response.data.http_code,
                        statusText: response.data.status_text,
                        responseLength: response.data.response ? response.data.response.length : 0,
                        responsePreview: response.data.response ? response.data.response.substring(0, 100) + '...' : 'Sin respuesta'
                    });
                    
                    var html = '<div class="alert alert-success"><strong>✓ Conectividad exitosa</strong><br>';
                    html += '<strong>Endpoint:</strong> ' + response.data.endpoint + '<br>';
                    html += '<strong>Código HTTP:</strong> ' + response.data.http_code + ' ' + response.data.status_text + '<br>';
                    
                    // Mostrar información del token para debug
                    if (response.data.token_debug) {
                        html += '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
                        html += '<strong style="color: #856404;">🔍 DEBUG - Token Info (Conectividad):</strong><br>';
                        html += '<small>';
                        html += 'Token Length: ' + response.data.token_debug.token_length + '<br>';
                        html += 'Token: ' + response.data.token_debug.token_usado.substring(0, 80) + '...<br>';
                        html += 'Son Iguales (Token vs DB): ' + (response.data.token_debug.son_iguales ? '✅ SÍ' : '❌ NO') + '<br>';
                        html += 'Expires: ' + response.data.token_debug.expires_at + '<br>';
                        html += 'Client ID: ' + response.data.token_debug.client_id + '<br>';
                        html += '</small></div>';
                    }
                    
                    html += '<strong>Respuesta del servidor:</strong><br>';
                    html += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 5px;">';
                    
                    try {
                        var jsonResult = JSON.parse(response.data.response);
                        html += JSON.stringify(jsonResult, null, 2);
                    } catch (e) {
                        html += response.data.response || 'Sin contenido de respuesta';
                    }
                    
                    html += '</pre></div>';
                    $result.html(html);
                    
                    $button.prop('disabled', false).html('<i class="icon-plug"></i> Probar Conectividad');
                } else {
                    console.log('❌ Error en la respuesta del servidor:', {
                        success: response.success,
                        hasData: !!response.data,
                        message: response.message,
                        fullResponse: response
                    });
                    
                    // Error en la conectividad
                    var html = '<div class="alert alert-danger"><strong>✗ Error en la conectividad</strong><br>';
                    html += '<strong>Mensaje:</strong> ' + (response.message || 'Error desconocido') + '<br>';
                    html += '</div>';
                    $result.html(html);
                    
                    $button.prop('disabled', false).html('<i class="icon-plug"></i> Probar Conectividad');
                }
            },
            error: function(xhr, status, error) {
                console.log('💥 Error en petición AJAX:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    readyState: xhr.readyState
                });
                
                var html = '<div class="alert alert-danger"><strong>✗ Error de conexión</strong><br>';
                html += 'No se pudo conectar con el servidor para obtener el token de acceso.';
                html += '</div>';
                $result.html(html);
                
                self.showAlert('Error de conexión al obtener token', 'error');
                $button.prop('disabled', false).html('<i class="icon-plug"></i> Probar Conectividad');
            }
        });
    },

    /**
     * Start OAuth authorization process
     */
    startOAuthAuthorization: function() {
        var self = this;
        var $button = $('#yuju-oauth-authorize');
        var $status = $('#yuju-oauth-status');

        $button.prop('disabled', true).html('<i class="icon-refresh yuju-spin"></i> Iniciando autorización...');
        $status.removeClass().addClass('yuju-status syncing').text('Iniciando...');

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'startOAuth',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.auth_url) {
                    $status.removeClass().addClass('yuju-status syncing').text('Redirigiendo...');
                    self.showAlert('Redirigiendo a la autorización OAuth...', 'info');
                    
                    // Abrir ventana de autorización
                    var authWindow = window.open(
                        response.data.auth_url,
                        'yuju_oauth',
                        'width=600,height=700,scrollbars=yes,resizable=yes'
                    );
                    
                    // Monitorear el estado de la autorización
                    self.monitorOAuthProcess(authWindow);
                } else {
                    $status.removeClass().addClass('yuju-status error').text('Error');
                    self.showAlert('Error al iniciar autorización OAuth: ' + (response.message || 'Error desconocido'), 'error');
                }
            },
            error: function() {
                $status.removeClass().addClass('yuju-status error').text('Error');
                self.showAlert('Error de conexión al iniciar OAuth', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="icon-key"></i> Autorizar OAuth');
            }
        });
    },

    /**
     * Monitor OAuth authorization process
     */
    monitorOAuthProcess: function(authWindow) {
        var self = this;
        var $status = $('#yuju-oauth-status');
        var checkInterval;
        
        $status.removeClass().addClass('yuju-status syncing').text('Esperando autorización...');
        
        checkInterval = setInterval(function() {
            try {
                if (authWindow.closed) {
                    clearInterval(checkInterval);
                    self.checkOAuthStatus();
                    return;
                }
                
                // Intentar detectar si la autorización fue completada
                var currentUrl = authWindow.location.href;
                if (currentUrl.indexOf('oauth_success') !== -1 || currentUrl.indexOf('code=') !== -1) {
                    clearInterval(checkInterval);
                    authWindow.close();
                    self.checkOAuthStatus();
                }
            } catch (e) {
                // Error de cross-origin, continuar monitoreando
            }
        }, 1000);
        
        // Timeout después de 5 minutos
        setTimeout(function() {
            if (checkInterval) {
                clearInterval(checkInterval);
                if (!authWindow.closed) {
                    authWindow.close();
                }
                $status.removeClass().addClass('yuju-status error').text('Timeout');
                self.showAlert('Tiempo de espera agotado para la autorización OAuth', 'warning');
            }
        }, 300000);
    },

    /**
     * Check OAuth authorization status
     */
    checkOAuthStatus: function() {
        var self = this;
        var $status = $('#yuju-oauth-status');
        
        $status.removeClass().addClass('yuju-status syncing').text('Verificando...');
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'checkOAuthStatus',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.authorized) {
                    $status.removeClass().addClass('yuju-status connected').text('Autorizado');
                    self.showAlert('Autorización OAuth completada exitosamente', 'success');
                    
                    // Actualizar información del token si está disponible
                    if (response.data.token_info) {
                        self.updateTokenInfo(response.data.token_info);
                    }
                    
                    // Refrescar la página para mostrar el nuevo estado
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    $status.removeClass().addClass('yuju-status disconnected').text('No autorizado');
                    self.showAlert('Autorización OAuth no completada: ' + (response.message || 'Proceso cancelado'), 'warning');
                }
            },
            error: function() {
                $status.removeClass().addClass('yuju-status error').text('Error');
                self.showAlert('Error al verificar el estado de OAuth', 'error');
            }
        });
    },

    /**
     * Update token information display
     */
    updateTokenInfo: function(tokenInfo) {
        if (tokenInfo.expires_at) {
            $('#yuju-token-expires').text(tokenInfo.expires_at);
        }
        if (tokenInfo.scope) {
            $('#yuju-token-scope').text(tokenInfo.scope);
        }
    },

    /**
     * Test products API - Create sample product
     */
    testProducts: function() {
        console.log('DEBUG: testProducts function called');
        
        var self = this;
        var $button = $('#yuju-test-products');
        var $result = $('#products-result'); // Corregido: usar el ID correcto del template
        
        console.log('DEBUG: Button found:', $button.length > 0);
        console.log('DEBUG: Result container found:', $result.length > 0);
        console.log('DEBUG: Config:', this.config);

        $button.prop('disabled', true).html('<i class="icon-refresh yuju-spin"></i> Creando producto de prueba...');
        
        // Show initial progress message
        var progressHtml = '<div class="alert alert-info"><strong>Creando producto de prueba...</strong><br>';
        progressHtml += '<div id="products-progress-steps"></div></div>';
        $result.html(progressHtml).show();
        
        var $progressSteps = $('#products-progress-steps');
        
        // Step 1: Verificar autenticación
        $progressSteps.append('<div><i class="icon-refresh yuju-spin"></i> Verificando autenticación...</div>');
        
        // Debug: Log AJAX configuration
        console.log('DEBUG: AJAX URL:', this.config.ajaxUrl);
        console.log('DEBUG: AJAX Token:', this.config.token);
        console.log('DEBUG: Starting AJAX call for testProducts');
        
        // Construir URL con parámetros GET para PrestaShop
        var ajaxUrl = this.config.ajaxUrl + '&ajax=1&action=testProducts';
        console.log('DEBUG: Final AJAX URL:', ajaxUrl);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            beforeSend: function(xhr) {
                console.log('DEBUG: AJAX request about to be sent');
            },
            success: function(response) {
                console.log('DEBUG: AJAX success response received:', response);
                if (response.success) {
                    // Update authentication step
                    $progressSteps.find('div:first').html('✓ Autenticación verificada');
                    
                    // Step 2: Creating product
                    $progressSteps.append('<div><i class="icon-refresh yuju-spin"></i> Enviando producto a Yuju...</div>');
                    
                    setTimeout(function() {
                        $progressSteps.find('div:last').html('✓ Producto enviado a Yuju');
                        
                        // Show final success message
                        var html = '<div class="alert alert-success">';
                        html += '<strong>✓ ' + (response.message || 'Producto de prueba creado exitosamente') + '</strong><br>';
                        
                        if (response.product) {
                            html += '<div style="margin-top: 10px; padding: 10px; background: #d4edda; border-radius: 4px;">';
                            if (response.product.id_product) {
                                html += '<div><strong>ID Yuju:</strong> ' + response.product.id_product + '</div>';
                            }
                            if (response.product.name) {
                                html += '<div><strong>Nombre:</strong> ' + response.product.name + '</div>';
                            }
                            if (response.product.sku) {
                                html += '<div><strong>SKU:</strong> ' + response.product.sku + '</div>';
                            }
                            if (response.product.id_shop) {
                                html += '<div><strong>ID Shop:</strong> ' + response.product.id_shop + '</div>';
                            }
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        $result.html(html);
                        
                        self.showAlert('Producto de prueba creado exitosamente', 'success');
                    }, 1000);
                    
                } else {
                    var html = '<div class="alert alert-danger">';
                    html += '<strong>❌ Error al crear producto de prueba</strong><br>';
                    html += '<div style="margin-top: 10px;">' + (response.message || response.error || 'Error desconocido') + '</div>';
                    
                    if (response.errors && response.errors.length > 0) {
                        html += '<div style="margin-top: 10px;"><strong>Errores de Yuju:</strong><br>';
                        html += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-size: 11px;">';
                        html += JSON.stringify(response.errors, null, 2);
                        html += '</pre></div>';
                    }
                    
                    html += '</div>';
                    $result.html(html);
                    
                    self.showAlert('Error al crear producto de prueba: ' + (response.message || 'Error desconocido'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.log('DEBUG: AJAX error occurred');
                console.log('DEBUG: XHR:', xhr);
                console.log('DEBUG: Status:', status);
                console.log('DEBUG: Error:', error);
                console.log('DEBUG: Response Text:', xhr.responseText);
                
                var html = '<div class="alert alert-danger"><strong>❌ Error de conexión</strong><br>';
                html += '<div style="margin-top: 10px;">No se pudo conectar con el servidor para crear el producto de prueba.</div>';
                html += '<div style="margin-top: 5px;"><strong>Error técnico:</strong> ' + error + '</div>';
                html += '<div style="margin-top: 5px;"><strong>Status:</strong> ' + status + '</div>';
                html += '<div style="margin-top: 5px;"><strong>Response:</strong> ' + xhr.responseText + '</div>';
                html += '</div>';
                $result.html(html);
                
                self.showAlert('Error de conexión al crear producto de prueba', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="icon-check"></i> Probar Productos');
            }
        });
    },



    /**
     * Start synchronization
     */
    startSync: function(syncType, syncMode) {
        var self = this;
        
        if (this.state.isSyncing) {
            this.showAlert('Synchronization is already in progress', 'warning');
            return;
        }

        this.state.isSyncing = true;
        this.updateSyncUI(true);

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'startSync',
                syncType: syncType,
                syncMode: syncMode,
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showAlert('Synchronization started successfully', 'success');
                    self.startSyncMonitoring();
                } else {
                    self.showAlert('Failed to start synchronization: ' + (response.message || 'Unknown error'), 'error');
                    self.state.isSyncing = false;
                    self.updateSyncUI(false);
                }
            },
            error: function() {
                self.showAlert('Failed to start synchronization', 'error');
                self.state.isSyncing = false;
                self.updateSyncUI(false);
            }
        });
    },

    /**
     * Stop synchronization
     */
    stopSync: function() {
        var self = this;
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stopSync',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showAlert('Synchronization stopped', 'info');
                } else {
                    self.showAlert('Failed to stop synchronization: ' + (response.message || 'Unknown error'), 'error');
                }
                self.state.isSyncing = false;
                self.updateSyncUI(false);
            },
            error: function() {
                self.showAlert('Failed to stop synchronization', 'error');
            }
        });
    },

    /**
     * Start monitoring sync progress
     */
    startSyncMonitoring: function() {
        var self = this;
        this.refreshSyncStatus();
        
        if (this.state.logInterval) {
            clearInterval(this.state.logInterval);
        }
        
        this.state.logInterval = setInterval(function() {
            self.refreshSyncStatus();
        }, 2000);
    },

    /**
     * Refresh sync status
     */
    refreshSyncStatus: function() {
        var self = this;
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'getSyncStatus',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.updateSyncStatus(response.data);
                    
                    // Stop monitoring if sync is complete
                    if (!response.data.is_syncing) {
                        self.state.isSyncing = false;
                        self.updateSyncUI(false);
                        if (self.state.logInterval) {
                            clearInterval(self.state.logInterval);
                            self.state.logInterval = null;
                        }
                    }
                }
            }
        });
    },

    /**
     * Update sync status display
     */
    updateSyncStatus: function(data) {
        // Update progress bar
        if (data.progress !== undefined) {
            var $progressBar = $('.yuju-progress-bar');
            $progressBar.css('width', data.progress + '%').text(data.progress + '%');
        }

        // Update status text
        if (data.status) {
            $('#yuju-sync-status').text(data.status);
        }

        // Update statistics
        if (data.stats) {
            $.each(data.stats, function(key, value) {
                $('#yuju-stat-' + key).text(value);
            });
        }

        // Update last sync time
        if (data.last_sync) {
            $('#yuju-last-sync').text(data.last_sync);
        }
    },

    /**
     * Update sync UI state
     */
    updateSyncUI: function(isSyncing) {
        var $syncButtons = $('.yuju-sync-button');
        var $stopButton = $('#yuju-stop-sync');
        
        if (isSyncing) {
            $syncButtons.prop('disabled', true);
            $stopButton.prop('disabled', false).show();
            $('#yuju-sync-status').removeClass().addClass('yuju-status syncing').text('Syncing...');
        } else {
            $syncButtons.prop('disabled', false);
            $stopButton.prop('disabled', true).hide();
            $('#yuju-sync-status').removeClass().addClass('yuju-status synced').text('Ready');
        }
    },

    /**
     * Clean old logs
     */
    cleanLogs: function() {
        var self = this;
        
        if (!confirm('Are you sure you want to clean old logs?')) {
            return;
        }

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cleanLogs',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showAlert('Logs cleaned successfully', 'success');
                    // Refresh logs table
                    window.location.reload();
                } else {
                    self.showAlert('Failed to clean logs: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showAlert('Failed to clean logs', 'error');
            }
        });
    },

    /**
     * View log details
     */
    viewLogDetails: function(logId) {
        var self = this;
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'getLogDetails',
                logId: logId,
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showLogModal(response.data);
                } else {
                    self.showAlert('Failed to load log details: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showAlert('Failed to load log details', 'error');
            }
        });
    },

    /**
     * Show log details modal
     */
    showLogModal: function(logData) {
        var modalHtml = '<div class="modal fade" id="yuju-log-modal" tabindex="-1">' +
            '<div class="modal-dialog modal-lg">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h4 class="modal-title">Log Details</h4>' +
            '<button type="button" class="close" data-dismiss="modal">&times;</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div class="yuju-json-viewer">' + this.formatJSON(logData) + '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        // Remove existing modal
        $('#yuju-log-modal').remove();
        
        // Add and show new modal
        $('body').append(modalHtml);
        $('#yuju-log-modal').modal('show');
    },

    /**
     * Format JSON for display
     */
    formatJSON: function(obj) {
        return JSON.stringify(obj, null, 2)
            .replace(/"([^"]+)":/g, '<span class="yuju-json-key">"$1":</span>')
            .replace(/: "([^"]*)"/g, ': <span class="yuju-json-string">"$1"</span>')
            .replace(/: (\d+)/g, ': <span class="yuju-json-number">$1</span>')
            .replace(/: (true|false)/g, ': <span class="yuju-json-boolean">$1</span>')
            .replace(/: null/g, ': <span class="yuju-json-null">null</span>');
    },

    /**
     * Copy text to clipboard with fallback
     */
    copyToClipboard: function(text, $button) {
        var self = this;
        
        if (!text) {
            
            return;
        }
        
        // Guardar el HTML original del botón
        var originalHtml = $button.html();
        
        // Mostrar estado de "copiando"
        $button.html('<i class="icon-refresh icon-spin"></i> Copiando...');
        $button.prop('disabled', true);
        $button.css('background-color', '#ffc107');
        
        // Función para ejecutar la copia
        function ejecutarCopia() {
            try {
                // Intentar usar la API moderna primero
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function() {
    
                        self.showCopySuccess($button, originalHtml);
                    }).catch(function(err) {
                        
                        self.fallbackCopyToClipboard(text, $button, originalHtml);
                    });
                } else {
                    // Usar fallback directamente
                    self.fallbackCopyToClipboard(text, $button, originalHtml);
                }
            } catch (err) {
                
                self.showCopyError($button, originalHtml, text);
            }
        }
        
        // Ejecutar con un pequeño delay
        setTimeout(ejecutarCopia, 100);
    },
    
    /**
     * Fallback copy method for older browsers
     */
    fallbackCopyToClipboard: function(text, $button, originalHtml) {
        var self = this;
        
        try {
            // Crear textarea temporal
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.top = '0';
            textarea.style.left = '0';
            textarea.style.width = '2em';
            textarea.style.height = '2em';
            textarea.style.padding = '0';
            textarea.style.border = 'none';
            textarea.style.outline = 'none';
            textarea.style.boxShadow = 'none';
            textarea.style.background = 'transparent';
            textarea.setAttribute('readonly', '');
            
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            
            var successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            
            if (successful) {

                self.showCopySuccess($button, originalHtml);
            } else {
                throw new Error('execCommand falló');
            }
        } catch (err) {
            
            self.showCopyError($button, originalHtml, text);
        }
    },
    
    /**
     * Show copy success state
     */
    showCopySuccess: function($button, originalHtml) {
        $button.html('<i class="icon-check"></i> ¡Copiado!');
        $button.css({
            'background-color': '#5cb85c',
            'border-color': '#5cb85c',
            'color': 'white'
        });
        
        // Restaurar después de 2.5 segundos
        setTimeout(function() {
            $button.html(originalHtml);
            $button.css({
                'background-color': '',
                'border-color': '',
                'color': ''
            });
            $button.prop('disabled', false);
        }, 2500);
    },
    
    /**
     * Show copy error state
     */
    showCopyError: function($button, originalHtml, text) {
        var self = this;
        
        $button.html('<i class="icon-warning"></i> Error');
        $button.css({
            'background-color': '#d9534f',
            'border-color': '#d9534f',
            'color': 'white'
        });
        
        // Mostrar alert después de un breve delay
        setTimeout(function() {
            alert('No se pudo copiar automáticamente.\n\nCopie esta URL manualmente:\n\n' + text);
            
            // Restaurar botón
            $button.html(originalHtml);
            $button.css({
                'background-color': '',
                'border-color': '',
                'color': ''
            });
            $button.prop('disabled', false);
        }, 500);
    },
    
    /**
     * Initialize copy buttons with delegated events
     */
    initCopyButtons: function() {
        var self = this;
        

        
        // Usar delegated events para compatibilidad con PrestaShop 8
        $(document).off('click.yuju-copy').on('click.yuju-copy', '.yuju-copy-button', function(e) {

            
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var textToCopy = $button.attr('data-copy-text');
            
            if (!textToCopy) {

                alert('Error: No se encontró URL para copiar');
                return false;
            }
            

            self.copyToClipboard(textToCopy, $button);
            
            return false;
        });
        
        // Verificar que los botones existen
        var buttonCount = $('.yuju-copy-button').length;

        
        if (buttonCount > 0) {
    
            
            // Listar cada botón para verificar
            $('.yuju-copy-button').each(function(index) {
                var copyText = $(this).attr('data-copy-text');
    
            });
        } else {
        }
        
        // Función de prueba global
        window.testYujuCopy = function(index) {
            var buttons = $('.yuju-copy-button');
            if (buttons.length > (index || 0)) {
        
                buttons.eq(index || 0).trigger('click');
            } else {
    
            }
        };
        

    },

    /**
     * Validate form
     */
    validateForm: function(form) {
        var isValid = true;
        var $form = $(form);
        
        // Clear previous errors
        $form.find('.has-error').removeClass('has-error');
        $form.find('.error-message').remove();
        
        // Validate required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            if (!$field.val().trim()) {
                $field.closest('.form-group').addClass('has-error');
                $field.after('<div class="error-message text-danger">This field is required</div>');
                isValid = false;
            }
        });
        
        // Validate URLs
        $form.find('input[type="url"]').each(function() {
            var $field = $(this);
            var url = $field.val().trim();
            if (url && !this.isValidUrl(url)) {
                $field.closest('.form-group').addClass('has-error');
                $field.after('<div class="error-message text-danger">Please enter a valid URL</div>');
                isValid = false;
            }
        });
        
        return isValid;
    },

    /**
     * Check if URL is valid
     */
    isValidUrl: function(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    },

    /**
     * Auto-save configuration
     */
    autoSaveConfig: function() {
        var self = this;
        var $form = $('#yuju-config-form');
        
        if (!this.validateForm($form[0])) {
            return;
        }
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=autoSave&ajax=true&token=' + this.config.token,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    self.showAlert('Configuration saved automatically', 'success', 2000);
                }
            }
        });
    },

    /**
     * Show alert message
     */
    showAlert: function(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;
        
        var alertHtml = '<div class="alert yuju-alert ' + type + ' alert-dismissible fade in">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message +
            '</div>';
        
        var $alert = $(alertHtml);
        $('#yuju-alerts').prepend($alert);
        
        // Auto-hide after duration
        setTimeout(function() {
            $alert.fadeOut(function() {
                $(this).remove();
            });
        }, duration);
    },

    /**
     * Utility function to debounce function calls
     */
    debounce: function(func, wait) {
        var timeout;
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var later = function() {
                timeout = null;
                func.apply(context, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Inicializar pruebas de diagnóstico
     */
    initDiagnosticTests: function() {

        
        var self = this;
        
        // Función para escribir en la consola de pruebas
        function writeToConsole(message, type) {
            type = type || 'info';
            var timestamp = new Date().toLocaleTimeString();
            var prefix = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
            var fullMessage = '[' + timestamp + '] ' + prefix + ' ' + message;
            
            var console = $('#test-console');
            var currentText = console.val();
            console.val(currentText + fullMessage + '\n');
            console.scrollTop(console[0].scrollHeight);
        }
        
        // Función para mostrar resultado en div específico
        function showResult(elementId, message, type) {
            var element = $('#' + elementId);
            element.removeClass('success error info').addClass(type);
            element.html(message);
        }
        
        // Prueba 1: Verificar jQuery
        $('#test-jquery').on('click', function() {
            writeToConsole('Iniciando prueba de jQuery...');
            
            try {
                if (typeof $ !== 'undefined' && typeof jQuery !== 'undefined') {
                    var version = $.fn.jquery || 'desconocida';
                    var message = 'jQuery disponible (versión: ' + version + ')';
                    showResult('jquery-result', message, 'success');
                    writeToConsole(message, 'success');
                } else {
                    var message = 'jQuery NO está disponible';
                    showResult('jquery-result', message, 'error');
                    writeToConsole(message, 'error');
                }
            } catch (e) {
                var message = 'Error al verificar jQuery: ' + e.message;
                showResult('jquery-result', message, 'error');
                writeToConsole(message, 'error');
            }
        });
        
        // Prueba 2: Verificar YujuAdmin
        $('#test-yuju-admin').on('click', function() {
            writeToConsole('Iniciando prueba de YujuAdmin...');
            
            try {
                if (typeof YujuAdmin !== 'undefined') {
                    var methods = Object.keys(YujuAdmin);
                    var message = 'YujuAdmin disponible con ' + methods.length + ' métodos: ' + methods.slice(0, 5).join(', ');
                    showResult('yuju-admin-result', message, 'success');
                    writeToConsole(message, 'success');
                } else {
                    var message = 'YujuAdmin NO está disponible';
                    showResult('yuju-admin-result', message, 'error');
                    writeToConsole(message, 'error');
                }
            } catch (e) {
                var message = 'Error al verificar YujuAdmin: ' + e.message;
                showResult('yuju-admin-result', message, 'error');
                writeToConsole(message, 'error');
            }
        });
        
        // Prueba 3: Clipboard API
        $('#test-clipboard-api').on('click', function() {
            writeToConsole('Iniciando prueba de Clipboard API...');
            
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText('Prueba de Clipboard API').then(function() {
                        var message = 'Clipboard API disponible y funcionando';
                        showResult('clipboard-api-result', message, 'success');
                        writeToConsole(message, 'success');
                    }).catch(function(err) {
                        var message = 'Clipboard API disponible pero falló: ' + err.message;
                        showResult('clipboard-api-result', message, 'error');
                        writeToConsole(message, 'error');
                    });
                } else {
                    var message = 'Clipboard API NO está disponible (se usará fallback)';
                    showResult('clipboard-api-result', message, 'info');
                    writeToConsole(message, 'info');
                }
            } catch (e) {
                var message = 'Error al verificar Clipboard API: ' + e.message;
                showResult('clipboard-api-result', message, 'error');
                writeToConsole(message, 'error');
            }
        });
        
        // Prueba 4: Fallback Copy
        $('#test-fallback-copy').on('click', function() {
            writeToConsole('Iniciando prueba de Fallback Copy...');
            
            try {
                var testText = 'Prueba de fallback copy - ' + new Date().getTime();
                var result = self.fallbackCopyToClipboard(testText);
                
                if (result) {
                    var message = 'Fallback copy funcionando correctamente';
                    showResult('fallback-copy-result', message, 'success');
                    writeToConsole(message, 'success');
                } else {
                    var message = 'Fallback copy falló';
                    showResult('fallback-copy-result', message, 'error');
                    writeToConsole(message, 'error');
                }
            } catch (e) {
                var message = 'Error en fallback copy: ' + e.message;
                showResult('fallback-copy-result', message, 'error');
                writeToConsole(message, 'error');
            }
        });
        
        // Limpiar consola
        $('#clear-console').on('click', function() {
            $('#test-console').val('');
            $('.test-result').removeClass('success error info').html('');
            writeToConsole('Consola limpiada');
        });
        
        writeToConsole('Sistema de diagnóstico inicializado correctamente', 'success');
    }
};

// Initialize when document is ready - VERSIÓN CORREGIDA
$(document).ready(function() {

    
    // Detectar página Yuju de múltiples formas
    var isYujuPage = $('.yuju-module').length > 0 || 
                     $('.yuju-copy-button').length > 0 ||
                     window.location.href.indexOf('AdminYuju') !== -1 ||
                     $('body').hasClass('adminyujuconfiguration');
    
    if (isYujuPage) {
        // Configuración para inicializar YujuAdmin
        var config = {
            ajaxUrl: window.currentIndex || '',
            token: window.token || ''
        };
        
        // Buscar configuración específica si existe
        if (typeof yujuAdminConfig !== 'undefined') {
            config = Object.assign(config, yujuAdminConfig);
        }
        
        // Inicializar YujuAdmin
        YujuAdmin.init(config);
        

    } else {
        // Aún así inicializar los botones de copiar para cualquier página que los tenga
        YujuAdmin.initCopyButtons();
    }
    
    // Form validation específica para configuración
    $('#configuration_form').on('submit', function(e) {
        var clientId = $('input[name="YUJU_CLIENT_ID"]').val();
        var clientSecret = $('input[name="YUJU_CLIENT_SECRET"]').val();
        
        if (!clientId || !clientSecret) {
            e.preventDefault();
            alert('Por favor complete tanto el ID de Cliente como el Secreto de Cliente');
            return false;
        }
    });
    
    // Global form validation
    $('form').on('submit', function() {
        return YujuAdmin.validateForm(this);
    });
    
    // Auto-save configuration changes
    $('input[name^="YUJU_"], select[name^="YUJU_"]').on('change', function() {
        YujuAdmin.autoSaveConfig();
    });
    
    // Función de prueba global
    window.testYujuCopy = function(index) {
        var buttons = $('.yuju-copy-button');
        if (buttons.length > (index || 0)) {
            var $button = buttons.eq(index || 0);
            var text = $button.attr('data-copy-text');
            $button.trigger('click');
        }
    };
    
    // Inicializar botones de diagnóstico si existen
    if ($('#test-jquery').length > 0) {
        YujuAdmin.initDiagnosticTests();
    }
});

// Export for global access
window.YujuAdmin = YujuAdmin;