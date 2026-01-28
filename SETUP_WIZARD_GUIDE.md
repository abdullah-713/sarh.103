# Setup Wizard Guide - دليل معالج الإعداد

## Overview

معالج إعداد احترافي وحديث لنظام "صرح الإتقان" (Sarh Al-Itqan) مع واجهة dark-mode حديثة باستخدام Tailwind CSS.

---

## Features - الميزات

### ✅ Modern UI Design
- Dark-mode tech aesthetic
- Tailwind CSS styling
- Glass morphism effects
- Smooth animations
- RTL (Arabic) support
- Responsive design

### ✅ Step-by-Step Setup
1. **Environment Check** - فحص البيئة
2. **Database Migration** - ترحيل قاعدة البيانات
3. **Branch Configuration** - تكوين الفرع
4. **Developer Account** - إنشاء حساب المطور
5. **Success Screen** - شاشة النجاح

### ✅ Security
- Setup lock file (`setup.lock`)
- Prevents multiple runs
- Secure password hashing

### ✅ Error Handling
- SweetAlert2 for notifications
- Detailed error messages
- Progress indicators

---

## Usage - الاستخدام

### First Time Setup

1. **Access Setup Wizard**
   ```
   http://your-domain.com/setup_wizard.php
   ```

2. **Step 1: Environment Check**
   - Enter database credentials
   - Click "فحص البيئة"
   - System checks:
     - PHP version (>= 7.4)
     - PDO extension
     - PDO MySQL extension
     - JSON extension
     - Database connection

3. **Step 2: Database Migration**
   - Click "تشغيل الترحيل"
   - System runs `migration_complete.sql`
   - Progress bar shows execution
   - Removes GPS data
   - Sets up IP-based tables

4. **Step 3: Branch Configuration**
   - Enter authorized IP for main branch
   - Supports single IP or CIDR notation
   - Example: `192.168.1.100` or `192.168.1.0/24`
   - Click "حفظ الإعدادات"

5. **Step 4: Developer Account**
   - Enter developer details:
     - Full Name: Abdullah Al-Kurdi
     - Username: developer
     - Email: developer@sarh.online
     - Password: Sarh@2026!
   - Click "إنشاء الحساب"
   - Creates account with `role_id = 6` (developer)

6. **Success Screen**
   - Shows completion message
   - Displays account credentials
   - Button to go to Dashboard

### After Setup

Once `setup.lock` file is created:
- Setup wizard is locked
- Shows message: "تم إعداد النظام مسبقاً"
- Link to dashboard provided

---

## File Structure

```
setup_wizard.php          - Main setup wizard file
setup.lock                - Lock file (created after completion)
migration_complete.sql    - Database migration file
```

---

## Database Configuration

The wizard collects database credentials:
- **Host**: Database server (default: localhost)
- **Database Name**: Database name (default: u850419603_101)
- **Username**: Database username
- **Password**: Database password

These are used for all setup operations.

---

## Branch IP Configuration

### Single IP
```
192.168.1.100
```
Allows only this specific IP address.

### CIDR Notation
```
192.168.1.0/24
```
Allows all IPs from 192.168.1.0 to 192.168.1.255.

---

## Developer Account

### Default Values
- **Full Name**: Abdullah Al-Kurdi
- **Username**: developer
- **Email**: developer@sarh.online
- **Password**: Sarh@2026!
- **Role ID**: 6 (developer)
- **Branch ID**: 1 (main branch)

### Role Permissions
Developer role (`role_id = 6`) has:
- Full system access (`*`)
- Developer permissions (`developer.*`)
- System permissions (`system.*`)

---

## Security Features

### Setup Lock
- File: `setup.lock`
- Created after successful setup
- Prevents re-running wizard
- Contains completion timestamp

### Password Security
- Passwords hashed with `password_hash()`
- Uses `PASSWORD_DEFAULT` algorithm
- Secure storage in database

---

## Error Handling

### SweetAlert2 Integration
All errors displayed using SweetAlert2:
- Environment check failures
- Database connection errors
- Migration errors
- Configuration errors
- Account creation errors

### Error Types
1. **Environment Errors**: Missing PHP extensions
2. **Database Errors**: Connection or query failures
3. **Validation Errors**: Invalid input data
4. **Migration Errors**: SQL execution issues

---

## Customization

### Change Default Values

Edit the default values in `setup_wizard.php`:

```php
// Database defaults (lines 36-41)
$db_config = [
    'host' => 'localhost',
    'name' => 'your_database',
    'user' => 'your_user',
    'pass' => 'your_password'
];

// Developer defaults (in HTML)
<input type="text" id="full_name" value="Your Name">
<input type="text" id="username" value="your_username">
```

### Change Colors

Modify Tailwind classes:
- Primary: `from-blue-600 to-purple-600`
- Success: `from-green-600 to-emerald-600`
- Warning: `from-yellow-600 to-orange-600`

---

## Troubleshooting

### Issue: Setup wizard not accessible

**Solution:**
1. Check file permissions
2. Verify PHP is running
3. Check web server configuration

### Issue: Database connection fails

**Solution:**
1. Verify database credentials
2. Check database server is running
3. Verify user permissions
4. Check firewall settings

### Issue: Migration fails

**Solution:**
1. Check `migration_complete.sql` exists
2. Verify file permissions
3. Check database user has ALTER privileges
4. Review error messages in SweetAlert

### Issue: Cannot create developer account

**Solution:**
1. Check username/email not already exists
2. Verify role_id = 6 exists in roles table
3. Check branch_id = 1 exists in branches table
4. Review error messages

---

## Testing

### Manual Test Steps

1. **Delete setup.lock** (if exists)
2. **Access setup_wizard.php**
3. **Complete all steps**:
   - Enter database credentials
   - Run environment check
   - Execute migration
   - Configure branch IP
   - Create developer account
4. **Verify setup.lock** created
5. **Try accessing wizard again** (should be locked)
6. **Login with developer account**

---

## Files Created

- ✅ `setup_wizard.php` - Main wizard file
- ✅ `setup.lock` - Lock file (created after setup)
- ✅ `SETUP_WIZARD_GUIDE.md` - This guide

---

## Summary

✅ **Modern UI**: Dark-mode with Tailwind CSS
✅ **Step-by-Step**: 4 main steps + success screen
✅ **Environment Check**: Automatic verification
✅ **Database Migration**: Runs migration_complete.sql
✅ **Branch Configuration**: IP setup for main branch
✅ **Developer Account**: Creates default developer account
✅ **Security**: Setup lock prevents re-running
✅ **Error Handling**: SweetAlert2 notifications
✅ **RTL Support**: Full Arabic support

**Status**: ✅ **PRODUCTION READY**
