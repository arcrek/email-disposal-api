# 📧 Email API Installation Guide

## Step-by-Step cPanel Installation

### 1. Prepare cPanel Database

#### Create MySQL Database
1. **Login to cPanel**
2. **Go to MySQL Databases**
3. **Create New Database**
   - Database Name: `emailapi` (or your preferred name)
   - Click "Create Database"

#### Create Database User
1. **In MySQL Databases section**
2. **Add New User**
   - Username: `emailapi_user` (or your preferred name)
   - Password: Generate a strong password
   - Click "Create User"

#### Grant Privileges
1. **Add User to Database**
   - User: `emailapi_user`
   - Database: `emailapi`
   - Privileges: **ALL PRIVILEGES**
   - Click "Add"

#### Note Down Credentials
```
Database Host: localhost
Database Name: [username]_emailapi
Database User: [username]_emailapi_user
Database Password: [your_generated_password]
```

### 2. Upload Files via File Manager

#### Upload Project Files
1. **Open cPanel File Manager**
2. **Navigate to public_html**
3. **Upload all project files**:
   ```
   api/
   admin/
   data/
   .htaccess
   setup.php
   init.php
   README.md
   INSTALL.md
   CHANGELOG.md
   project-summary.mdc
   ```

#### Set File Permissions
```bash
# Make directories executable
chmod 755 api/ admin/ data/

# Make PHP files readable
chmod 644 api/*.php admin/*.php *.php

# Make email.txt writable
chmod 666 data/email.txt

# Make static files readable
chmod 644 admin/*.html admin/*.css admin/*.js .htaccess
```

### 3. Configure Database (Easy Method)

#### Use Setup Wizard
1. **Visit**: `https://yourdomain.com/setup.php`
2. **Enter your database credentials**:
   - Host: `localhost`
   - Database Name: `[username]_emailapi`
   - Username: `[username]_emailapi_user`
   - Password: `[your_password]`
3. **Click "Setup Database"**
4. **Wait for success message**

#### Verify Configuration
- Tables created automatically
- Sample emails loaded
- Configuration file updated

### 4. Test Installation

#### Test Database Connection
Visit: `https://yourdomain.com/init.php`

Expected output:
```
=== Email API Database Initialization ===

Testing database connection...
✓ Database connection successful.
✓ Tables and indexes created.
✓ Loaded 20 emails from email.txt.

Database Statistics:
- Total emails: 20
- Available: 20
- Locked: 0

=== Initialization Complete! ===
```

#### Test API Endpoint
Visit: `https://yourdomain.com/api/email`

Expected response:
```json
{
  "success": true,
  "email": "test1@example.com",
  "timestamp": 1234567890
}
```

#### Test Admin Panel
Visit: `https://yourdomain.com/admin/`

Should show:
- Statistics dashboard
- Email management interface
- Import/export functionality

### 5. Security & Cleanup

#### Remove Setup File
```bash
rm setup.php
```

#### Verify File Protection
Check that these URLs return 403 Forbidden:
- `https://yourdomain.com/data/email.txt`
- `https://yourdomain.com/data/`

### 6. Performance Testing

#### Load Test API
```bash
# Install Apache Bench (if available)
ab -n 1000 -c 50 https://yourdomain.com/api/email

# Or use curl for simple test
for i in {1..10}; do
  curl -w "%{time_total}\n" -o /dev/null -s https://yourdomain.com/api/email
done
```

#### Verify No Conflicts
Run multiple concurrent requests and ensure no duplicate emails returned.

### 7. Monitoring Setup

#### Enable Error Logging
Add to your `.htaccess` or PHP configuration:
```php
php_value log_errors on
php_value error_log /path/to/error.log
```

#### Monitor Admin Panel
- Check statistics regularly
- Monitor available email count
- Watch for performance issues

## Alternative: Manual Configuration

### Edit Config File Directly
If setup.php doesn't work, manually edit `api/config.php`:

```php
// Update these constants
const DB_HOST = 'localhost';
const DB_NAME = 'your_username_emailapi';
const DB_USER = 'your_username_emailapi_user';
const DB_PASS = 'your_password';
```

### Create Tables Manually
Run this SQL in phpMyAdmin:

```sql
CREATE TABLE emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    is_locked TINYINT(1) DEFAULT 0,
    locked_at INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_locked ON emails(is_locked);
CREATE INDEX idx_locked_at ON emails(locked_at);
```

## Troubleshooting

### Common Issues

#### "Access Denied" Error
- Check database credentials in `api/config.php`
- Verify user has ALL PRIVILEGES on database
- Ensure database names include your cPanel username prefix

#### "Table doesn't exist" Error
- Run `setup.php` or `init.php` to create tables
- Manually create tables using SQL above
- Check database name is correct

#### "No emails available" (429 Error)
- Check `data/email.txt` has valid emails
- Verify file permissions (666 for email.txt)
- Run admin panel to add emails

#### Admin Panel Not Loading
- Check PHP error logs
- Verify file permissions (644 for PHP files)
- Test database connection

#### Performance Issues
- Check MySQL query performance
- Monitor lock cleanup frequency
- Verify adequate email pool size

### Support Checklist
- [ ] Database created with proper user/privileges
- [ ] All files uploaded with correct permissions
- [ ] Configuration updated with correct credentials
- [ ] Tables created successfully
- [ ] API returns valid JSON responses
- [ ] Admin panel loads and functions
- [ ] No duplicate emails under load testing
- [ ] Error logging enabled and monitored

## Production Recommendations

### Security
- Use strong database passwords
- Delete setup.php after installation
- Monitor error logs regularly
- Keep email pool size > 100 emails

### Performance
- Monitor API response times (<50ms)
- Test under expected load (>50 req/s)
- Keep database optimized
- Use cPanel caching if available

### Maintenance
- Backup email.txt regularly
- Monitor database size
- Check for stuck locks
- Update email pool as needed
