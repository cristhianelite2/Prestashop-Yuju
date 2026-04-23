<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Órdenes de Webhooks - Yuju</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filters label {
            margin-right: 10px;
            font-weight: 600;
        }
        
        .filters select, .filters input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .filters button {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filters button:hover {
            background: #2980b9;
        }
        
        .summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .summary-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .summary-item.success {
            border-left-color: #27ae60;
        }
        
        .summary-item.failed {
            border-left-color: #e74c3c;
        }
        
        .summary-item.pending {
            border-left-color: #f39c12;
        }
        
        .summary-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .orders-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .order-item {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .order-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .order-info-item {
            display: flex;
            align-items: center;
        }
        
        .order-info-label {
            font-weight: 600;
            margin-right: 5px;
            color: #7f8c8d;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #e67e22;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d35400;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .details-section {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .detail-item {
            padding: 10px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }
        
        .detail-item.error {
            border-left-color: #e74c3c;
        }
        
        .detail-item.success {
            border-left-color: #27ae60;
        }
        
        .detail-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #7f8c8d;
        }
        
        .detail-value {
            color: #2c3e50;
        }
        
        .error-message {
            color: #e74c3c;
            margin-top: 5px;
            font-size: 12px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #555;
            line-height: 1.6;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            z-index: 2000;
            display: none;
            max-width: 400px;
        }
        
        .notification.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .notification.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .notification.warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Gestionar Órdenes de Webhooks</h1>
        
        <div class="filters">
            <label>Límite:</label>
            <input type="number" id="limit" value="50" min="1" max="200">
            
            <label>Estado:</label>
            <select id="status">
                <option value="">Todos</option>
                <option value="completed">Completado</option>
                <option value="failed">Fallido</option>
                <option value="pending">Pendiente</option>
            </select>
            
            <button onclick="loadOrders()">🔄 Actualizar</button>
        </div>
        
        <div class="summary" id="summary"></div>
        
        <div class="orders-list" id="orders-list">
            <div class="loading">Cargando órdenes...</div>
        </div>
    </div>
    
    <!-- Modal de confirmación -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirmar Eliminación</div>
            <div class="modal-body" id="deleteModalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeDeleteModal()">Cancelar</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
            </div>
        </div>
    </div>
    
    <!-- Notificación -->
    <div id="notification" class="notification"></div>
    
    <script>
        let ordersData = null;
        
        // Cargar órdenes al inicio
        loadOrders();
        
        function loadOrders() {
            const limit = document.getElementById('limit').value;
            const status = document.getElementById('status').value;
            
            let url = 'check_webhook_orders.php?limit=' + limit;
            if (status) {
                url += '&status=' + status;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        ordersData = data;
                        displaySummary(data.summary);
                        displayOrders(data.orders);
                    } else {
                        showNotification('Error al cargar órdenes: ' + data.error, 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error de conexión: ' + error.message, 'error');
                    console.error('Error:', error);
                });
        }
        
        function displaySummary(summary) {
            const html = `
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Total Webhooks</div>
                        <div class="summary-value">${summary.total_order_webhooks}</div>
                    </div>
                    <div class="summary-item success">
                        <div class="summary-label">Exitosos</div>
                        <div class="summary-value">${summary.successful_creations}</div>
                    </div>
                    <div class="summary-item failed">
                        <div class="summary-label">Fallidos</div>
                        <div class="summary-value">${summary.failed_creations}</div>
                    </div>
                    <div class="summary-item pending">
                        <div class="summary-label">Pendientes</div>
                        <div class="summary-value">${summary.pending_processing}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Tasa de Éxito</div>
                        <div class="summary-value">${summary.success_rate}</div>
                    </div>
                </div>
            `;
            document.getElementById('summary').innerHTML = html;
        }
        
        function displayOrders(orders) {
            if (orders.length === 0) {
                document.getElementById('orders-list').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No hay órdenes con los filtros seleccionados</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            orders.forEach(order => {
                html += renderOrder(order);
            });
            
            document.getElementById('orders-list').innerHTML = html;
        }
        
        function renderOrder(order) {
            let statusClass = 'pending';
            let statusText = 'Pendiente';
            
            if (order.creation_success === true) {
                statusClass = 'success';
                statusText = 'Exitoso';
            } else if (order.creation_success === false) {
                statusClass = 'failed';
                statusText = 'Fallido';
            }
            
            let html = `
                <div class="order-item">
                    <div class="order-header">
                        <div class="order-title">
                            🛒 Webhook: ${order.webhook_id}
                        </div>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    
                    <div class="order-info">
                        <div class="order-info-item">
                            <span class="order-info-label">Yuju Order ID:</span>
                            ${order.yuju_order_id}
                        </div>
                        ${order.prestashop_order_id ? `
                        <div class="order-info-item">
                            <span class="order-info-label">PrestaShop Order ID:</span>
                            ${order.prestashop_order_id}
                        </div>` : ''}
                        <div class="order-info-item">
                            <span class="order-info-label">Recibido:</span>
                            ${order.received_at}
                        </div>
                        <div class="order-info-item">
                            <span class="order-info-label">Topic:</span>
                            ${order.topic}
                        </div>
                    </div>
                    
                    ${order.details ? renderDetails(order.details) : ''}
                    
                    <div class="order-actions">
                        ${order.prestashop_order_id ? `
                            <a href="../../../admin/index.php?controller=AdminOrders&id_order=${order.prestashop_order_id}&vieworder" 
                               class="btn btn-primary" target="_blank">
                                👁️ Ver Orden
                            </a>
                            <button class="btn btn-danger" onclick="confirmDelete(${order.prestashop_order_id}, false)">
                                🗑️ Borrar Orden
                            </button>
                            <button class="btn btn-warning" onclick="confirmDelete(${order.prestashop_order_id}, true)">
                                💣 Borrar Todo
                            </button>
                        ` : `
                            <button class="btn btn-success" onclick="createOrder('${order.webhook_id}', '${order.yuju_order_id}')">
                                ➕ Crear Orden
                            </button>
                        `}
                    </div>
                </div>
            `;
            
            return html;
        }
        
        function renderDetails(details) {
            let html = '<div class="details-section"><strong>Detalles de Creación:</strong><div class="details-grid">';
            
            if (details.customer) {
                const status = details.customer.status || 'UNKNOWN';
                const className = details.customer.error ? 'error' : 'success';
                html += `
                    <div class="detail-item ${className}">
                        <div class="detail-label">Cliente</div>
                        <div class="detail-value">ID: ${details.customer.id || 'N/A'}</div>
                        <div class="detail-value">Estado: ${status}</div>
                        ${details.customer.error ? `<div class="error-message">${details.customer.error}</div>` : ''}
                    </div>
                `;
            }
            
            if (details.address) {
                const status = details.address.status || 'UNKNOWN';
                const className = details.address.error ? 'error' : 'success';
                html += `
                    <div class="detail-item ${className}">
                        <div class="detail-label">Dirección</div>
                        <div class="detail-value">ID: ${details.address.id || 'N/A'}</div>
                        <div class="detail-value">Estado: ${status}</div>
                        ${details.address.error ? `<div class="error-message">${details.address.error}</div>` : ''}
                    </div>
                `;
            }
            
            if (details.cart) {
                const status = details.cart.status || 'UNKNOWN';
                const className = details.cart.error ? 'error' : 'success';
                html += `
                    <div class="detail-item ${className}">
                        <div class="detail-label">Carrito</div>
                        <div class="detail-value">ID: ${details.cart.id || 'N/A'}</div>
                        <div class="detail-value">Estado: ${status}</div>
                        ${details.cart.products_added ? `<div class="detail-value">Productos: ${details.cart.products_added}</div>` : ''}
                        ${details.cart.error ? `<div class="error-message">${details.cart.error}</div>` : ''}
                    </div>
                `;
            }
            
            if (details.order) {
                const status = details.order.status || 'UNKNOWN';
                const className = details.order.error ? 'error' : 'success';
                html += `
                    <div class="detail-item ${className}">
                        <div class="detail-label">Orden</div>
                        <div class="detail-value">ID: ${details.order.id || 'N/A'}</div>
                        <div class="detail-value">Estado: ${status}</div>
                        ${details.order.error ? `<div class="error-message">${details.order.error}</div>` : ''}
                    </div>
                `;
            }
            
            html += '</div></div>';
            return html;
        }
        
        function createOrder(webhookId, orderId) {
            if (!confirm('¿Crear orden desde este webhook?')) return;
            
            showNotification('Creando orden...', 'warning');
            
            fetch('create_order_from_webhook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    webhook_id: webhookId,
                    order_id: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ Orden creada exitosamente: ID ' + data.prestashop_order_id, 'success');
                    setTimeout(loadOrders, 1500);
                } else {
                    showNotification('❌ Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('❌ Error de conexión: ' + error.message, 'error');
            });
        }
        
        function confirmDelete(orderId, deleteAll) {
            const modal = document.getElementById('deleteModal');
            const body = document.getElementById('deleteModalBody');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            if (deleteAll) {
                body.innerHTML = `
                    <p><strong>¿Estás seguro de eliminar COMPLETAMENTE esta orden?</strong></p>
                    <p>Esta acción eliminará:</p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>La orden de PrestaShop (ID: ${orderId})</li>
                        <li>El carrito asociado</li>
                        <li>Las direcciones (si no son usadas por otras órdenes)</li>
                        <li>El cliente (si no tiene otras órdenes)</li>
                    </ul>
                    <p style="color: #e74c3c; font-weight: 600;">Esta acción NO se puede deshacer.</p>
                `;
            } else {
                body.innerHTML = `
                    <p><strong>¿Estás seguro de eliminar esta orden?</strong></p>
                    <p>Se eliminará la orden de PrestaShop (ID: ${orderId})</p>
                    <p>El cliente, direcciones y carrito se preservarán.</p>
                `;
            }
            
            confirmBtn.onclick = () => deleteOrder(orderId, deleteAll);
            modal.style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        function deleteOrder(orderId, deleteAll) {
            closeDeleteModal();
            showNotification('Eliminando orden...', 'warning');
            
            fetch('delete_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    delete_all: deleteAll
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ ' + data.message, 'success');
                    setTimeout(loadOrders, 1500);
                } else {
                    showNotification('❌ Error: ' + data.message + (data.errors.length > 0 ? '<br>' + data.errors.join('<br>') : ''), 'error');
                }
            })
            .catch(error => {
                showNotification('❌ Error de conexión: ' + error.message, 'error');
            });
        }
        
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.innerHTML = message;
            notification.className = 'notification ' + type;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
