// Simplified Admin Panel JavaScript - Statistics & Actions Only
document.addEventListener('DOMContentLoaded', function() {
    // Load stats on page load
    loadStats();
    
    // Auto-refresh stats every 60 seconds
    setInterval(loadStats, 60000);
});

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
            loadStats(); // Update stats
        } else {
            showStatus('Failed to clear locks: ' + data.message, 'error');
        }
    } catch (error) {
        showStatus('Error clearing locks: ' + error.message, 'error');
    }
}



function exportEmails() {
    try {
        showStatus('Preparing export...', 'success');
        
        // Direct download all emails as txt file
        window.open('export_emails.php', '_blank');
        
        showStatus('Export started - check your downloads', 'success');
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



function showStatus(message, type) {
    const statusElement = document.getElementById('save-status');
    statusElement.textContent = message;
    statusElement.className = type;
    
    setTimeout(() => {
        statusElement.textContent = '';
        statusElement.className = '';
    }, 5000);
}
