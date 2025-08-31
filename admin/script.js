// Admin Panel JavaScript - Optimized for 1M+ emails
let currentPage = 1;
let pageSize = 100;
let currentSearch = '';
let selectedEmails = new Set();

// Optimized loading - lazy load emails, immediate stats
document.addEventListener('DOMContentLoaded', function() {
    // Load stats immediately (fast query)
    loadStats();
    
    // Lazy load emails after short delay to prioritize UI responsiveness
    setTimeout(loadEmails, 100);
    
    // Auto-refresh stats every 60 seconds (reduced frequency)
    setInterval(loadStats, 60000);
    
    // Optimized debounced search with longer delay
    let searchTimeout;
    document.getElementById('search-input').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (currentSearch !== this.value) {
                currentSearch = this.value;
                currentPage = 1;
                loadEmails();
            }
        }, 800); // Increased delay to reduce API calls
    });
});

async function loadEmails() {
    try {
        // Show loading state
        const tbody = document.getElementById('email-table-body');
        if (tbody.children.length === 1 && tbody.textContent.includes('Loading')) {
            // Keep loading message for initial load
        } else {
            // Show quick loading for subsequent loads
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 10px;">Updating...</td></tr>';
        }
        
        const params = new URLSearchParams({
            page: currentPage,
            limit: pageSize,
            search: currentSearch
        });
        
        const response = await fetch(`load_emails_paginated.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            displayEmailTable(result.data);
            updatePagination(result.data);
        } else {
            showStatus('Failed to load emails: ' + result.message, 'error');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #f44336;">Failed to load emails</td></tr>';
        }
    } catch (error) {
        showStatus('Error loading emails: ' + error.message, 'error');
        const tbody = document.getElementById('email-table-body');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #f44336;">Connection error</td></tr>';
    }
}

function displayEmailTable(data) {
    const tbody = document.getElementById('email-table-body');
    
    if (data.emails.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5">No emails found</td></tr>';
        return;
    }
    
    // Optimized rendering with pre-formatted data
    const rows = [];
    for (const email of data.emails) {
        const status = email.is_locked ? 'Locked' : 'Available';
        const statusClass = email.is_locked ? 'status-locked' : 'status-available';
        const createdDisplay = email.created_display || new Date(email.created_at).toLocaleDateString();
        const isSelected = selectedEmails.has(parseInt(email.id));
        
        rows.push(`
            <tr>
                <td><input type="checkbox" value="${email.id}" ${isSelected ? 'checked' : ''} onchange="toggleEmailSelection(${email.id}, this.checked)"></td>
                <td title="${escapeHtml(email.email)}">${escapeHtml(email.email.length > 30 ? email.email.substring(0, 30) + '...' : email.email)}</td>
                <td><span class="${statusClass}">${status}</span></td>
                <td>${createdDisplay}</td>
                <td>
                    <button onclick="deleteEmail(${email.id})" class="btn-danger btn-small">×</button>
                </td>
            </tr>
        `);
    }
    
    tbody.innerHTML = rows.join('');
    updateSelectedCount();
}

function updatePagination(data) {
    document.getElementById('page-info').textContent = `Page ${data.page} of ${data.pages} (${data.total} total)`;
    document.getElementById('prev-btn').disabled = data.page <= 1;
    document.getElementById('next-btn').disabled = data.page >= data.pages;
}

function changePage(direction) {
    const newPage = currentPage + direction;
    if (newPage >= 1) {
        currentPage = newPage;
        loadEmails();
    }
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('page-size').value);
    currentPage = 1;
    loadEmails();
}

function toggleEmailSelection(emailId, isSelected) {
    if (isSelected) {
        selectedEmails.add(emailId);
    } else {
        selectedEmails.delete(emailId);
    }
    updateSelectedCount();
}

function toggleAllEmails(checkbox) {
    const emailCheckboxes = document.querySelectorAll('#email-table tbody input[type="checkbox"]');
    emailCheckboxes.forEach(cb => {
        const emailId = parseInt(cb.value);
        cb.checked = checkbox.checked;
        if (checkbox.checked) {
            selectedEmails.add(emailId);
        } else {
            selectedEmails.delete(emailId);
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    document.getElementById('selected-count').textContent = `${selectedEmails.size} selected`;
}

async function loadStats() {
    try {
        // Use quick stats endpoint for faster loading
        const response = await fetch('quick_stats.php');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.stats;
            const approximateText = stats.approximate ? ' (approx)' : '';
            document.getElementById('stats-content').innerHTML = `
                <div class="stat-item stat-total">
                    <div><strong>${stats.total.toLocaleString()}${approximateText}</strong></div>
                    <div>Total Emails</div>
                </div>
                <div class="stat-item stat-available">
                    <div><strong>${stats.available.toLocaleString()}</strong></div>
                    <div>Available</div>
                </div>
                <div class="stat-item stat-locked">
                    <div><strong>${stats.locked.toLocaleString()}</strong></div>
                    <div>In Use</div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

async function addEmail() {
    const emailInput = document.getElementById('new-email');
    const email = emailInput.value.trim();
    
    if (!email || !isValidEmail(email)) {
        showStatus('Please enter a valid email address', 'error');
        return;
    }
    
    try {
        const response = await fetch('bulk_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                operation: 'bulk_add',
                emails: [email]
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatus(`Email added successfully`, 'success');
            emailInput.value = '';
            loadEmails(); // Refresh current page
            loadStats(); // Update stats
        } else {
            showStatus('Failed to add email: ' + data.message, 'error');
        }
    } catch (error) {
        showStatus('Error adding email: ' + error.message, 'error');
    }
}

async function bulkDeleteSelected() {
    if (selectedEmails.size === 0) {
        showStatus('No emails selected', 'error');
        return;
    }
    
    if (!confirm(`Delete ${selectedEmails.size} selected emails? This cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch('bulk_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                operation: 'bulk_delete',
                email_ids: Array.from(selectedEmails)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatus(`Deleted ${data.count} emails successfully`, 'success');
            selectedEmails.clear();
            loadEmails(); // Refresh current page
            loadStats(); // Update stats
        } else {
            showStatus('Failed to delete emails: ' + data.message, 'error');
        }
    } catch (error) {
        showStatus('Error deleting emails: ' + error.message, 'error');
    }
}

async function deleteEmail(emailId) {
    if (!confirm('Delete this email? This cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('bulk_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                operation: 'bulk_delete',
                email_ids: [emailId]
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatus('Email deleted successfully', 'success');
            selectedEmails.delete(emailId);
            loadEmails(); // Refresh current page
            loadStats(); // Update stats
        } else {
            showStatus('Failed to delete email: ' + data.message, 'error');
        }
    } catch (error) {
        showStatus('Error deleting email: ' + error.message, 'error');
    }
}

async function clearLocked() {
    if (!confirm('Clear all locked emails? This will unlock all currently locked emails.')) {
        return;
    }
    
    try {
        const response = await fetch('bulk_operations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                operation: 'clear_locked'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showStatus(`Unlocked ${data.count} emails`, 'success');
            loadEmails(); // Refresh current page
            loadStats(); // Update stats
        } else {
            showStatus('Failed to clear locks: ' + data.message, 'error');
        }
    } catch (error) {
        showStatus('Error clearing locks: ' + error.message, 'error');
    }
}

function searchEmails() {
    currentSearch = document.getElementById('search-input').value.trim();
    currentPage = 1;
    loadEmails();
}

async function exportEmails() {
    try {
        showStatus('Preparing export...', 'success');
        
        // Export all emails (could be large file)
        const response = await fetch('load_emails.php'); // Use old endpoint for full export
        const data = await response.json();
        
        if (data.success) {
            const content = data.emails.join('\n');
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'emails_' + new Date().toISOString().split('T')[0] + '.txt';
            a.click();
            window.URL.revokeObjectURL(url);
            
            showStatus(`Exported ${data.emails.length} emails`, 'success');
        } else {
            showStatus('Failed to export emails', 'error');
        }
    } catch (error) {
        showStatus('Error exporting emails: ' + error.message, 'error');
    }
}

async function importEmails(input) {
    const file = input.files[0];
    if (!file) return;
    
    showStatus('Processing import...', 'success');
    
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const content = e.target.result;
            const emails = content.split('\n')
                .map(e => e.trim())
                .filter(e => e && isValidEmail(e));
            
            if (emails.length === 0) {
                showStatus('No valid emails found in file', 'error');
                return;
            }
            
            // Confirm large imports
            if (emails.length > 10000) {
                if (!confirm(`Import ${emails.length} emails? This may take a while.`)) {
                    return;
                }
            }
            
            const response = await fetch('bulk_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    operation: 'bulk_add',
                    emails: emails
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showStatus(`Imported ${data.count} emails successfully`, 'success');
                loadEmails(); // Refresh current page
                loadStats(); // Update stats
            } else {
                showStatus('Failed to import: ' + data.message, 'error');
            }
        } catch (error) {
            showStatus('Error importing emails: ' + error.message, 'error');
        }
    };
    
    reader.readAsText(file);
    input.value = ''; // Reset file input
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function showStatus(message, type) {
    const statusElement = document.getElementById('save-status');
    statusElement.textContent = message;
    statusElement.className = type;
    
    setTimeout(() => {
        statusElement.textContent = '';
        statusElement.className = '';
    }, 5000);
}
