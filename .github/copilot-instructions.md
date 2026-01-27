# GitHub Copilot Instructions - Sarh Al-Itqan (صرح الإتقان)

## Project Overview

**Sarh Al-Itqan** (صرح الإتقان) is a comprehensive field operations attendance management system designed for Arabic-speaking organizations. It provides intelligent attendance tracking with GPS geofencing, role-based access control, PWA support, and advanced analytics.

### Quick Facts
- **Version:** 1.1.0
- **Primary Language:** Arabic (with bilingual support)
- **Stack:** PHP 8.x, MySQL/MariaDB, Bootstrap 5.3.2 RTL, Vanilla JavaScript
- **Architecture:** Traditional MVC pattern (no framework)
- **Timezone:** Asia/Riyadh (UTC+3)
- **Encoding:** UTF-8 (utf8mb4_unicode_520_ci)

## Technology Stack

### Backend
- **PHP 8.x+** with modern features (type hints, strict types)
- **PDO** with prepared statements for database operations
- **Singleton pattern** for database connections
- **Session-based authentication** with secure session handling
- **bcrypt** password hashing (cost factor: 12)

### Database
- **MySQL/MariaDB** with utf8mb4 character set
- **Database Name:** u307296675_101 (Production on Hostinger)
- **Timezone:** Asia/Riyadh
- **19 Tables** including: users, branches, attendance, leaves, notifications, etc.
- All queries use **prepared statements** (SQL injection protection)

### Frontend
- **Bootstrap 5.3.2 RTL** for right-to-left layout
- **Bootstrap Icons 1.11.2** and **Font Awesome 6.5.1**
- **Vanilla JavaScript** (no jQuery) with Fetch API
- **SweetAlert2** for elegant alerts
- **Progressive Web App (PWA)** with Service Workers

### Security
- **CSRF tokens** on all forms
- **Prepared statements** for SQL injection prevention
- **XSS protection** with `htmlspecialchars()` wrapper function `e()`
- **HTTPS enforcement** in production
- **Session security** with IP tracking and regeneration
- **Activity logging** for audit trails
- **Role-Based Access Control (RBAC)** with 5 levels

## Repository Structure

```
sarh.103/
├── config/                 # Core configuration
│   ├── app.php            # Application settings, constants
│   └── database.php       # Database Singleton class + helpers
├── includes/              # Shared components
│   ├── functions.php      # Core business logic functions
│   ├── header.php         # HTML template header
│   ├── footer.php         # HTML template footer
│   └── analytics.php      # Advanced analytics engine
├── api/                   # RESTful API endpoints
│   ├── attendance/        # Attendance operations
│   ├── chat/              # Real-time chat
│   ├── notifications/     # Push notifications
│   └── leaves/            # Leave requests
├── admin/                 # Admin-only pages
├── dashboard/             # Dashboard views
├── assets/                # Static resources
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript modules
│   ├── images/           # Images and icons
│   └── audio/            # Notification sounds
├── developer/             # Developer tools
│   ├── db-manager.php    # Database management
│   ├── backup.php        # Backup utilities
│   └── log-viewer.php    # Log analysis
├── install/               # Installation scripts
├── cron/                  # Scheduled tasks
└── errors/                # Error pages (403, 404, 500)
```

## Coding Standards

### PHP Code Style

1. **File Headers**
```php
<?php
/**
 * Sarh Al-Itqan - [Component Name]
 * [Brief description]
 * 
 * @version 1.1.0
 * @author [Author Name]
 */

// Require dependencies at the top
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';
```

2. **Function Naming**
- Use `snake_case` for function names (consistent with existing code)
- Prefix database functions with `db_` (e.g., `db_get_user()`)
- Use descriptive names (e.g., `check_attendance_eligibility()`)

3. **Database Operations**
```php
// ALWAYS use prepared statements
function db_get_user($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Use transactions for multi-step operations
function create_attendance_with_log($data) {
    global $db;
    try {
        $db->beginTransaction();
        
        // Insert attendance
        $stmt = $db->prepare("INSERT INTO attendance (user_id, check_in_time) VALUES (?, NOW())");
        $stmt->execute([$data['user_id']]);
        $attendance_id = $db->lastInsertId();
        
        // Log activity
        log_activity('attendance_created', $attendance_id, 'Checked in');
        
        $db->commit();
        return $attendance_id;
    } catch (Exception $e) {
        $db->rollBack();
        error_log($e->getMessage());
        return false;
    }
}
```

4. **Security Best Practices**
```php
// ALWAYS escape output
echo e($user['full_name_ar']); // NOT: echo $user['full_name_ar'];

// ALWAYS validate CSRF tokens on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die_with_error('Invalid CSRF token');
    }
}

// ALWAYS check authentication
require_login(); // At the top of protected pages

// ALWAYS check permissions
if (!has_permission('manage_users')) {
    redirect_to('/errors/403.php');
}
```

### JavaScript Code Style

1. **Modern JavaScript**
```javascript
// Use const/let (not var)
const apiUrl = '/api/attendance/action.php';
let currentPage = 1;

// Use async/await for API calls
async function fetchAttendanceData() {
    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'get_records' })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching attendance:', error);
        showError('فشل في تحميل البيانات'); // Arabic error message
        return null;
    }
}
```

2. **UI Feedback**
```javascript
// Use SweetAlert2 for user feedback
function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'نجح!',
        text: message,
        confirmButtonText: 'حسناً'
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'خطأ!',
        text: message,
        confirmButtonText: 'حسناً'
    });
}
```

### SQL Standards

1. **Table Naming**
- Use plural nouns: `users`, `branches`, `attendance_records`
- Use underscores for multi-word names: `leave_requests`, `activity_logs`

2. **Column Naming**
- Use `snake_case`: `full_name_ar`, `created_at`
- Timestamps: `created_at`, `updated_at`, `deleted_at` (soft deletes)
- Foreign keys: `user_id`, `branch_id` (singular + _id)
- Boolean fields: `is_active`, `is_approved`, `has_permission`

3. **Query Patterns**
```sql
-- ALWAYS exclude soft-deleted records
SELECT * FROM users WHERE id = ? AND deleted_at IS NULL;

-- Use JOINs efficiently
SELECT u.*, b.branch_name_ar, r.role_name_ar
FROM users u
INNER JOIN branches b ON u.branch_id = b.id
INNER JOIN roles r ON u.role_id = r.id
WHERE u.deleted_at IS NULL
AND b.deleted_at IS NULL;

-- Use indexes for frequently queried columns
-- Indexes exist on: id, email, branch_id, role_id, created_at
```

## Arabic Language Support

### HTML Structure
```html
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صرح الإتقان - [Page Title]</title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
```

### Bilingual Fields
- Store both Arabic and English versions where applicable
- Primary display: Arabic (`_ar` fields)
- Fallback: English (`_en` fields)
- Example: `full_name_ar`, `full_name_en`

### Date/Time Formatting
```php
// Use Saudi Arabia timezone
date_default_timezone_set('Asia/Riyadh');

// Arabic date formatting
function format_arabic_date($date) {
    $arabic_months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $day = date('j', $timestamp);
    $month = $arabic_months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "{$day} {$month} {$year}";
}
```

### Text Direction
- Main content: RTL (right-to-left)
- Exceptions: Numbers, English text, code snippets remain LTR
- Use `dir="ltr"` for specific elements when needed

## Architecture Patterns

### MVC Without Framework

**Model (Data Layer):**
- Database class in `/config/database.php`
- Business logic functions in `/includes/functions.php`
- Naming: `db_get_*()`, `db_create_*()`, `db_update_*()`, `db_delete_*()`

**View (Presentation Layer):**
- PHP files with HTML templates
- Use `/includes/header.php` and `/includes/footer.php`
- Inline PHP for dynamic content: `<?= e($variable) ?>`

**Controller (Logic Layer):**
- Page-level logic in main `.php` files
- API endpoints in `/api/` directory
- Form processing with validation

### Singleton Pattern (Database)
```php
// Database connection is a singleton
$db = Database::getInstance()->getConnection();

// Don't create new PDO instances
// WRONG: $pdo = new PDO(...);
// RIGHT: global $db; or use Database::getInstance()
```

### Authentication Flow
```
1. User visits page
2. require_login() checks session
3. If not logged in → redirect to /login.php
4. If logged in → load user data with get_current_user()
5. Check permissions with has_permission()
6. Render page content
```

### API Response Format
```json
{
    "success": true,
    "message": "تم الحفظ بنجاح",
    "data": {
        "id": 123,
        "created_at": "2026-01-27 15:30:00"
    }
}
```

## Security Guidelines

### Input Validation
```php
// Sanitize user input
$email = clean_input($_POST['email']);
$name = clean_input($_POST['name']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die_with_error('البريد الإلكتروني غير صالح');
}

// Validate required fields
$required = ['email', 'password', 'full_name_ar'];
$field_labels = [
    'email' => 'البريد الإلكتروني',
    'password' => 'كلمة المرور',
    'full_name_ar' => 'الاسم الكامل'
];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $label = $field_labels[$field] ?? $field;
        die_with_error("{$label} مطلوب");
    }
}
```

### Password Handling
```php
// Hash passwords with bcrypt (cost 12)
$hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verify passwords
if (password_verify($input_password, $stored_hash)) {
    // Login successful
}

// Never log or display passwords
// Never store passwords in plain text
```

### Session Security
```php
// Session is configured in /config/app.php
// - httponly: true
// - secure: true (HTTPS)
// - samesite: Strict

// Regenerate session on login
session_regenerate_id(true);

// Store user IP for validation
$_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];

// Check session validity (optional - may cause issues with VPNs/load balancers)
// Note: IP validation can cause legitimate users to be logged out when IP changes
// Consider using user agent fingerprinting or disabling for mobile users
if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    // Log suspicious activity instead of immediate logout
    log_activity('session_ip_mismatch', 0, 'IP changed from ' . $_SESSION['user_ip'] . ' to ' . $_SERVER['REMOTE_ADDR']);
    // Optionally: require re-authentication for sensitive operations
}
```

### File Upload Security
```php
// Validate file types (use server-side validation, not client MIME type)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    die_with_error('نوع الملف غير مسموح به');
}

// Additional validation: check actual file content
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime_type, $allowed_mimes)) {
    die_with_error('نوع الملف غير صالح');
}

// Validate file size (max 5MB)
if ($_FILES['avatar']['size'] > 5 * 1024 * 1024) {
    die_with_error('حجم الملف كبير جداً');
}

// Generate unique filename
$extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $extension;

// IMPORTANT: Store uploads with proper security
// Option 1: Store outside web root (recommended)
$upload_dir = dirname(__DIR__) . '/uploads/'; // Outside public directory

// Option 2: If storing in public directory, ensure .htaccess prevents execution
// Add to /uploads/.htaccess:
// php_flag engine off
// AddType application/octet-stream .php .phtml .php3 .php4 .php5

move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $filename);
```

## Progressive Web App (PWA)

### Service Worker
- File: `/service-worker.js`
- Caches: pages, CSS, JS, images for offline access
- Strategy: Cache-first for assets, Network-first for data

### Web Push Notifications
```javascript
// Request permission
if ('Notification' in window && 'serviceWorker' in navigator) {
    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
        // Subscribe to push notifications
        // Note: Replace with your actual VAPID public key from the config
        // Generate VAPID keys: https://www.npmjs.com/package/web-push#command-line
        const vapidPublicKey = 'YOUR_VAPID_PUBLIC_KEY'; // TODO: Get from server config
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: vapidPublicKey
        });
    }
}
```

### Manifest
- File: `/manifest.json`
- Name: "صرح الإتقان"
- Icons: Various sizes (192x192, 512x512)
- Theme color: Per brand guidelines

## Geofencing & GPS

### Attendance Validation
```php
// Check if user is within branch geofence
function is_within_geofence($user_lat, $user_lng, $branch_lat, $branch_lng, $radius_meters) {
    $earth_radius = 6371000; // meters
    
    $lat_diff = deg2rad($branch_lat - $user_lat);
    $lng_diff = deg2rad($branch_lng - $user_lng);
    
    $a = sin($lat_diff / 2) * sin($lat_diff / 2) +
         cos(deg2rad($user_lat)) * cos(deg2rad($branch_lat)) *
         sin($lng_diff / 2) * sin($lng_diff / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earth_radius * $c;
    
    return $distance <= $radius_meters;
}
```

### GPS Requirements
- Minimum accuracy: 50 meters
- Location permission required
- Fallback: Manual address entry for approval

## Testing Guidelines

### Manual Testing Checklist

**Authentication:**
- [ ] Login with valid credentials
- [ ] Login with invalid credentials
- [ ] Session persistence
- [ ] Logout functionality
- [ ] Password reset flow

**Attendance:**
- [ ] Check-in within geofence
- [ ] Check-in outside geofence (should fail)
- [ ] Check-out functionality
- [ ] Early/late detection
- [ ] Points calculation

**Permissions:**
- [ ] Admin can access all pages
- [ ] Manager cannot access admin pages
- [ ] Employee can only access own data
- [ ] Unauthorized access redirects to 403

**Arabic Support:**
- [ ] RTL layout displays correctly
- [ ] Arabic text renders properly
- [ ] Dates format in Arabic
- [ ] Form validation messages in Arabic

### Database Testing
```sql
-- Test data integrity
SELECT COUNT(*) FROM users WHERE email = '' OR email IS NULL;
SELECT COUNT(*) FROM attendance WHERE check_in_time > check_out_time;
SELECT COUNT(*) FROM users WHERE branch_id NOT IN (SELECT id FROM branches);

-- Performance testing
EXPLAIN SELECT * FROM attendance WHERE user_id = ? AND DATE(check_in_time) = CURDATE();
```

## Common Patterns

### Page Template
```php
<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

require_login(); // Ensure user is authenticated

$user = get_current_user();
$page_title = 'عنوان الصفحة';

include __DIR__ . '/includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1><?= e($page_title) ?></h1>
            
            <!-- Content here -->
            
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
```

### API Endpoint Template
```php
<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Require authentication
require_login();

// Verify CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!verify_csrf_token($data['csrf_token'] ?? '')) {
        json_response(false, 'رمز CSRF غير صالح');
        exit;
    }
}

// Handle actions
$action = $_GET['action'] ?? $data['action'] ?? '';

switch ($action) {
    case 'get_records':
        // Implementation
        break;
        
    case 'create':
        // Implementation
        break;
        
    default:
        json_response(false, 'إجراء غير معروف');
}
```

### Form with CSRF Protection
```php
<form method="POST" action="/api/users/create.php">
    <?= csrf_token_field() ?>
    
    <div class="mb-3">
        <label for="full_name_ar" class="form-label">الاسم الكامل</label>
        <input type="text" class="form-control" id="full_name_ar" name="full_name_ar" required>
    </div>
    
    <button type="submit" class="btn btn-primary">حفظ</button>
</form>
```

## Performance Optimization

### Database Indexing
- Index on frequently queried columns (id, email, branch_id, created_at)
- Composite indexes for multi-column WHERE clauses
- Monitor slow query log

### Caching Strategy
- Session data cached in memory
- Service Worker caches static assets
- Consider Redis/Memcached for production scaling

### Asset Optimization
- Minify CSS/JS in production
- Use CDN for Bootstrap, Font Awesome
- Lazy load images
- Compress images (WebP format)

## Deployment Notes

### Production Environment
- **Hosting:** Hostinger
- **Domain:** sarh.online
- **SSL:** Required (HTTPS only)
- **PHP Version:** 8.x recommended
- **Database:** MySQL 8.0+ or MariaDB 10.6+

### Environment Variables
```php
// In production, set in hosting panel or .env file
// DO NOT commit sensitive data to git

// IMPORTANT: Fail explicitly if required env vars are missing in production
if (getenv('APP_ENV') === 'production') {
    $required_env = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($required_env as $var) {
        if (!getenv($var)) {
            die("Configuration error: {$var} environment variable is not set");
        }
    }
}

// Use environment variables with safe defaults for development only
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'u307296675_101');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

### Security Checklist Before Deploy
- [ ] Change default admin password
- [ ] Set APP_DEBUG to false
- [ ] Remove /install directory after setup
- [ ] Configure HTTPS/SSL
- [ ] Set secure session settings
- [ ] Configure CORS headers
- [ ] Enable error logging (not display)
- [ ] Set proper file permissions (755 for dirs, 644 for files)
- [ ] Configure backup schedule
- [ ] Test disaster recovery

## Git Workflow

### Commit Messages
```
feat: Add geofencing validation to attendance check-in
fix: Resolve Arabic date formatting issue in reports
docs: Update API documentation for leave requests
refactor: Optimize database query in analytics engine
security: Add rate limiting to login endpoint
```

### Branch Strategy
- `main` - Production-ready code
- `develop` - Integration branch
- `feature/*` - New features
- `fix/*` - Bug fixes
- `hotfix/*` - Urgent production fixes

### Code Review Checklist
- [ ] Code follows PHP standards (snake_case functions)
- [ ] Security: CSRF tokens, prepared statements, XSS protection
- [ ] Arabic support: RTL layout, bilingual fields
- [ ] Error handling: Try-catch, user-friendly messages
- [ ] Performance: Efficient queries, minimal database calls
- [ ] Testing: Manually tested common use cases
- [ ] Documentation: PHPDoc comments for functions

## Important Constants & Configuration

### Application Constants (config/app.php)
```php
define('APP_NAME', 'صرح الإتقان');
define('APP_VERSION', '1.1.0');
define('APP_LANG', 'ar');
define('APP_DIR', 'rtl');
define('APP_TIMEZONE', 'Asia/Riyadh');
define('APP_DEBUG', false); // Set to false in production
```

### Role Levels
1. **Employee (موظف)** - Basic access, own data only
2. **Supervisor (مشرف)** - Team management, reports
3. **Manager (مدير)** - Branch management, approvals
4. **Senior Manager (مدير أول)** - Multi-branch oversight
5. **Admin (مسؤول)** - System-wide access, settings

### Permissions
- `view_own_data` - View personal records
- `view_team_data` - View team members' data
- `manage_users` - Create, edit, delete users
- `manage_attendance` - Modify attendance records
- `manage_settings` - System configuration
- `view_analytics` - Access analytics dashboard

## Troubleshooting

### Common Issues

**Database Connection Errors:**
- Check `/config/database.php` credentials
- Verify database server is running
- Check firewall rules

**Arabic Text Displays as Gibberish:**
- Ensure database charset is utf8mb4
- Verify HTML `<meta charset="UTF-8">`
- Check `header('Content-Type: text/html; charset=utf-8')`

**Geofencing Not Working:**
- Verify HTTPS (GPS requires secure context)
- Check browser location permissions
- Validate branch coordinates in database

**Session Expires Too Quickly:**
- Check `session.gc_maxlifetime` in `php.ini`
- Verify session cookie settings in `/config/app.php`

## Additional Resources

### Internal Documentation
- `/ATTENDANCE_RULES_AND_EXAMPLES.html` - Business rules reference
- `/ATTENDANCE_DARK_SIDE_GUIDE.html` - Edge cases and troubleshooting
- `/README_التقارير.md` - Reports documentation
- `/developer/README_SECURITY.md` - Security best practices

### External Resources
- [PHP 8 Documentation](https://www.php.net/manual/en/)
- [Bootstrap 5 RTL](https://getbootstrap.com/docs/5.3/getting-started/rtl/)
- [MDN Web Docs - PWA](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Web Push Notifications](https://developers.google.com/web/fundamentals/push-notifications)

---

## Quick Reference for Copilot

When working on this project:

✅ **DO:**
- Use prepared statements for ALL database queries
- Escape output with `e()` function
- Verify CSRF tokens on POST requests
- Check authentication with `require_login()`
- Support both Arabic and English where applicable
- Use RTL-friendly CSS classes
- Follow snake_case for PHP functions
- Log important actions with `log_activity()`
- Handle errors gracefully with try-catch
- Write Arabic comments for Arabic developers

❌ **DON'T:**
- Use string concatenation for SQL queries
- Display raw user input without escaping
- Skip CSRF validation on forms
- Hardcode Arabic text without bilingual support
- Use `var` in JavaScript (use const/let)
- Create new database connections (use singleton)
- Commit sensitive data to git
- Remove or modify soft-delete functionality
- Break RTL layout with LTR-specific CSS

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-27  
**Maintained By:** GitHub Copilot Coding Agent
