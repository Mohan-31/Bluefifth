<?php
// Create this file as: admin/test-shiprocket.php
session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Simple test to check Shiprocket integration
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shiprocket Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Shiprocket Integration Test</h2>
        
        <div class="card">
            <div class="card-body">
                <h5>Test Connection</h5>
                <form id="testForm">
                    <div class="mb-3">
                        <label>Shiprocket Email:</label>
                        <input type="email" class="form-control" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label>Shiprocket Password:</label>
                        <input type="password" class="form-control" id="password" required>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="testConnection()">Test Connection</button>
                </form>
                
                <div id="results" class="mt-4"></div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-body">
                <h5>Current Settings</h5>
                <div id="currentSettings"></div>
                <button class="btn btn-info" onclick="loadCurrentSettings()">Load Current Settings</button>
            </div>
        </div>
    </div>

    <script>
        async function testConnection() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                alert('Please enter both email and password');
                return;
            }
            
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="alert alert-info">Testing connection...</div>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'test_shiprocket_connection');
                formData.append('email', email);
                formData.append('password', password);
                
                const response = await fetch('api/manage-settings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ Connection Successful!</h6>
                            <p><strong>Token:</strong> ${data.token.substring(0, 20)}...</p>
                            <p><strong>Expires:</strong> ${data.token_expiry}</p>
                        </div>
                    `;
                    
                    // Now test courier loading
                    await testCourierLoading();
                    
                } else {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>❌ Connection Failed</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                resultsDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>⚠️ Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        async function testCourierLoading() {
            try {
                const response = await fetch('api/manage-settings.php?action=get_shiprocket_couriers');
                const data = await response.json();
                
                const resultsDiv = document.getElementById('results');
                
                if (data.success) {
                    let html = `
                        <div class="alert alert-success">
                            <h6>📦 Couriers Loaded Successfully</h6>
                            <p>Found ${data.couriers.length} courier partners:</p>
                            <ul>
                    `;
                    
                    data.couriers.slice(0, 5).forEach(courier => {
                        html += `<li>${courier.courier_name} (${courier.courier_type})</li>`;
                    });
                    
                    if (data.couriers.length > 5) {
                        html += `<li>... and ${data.couriers.length - 5} more</li>`;
                    }
                    
                    html += '</ul></div>';
                    
                    resultsDiv.innerHTML += html;
                    
                } else {
                    resultsDiv.innerHTML += `
                        <div class="alert alert-warning">
                            <h6>⚠️ Courier Loading Failed</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                resultsDiv.innerHTML += `
                    <div class="alert alert-danger">
                        <h6>⚠️ Courier Loading Error</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        async function loadCurrentSettings() {
            try {
                const response = await fetch('api/manage-settings.php?action=get_all_settings');
                const data = await response.json();
                
                if (data.success) {
                    const settings = data.settings;
                    const settingsDiv = document.getElementById('currentSettings');
                    
                    let html = '<div class="table-responsive"><table class="table table-sm">';
                    html += '<thead><tr><th>Setting</th><th>Value</th></tr></thead><tbody>';
                    
                    const shiprocketSettings = [
                        'shiprocket_enabled',
                        'shiprocket_email', 
                        'shiprocket_password',
                        'shiprocket_api_token',
                        'shiprocket_token_expiry'
                    ];
                    
                    shiprocketSettings.forEach(key => {
                        let value = settings[key] || 'Not set';
                        if (key.includes('password') || key.includes('token')) {
                            value = value ? '***HIDDEN***' : 'Not set';
                        }
                        html += `<tr><td>${key}</td><td>${value}</td></tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    settingsDiv.innerHTML = html;
                }
                
            } catch (error) {
                document.getElementById('currentSettings').innerHTML = 
                    `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }
        
        // Load current settings on page load
        document.addEventListener('DOMContentLoaded', loadCurrentSettings);
    </script>
</body>
</html>