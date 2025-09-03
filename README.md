# Lightweight Email API

A high-performance email API designed for cPanel deployment that serves random emails without conflicts, supporting >50 req/s.

## Features

- ⚡ **High Performance**: >50 requests/second with SQLite optimization
- 🔒 **Conflict Prevention**: Atomic locking prevents duplicate emails
- ⏱️ **Auto-Release**: 15-second timeout with automatic cleanup
- 🎛️ **Admin Panel**: Web interface for email management
- 📱 **Responsive**: Works on desktop and mobile
- 🚀 **Lightweight**: Minimal tech stack for easy cPanel deployment

## Quick Start

### 1. Upload to cPanel
Upload all files to your `public_html` directory:

```
public_html/
├── api/
│   ├── config.php
│   └── email.php
├── admin/
│   ├── index.html
│   ├── script.js
│   ├── style.css
│   ├── fast_load.php
│   ├── quick_stats.php
│   ├── save_emails.php
│   ├── bulk_operations.php
│   └── export_emails.php
├── data/
│   └── email.txt
├── .htaccess
├── setup.php
├── init.php
├── performance_benchmark.php
├── README.md
├── INSTALL.md
└── PERFORMANCE.md
```

### 2. Create MySQL Database in cPanel
1. **Go to cPanel → MySQL Databases**
2. **Create new database** (e.g., "emailapi")
3. **Create new user** with a strong password
4. **Add user to database** with **ALL PRIVILEGES**
5. **Note down the full names** (usually prefixed with your username)

### 3. Setup Database Configuration
Visit: `https://yourdomain.com/setup.php`

This guided setup will:
- Test your database connection
- Create the required tables and indexes
- Update configuration automatically
- Load sample emails

**Alternative: Manual Configuration**
Edit `api/config.php` and update these constants:
```php
const DB_HOST = 'localhost';
const DB_NAME = 'your_database_name';
const DB_USER = 'your_username';
const DB_PASS = 'your_password';
```

### 4. Initialize & Test
Visit: `https://yourdomain.com/init.php` to verify setup

Then test the API:
```bash
curl https://yourdomain.com/api/email
```

### 5. Security Cleanup
**Important:** Delete `setup.php` after configuration for security:
```bash
rm setup.php
```

### 6. Performance Testing (Optional)
Test system performance with 1M+ emails:
```bash
php performance_benchmark.php
```
This will verify:
- API response times (<50ms target)
- Lock conflict prevention
- Memory usage optimization
- Database query performance

Expected response:
```json
{
  "success": true,
  "email": "test1@example.com",
  "timestamp": 1234567890
}
```

### 5. Access Admin Panel
Visit: `https://yourdomain.com/admin/`

## API Usage

### Get Random Email
```
GET /api/email
```

**Success Response (200):**
```json
{
  "success": true,
  "email": "user@domain.com",
  "timestamp": 1234567890
}
```

**No Emails Available (429):**
```json
{
  "success": false,
  "error": "no_emails_available",
  "message": "No available emails at this time",
  "timestamp": 1234567890
}
```

**Error Response (500):**
```json
{
  "success": false,
  "error": "system_error",
  "message": "Internal server error",
  "timestamp": 1234567890
}
```

## Admin Panel Features

### Statistics Dashboard
- **Total Emails**: Complete email count
- **Available**: Unlocked emails ready for use
- **In Use**: Currently locked emails
- **Auto-refresh**: Updates every 60 seconds

### Core Functions
- **Export All Emails**: Download all emails from database as .txt file
- **Import Emails**: Upload .txt file to append emails to database (no duplicates)
- **Clear Locked Emails**: Unlock all currently locked emails for maintenance

### Key Benefits
- **Simplified Interface**: Clean, focused design without clutter
- **Fast Loading**: Statistics-only display loads instantly
- **Secure Operations**: All functions include validation and error handling
- **Mobile Friendly**: Responsive design works on all devices

## Performance Features

### Database Optimization
- **MySQL**: Robust database with cPanel phpMyAdmin support
- **Indexing**: Optimized queries on lock status and timestamps
- **Transactions**: Atomic operations prevent race conditions
- **InnoDB Engine**: ACID compliance and row-level locking

### Locking Mechanism
- **Row-Level Locking**: Prevents email conflicts
- **15-Second Timeout**: Automatic lock release
- **Background Cleanup**: Expired lock removal
- **Random Selection**: Even distribution

### Caching & Compression
- **Static File Caching**: CSS/JS cached for 1 month
- **Gzip Compression**: Reduced bandwidth usage
- **Optimized Headers**: Performance and security

## Security Features

### File Protection
- **Database Files**: Blocked from web access
- **Email Data**: Protected from direct access
- **Backup Files**: Secured with .htaccess rules
- **Admin Files**: Input validation and sanitization

### Request Security
- **Input Validation**: Email format verification
- **SQL Injection**: Prepared statements only
- **XSS Protection**: Output escaping
- **CSRF Headers**: Security headers enabled

### Rate Limiting
- **Request Size Limits**: 1MB maximum
- **DOS Protection**: Optional mod_evasive integration
- **Error Logging**: Comprehensive error tracking

## Troubleshooting

### Common Issues

**API returns 429 (No emails available)**
- Check if `data/email.txt` has valid emails
- Run `/init.php` to reload database
- Verify file permissions (666 for email.txt)

**Database errors**
- Verify MySQL database credentials in `api/config.php`
- Check database user has proper permissions
- Ensure MySQL database exists and is accessible
- Run `setup.php` for guided configuration

**Admin panel not loading emails**
- Verify PHP files have correct permissions (644)
- Check error logs for PHP errors
- Ensure proper file paths in config

**Performance issues**
- Monitor lock cleanup with admin stats
- Check for database file corruption
- Verify sufficient disk space

### Database Setup Reference
```sql
-- MySQL table structure (auto-created)
CREATE TABLE emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    is_locked TINYINT(1) DEFAULT 0,
    locked_at INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes for performance
CREATE INDEX idx_locked ON emails(is_locked);
CREATE INDEX idx_locked_at ON emails(locked_at);
```

### File Permissions Reference
```bash
# Directories
chmod 755 api/ admin/ data/

# PHP files
chmod 644 api/*.php admin/*.php *.php

# Data files
chmod 666 data/email.txt

# Web files
chmod 644 admin/*.html admin/*.css admin/*.js
chmod 644 .htaccess README.md
```

### Performance Monitoring
- Monitor `/admin/` statistics dashboard
- Check error logs regularly
- Test API response times under load
- Verify email pool size stays adequate

## Technical Specifications

- **Technology**: PHP 7.4+, MySQL 5.7+
- **Dependencies**: PDO MySQL extension (standard on cPanel)
- **Capacity**: Optimized for 1,000,000+ email entries
- **Response Time**: <50ms average (even with 1M+ emails)
- **Throughput**: >50 req/s sustained
- **Lock Timeout**: 15 seconds
- **Cleanup Interval**: Every API request
- **Database Engine**: InnoDB with optimized indexes
- **Memory Usage**: <256MB for large operations
- **File Size**: <20KB total code

## Support

For issues or questions:
1. Check troubleshooting section
2. Verify file permissions
3. Review error logs
4. Test with `/init.php`
