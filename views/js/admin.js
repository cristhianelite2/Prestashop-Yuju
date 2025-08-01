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
        this.config = Object.assign(this.config, config);
        this.bindEvents();
        this.initTooltips();
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

        // OAuth Authorization button
        $(document).on('click', '#yuju-authorize', function(e) {
            e.preventDefault();
            self.authorizeOAuth();
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

        // Copy to clipboard
        $(document).on('click', '.yuju-copy-button', function(e) {
            e.preventDefault();
            var text = $(this).data('copy-text') || $(this).prev().text();
            self.copyToClipboard(text, $(this));
        });

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
     * Test connectivity and show stores
     */
    testConnectivity: function() {
        var self = this;
        var $button = $('#yuju-test-connectivity');
        var $result = $('#connectivity-result');

        $button.prop('disabled', true).html('<i class="icon-refresh yuju-spin"></i> Probando...');
        $result.hide();

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
                if (response.success) {
                    var html = '<strong>Conexión exitosa!</strong><br>';
                    if (response.data && response.data.stores && response.data.stores.length > 0) {
                        html += '<strong>Tiendas disponibles:</strong><ul>';
                        response.data.stores.forEach(function(store) {
                            html += '<li>' + store.name + ' (ID: ' + store.id + ')</li>';
                        });
                        html += '</ul>';
                    } else {
                        html += 'No se encontraron tiendas disponibles.';
                    }
                    $result.removeClass().addClass('alert alert-success').html(html).show();
                } else {
                    $result.removeClass().addClass('alert alert-danger')
                        .html('<strong>Error de conexión:</strong> ' + (response.message || 'Error desconocido'))
                        .show();
                }
            },
            error: function() {
                $result.removeClass().addClass('alert alert-danger')
                    .html('<strong>Error:</strong> No se pudo conectar con la API de Yuju')
                    .show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="icon-plug"></i> Probar Conectividad');
            }
        });
    },

    /**
     * Authorize OAuth
     */
    authorizeOAuth: function() {
        var self = this;
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'getAuthUrl',
                ajax: true,
                token: this.config.token
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.authUrl) {
                    // Open authorization window
                    var authWindow = window.open(
                        response.authUrl,
                        'yuju_oauth',
                        'width=600,height=700,scrollbars=yes,resizable=yes'
                    );
                    
                    // Check for completion
                    var checkClosed = setInterval(function() {
                        if (authWindow.closed) {
                            clearInterval(checkClosed);
                            // Refresh page to show new auth status
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        }
                    }, 1000);
                } else {
                    self.showAlert('Failed to get authorization URL: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function() {
                self.showAlert('Failed to initiate OAuth authorization', 'error');
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
     * Copy text to clipboard
     */
    copyToClipboard: function(text, $button) {
        // Guardar el contenido HTML original del botón
        var originalHtml = $button.html();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                // Mostrar mensaje de éxito
                $button.addClass('copied').html('<i class="icon-check"></i> ¡Copiado!');
                
                // Restaurar el botón después de 2 segundos
                setTimeout(function() {
                    $button.removeClass('copied').html(originalHtml);
                }, 2000);
            }).catch(function(err) {
                console.error('Error al copiar al portapapeles:', err);
                // Fallback si falla la API moderna
                YujuAdmin.fallbackCopyToClipboard(text, $button, originalHtml);
            });
        } else {
            // Fallback para navegadores antiguos
            YujuAdmin.fallbackCopyToClipboard(text, $button, originalHtml);
        }
    },
    
    /**
     * Fallback method for copying to clipboard
     */
    fallbackCopyToClipboard: function(text, $button, originalHtml) {
        try {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            var successful = document.execCommand('copy');
            $temp.remove();
            
            if (successful) {
                $button.addClass('copied').html('<i class="icon-check"></i> ¡Copiado!');
                setTimeout(function() {
                    $button.removeClass('copied').html(originalHtml);
                }, 2000);
            } else {
                // Si todo falla, mostrar el texto en un alert
                alert('Copie esta URL manualmente: ' + text);
            }
        } catch (err) {
            console.error('Error en fallback de copia:', err);
            alert('Copie esta URL manualmente: ' + text);
        }
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
    }
};

// Initialize when document is ready
$(document).ready(function() {
    // Check if we're on a Yuju admin page
    if ($('.yuju-module').length > 0) {
        // Configuration will be set by the template
        if (typeof yujuAdminConfig !== 'undefined') {
            YujuAdmin.init(yujuAdminConfig);
        }
    }
});

// Export for global access
window.YujuAdmin = YujuAdmin;