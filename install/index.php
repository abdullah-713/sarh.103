<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                     صرح الإتقان - SARH AL-ITQAN                              ║
 * ║                     INSTALLATION WIZARD v1.8.0                               ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// Check if already installed
if (file_exists('../config/database.php')) {
    $configContent = file_get_contents('../config/database.php');
    if (strpos($configContent, 'DB_HOST') !== false && strpos($configContent, 'your_host') === false) {
        header('Location: ../index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت صرح الإتقان</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #00b894;
            --primary-dark: #00997a;
            --secondary: #6c5ce7;
            --dark: #0a0a0f;
            --dark-light: #1a1a2e;
            --text: #e0e0e0;
            --border: rgba(255,255,255,0.1);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            min-height: 100vh;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .install-container {
            max-width: 600px;
            width: 100%;
        }
        .install-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .install-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: #fff;
            box-shadow: 0 10px 40px rgba(0, 184, 148, 0.3);
        }
        .install-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .install-header p {
            color: rgba(255,255,255,0.6);
            font-size: 1.1rem;
        }
        .install-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: var(--primary);
            box-shadow: 0 0 20px rgba(0, 184, 148, 0.5);
        }
        .step-dot.completed {
            background: var(--secondary);
        }
        .step-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .step-content.active { display: block; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #fff;
        }
        .step-desc {
            color: rgba(255,255,255,0.6);
            margin-bottom: 1.5rem;
        }
        .form-label {
            color: rgba(255,255,255,0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            padding: 0.875rem 1rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.15);
            color: #fff;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }
        .env-option {
            background: rgba(255,255,255,0.03);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        .env-option:hover {
            border-color: var(--primary);
            background: rgba(0, 184, 148, 0.05);
        }
        .env-option.selected {
            border-color: var(--primary);
            background: rgba(0, 184, 148, 0.1);
        }
        .env-option i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }
        .env-option h5 { margin-bottom: 0.5rem; }
        .env-option p { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin: 0; }
        .env-tip {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .env-tip i { color: #ffc107; margin-left: 0.5rem; }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 184, 148, 0.3);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }
        .btn-outline-light {
            border-radius: 12px;
            padding: 0.875rem 2rem;
        }
        .btn-test {
            background: rgba(108, 92, 231, 0.2);
            border: 1px solid var(--secondary);
            color: #fff;
            border-radius: 10px;
            padding: 0.625rem 1.25rem;
        }
        .btn-test:hover {
            background: var(--secondary);
            color: #fff;
        }
        .test-result {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        .test-result.success { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .test-result.error { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .progress-container {
            margin-top: 2rem;
            display: none;
        }
        .progress {
            height: 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.5s ease;
        }
        .progress-text {
            text-align: center;
            margin-top: 1rem;
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: #fff;
            animation: successPulse 1s ease;
        }
        @keyframes successPulse {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .input-group-text {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            border-radius: 12px 0 0 12px;
            color: rgba(255,255,255,0.6);
        }
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="install-logo">
                <img src="../assets/images/logo.png" alt="صرح الإتقان" style="width: 80px; height: 80px; object-fit: contain; border-radius: 16px;">
            </div>
            <h1>صرح الإتقان</h1>
            <p>معالج التثبيت الذكي</p>
        </div>

        <div class="install-card">
            <div class="step-indicator">
                <div class="step-dot active" data-step="1"></div>
                <div class="step-dot" data-step="2"></div>
                <div class="step-dot" data-step="3"></div>
                <div class="step-dot" data-step="4"></div>
                <div class="step-dot" data-step="5"></div>
            </div>

            <!-- Step 1: Environment -->
            <div class="step-content active" id="step1">
                <h3 class="step-title"><i class="bi bi-cloud-check me-2"></i>بيئة الاستضافة</h3>
                <p class="step-desc">اختر نوع بيئة الاستضافة الخاصة بك</p>
                
                <div class="row g-3">
                    <div class="col-6">
                        <div class="env-option" data-env="localhost" onclick="selectEnv(this)">
                            <i class="bi bi-pc-display"></i>
                            <h5>محلي (Localhost)</h5>
                            <p>XAMPP, WAMP, Laravel Valet</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="env-option" data-env="hostinger" onclick="selectEnv(this)">
                            <i class="bi bi-cloud"></i>
                            <h5>استضافة (Hostinger)</h5>
                            <p>أو أي استضافة مشتركة</p>
                        </div>
                    </div>
                </div>
                
                <div class="env-tip" id="envTip" style="display:none;"></div>
                
                <div class="d-grid mt-4">
                    <button class="btn btn-primary" onclick="nextStep()" id="step1Next" disabled>
                        التالي <i class="bi bi-arrow-left ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Database -->
            <div class="step-content" id="step2">
                <h3 class="step-title"><i class="bi bi-database me-2"></i>إعدادات قاعدة البيانات</h3>
                <p class="step-desc">أدخل بيانات الاتصال بقاعدة البيانات</p>
                
                <div class="mb-3">
                    <label class="form-label">اسم المضيف (Host)</label>
                    <input type="text" class="form-control" id="dbHost" value="localhost" placeholder="localhost">
                </div>
                <div class="mb-3">
                    <label class="form-label">اسم قاعدة البيانات</label>
                    <input type="text" class="form-control" id="dbName" placeholder="sarh_db">
                </div>
                <div class="mb-3">
                    <label class="form-label">اسم المستخدم</label>
                    <input type="text" class="form-control" id="dbUser" placeholder="root">
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" class="form-control" id="dbPass" placeholder="كلمة المرور">
                </div>
                
                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-test" onclick="testConnection()">
                        <i class="bi bi-plug me-1"></i> اختبار الاتصال
                    </button>
                    <span id="testResult"></span>
                </div>
                
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-outline-light" onclick="prevStep()">
                        <i class="bi bi-arrow-right me-2"></i> السابق
                    </button>
                    <button class="btn btn-primary flex-grow-1" onclick="nextStep()" id="step2Next" disabled>
                        التالي <i class="bi bi-arrow-left ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Branding -->
            <div class="step-content" id="step3">
                <h3 class="step-title"><i class="bi bi-palette me-2"></i>معلومات الشركة</h3>
                <p class="step-desc">أدخل اسم شركتك وشعارها</p>
                
                <div class="mb-3">
                    <label class="form-label">اسم الشركة *</label>
                    <input type="text" class="form-control" id="companyName" placeholder="شركة صرح الإتقان" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">رابط الشعار (اختياري)</label>
                    <input type="url" class="form-control" id="logoUrl" placeholder="https://example.com/logo.png">
                </div>
                
                <hr class="my-4" style="border-color:var(--border)">
                
                <h5 class="mb-3"><i class="bi bi-geo-alt me-2"></i>موقع المقر الرئيسي</h5>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">خط العرض (Latitude)</label>
                        <input type="number" step="any" class="form-control" id="branchLat" placeholder="24.7136">
                    </div>
                    <div class="col-6">
                        <label class="form-label">خط الطول (Longitude)</label>
                        <input type="number" step="any" class="form-control" id="branchLng" placeholder="46.6753">
                    </div>
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    يمكنك الحصول على الإحداثيات من Google Maps
                </small>
                
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-outline-light" onclick="prevStep()">
                        <i class="bi bi-arrow-right me-2"></i> السابق
                    </button>
                    <button class="btn btn-primary flex-grow-1" onclick="nextStep()">
                        التالي <i class="bi bi-arrow-left ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 4: Super Admin -->
            <div class="step-content" id="step4">
                <h3 class="step-title"><i class="bi bi-shield-lock me-2"></i>إنشاء المدير العام</h3>
                <p class="step-desc">أنشئ حساب المدير الأعلى (God Mode)</p>
                
                <div class="mb-3">
                    <label class="form-label">الاسم الكامل *</label>
                    <input type="text" class="form-control" id="adminName" placeholder="أحمد محمد" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">اسم المستخدم *</label>
                    <input type="text" class="form-control" id="adminUsername" placeholder="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني *</label>
                    <input type="email" class="form-control" id="adminEmail" placeholder="admin@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="adminPassword" placeholder="كلمة مرور قوية" required>
                    </div>
                    <small class="text-muted">8 أحرف على الأقل</small>
                </div>
                
                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-outline-light" onclick="prevStep()">
                        <i class="bi bi-arrow-right me-2"></i> السابق
                    </button>
                    <button class="btn btn-primary flex-grow-1" onclick="runInstall()">
                        <i class="bi bi-rocket-takeoff me-2"></i> بدء التثبيت
                    </button>
                </div>
                
                <div class="progress-container" id="progressContainer">
                    <div class="progress">
                        <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                    </div>
                    <p class="progress-text" id="progressText">جاري التحضير...</p>
                </div>
            </div>

            <!-- Step 5: Success -->
            <div class="step-content" id="step5">
                <div class="text-center">
                    <div class="success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3 class="step-title">تم التثبيت بنجاح! 🎉</h3>
                    <p class="step-desc">تم إعداد نظام صرح الإتقان بنجاح</p>
                    
                    <div class="alert alert-warning text-start mt-4" style="background:rgba(255,193,7,0.1);border-color:rgba(255,193,7,0.3);">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>تحذير أمني هام!</h6>
                        <p class="mb-0 small">
                            يجب عليك <strong>حذف مجلد /install</strong> فوراً لأسباب أمنية.
                            وجود هذا المجلد قد يعرض نظامك للخطر.
                        </p>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="../login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-left me-2"></i>
                            الانتقال لصفحة تسجيل الدخول
                        </a>
                        <a href="../MANUAL.md" class="btn btn-outline-light" target="_blank">
                            <i class="bi bi-book me-2"></i>
                            قراءة دليل المستخدم
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <p class="text-center mt-4 text-muted small">
            صرح الإتقان v1.8.0 &copy; <?= date('Y') ?>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    let currentStep = 1;
    let selectedEnv = null;
    let dbTested = false;

    const envTips = {
        localhost: `
            <i class="bi bi-lightbulb"></i>
            <strong>نصيحة للبيئة المحلية:</strong><br>
            • تأكد من تشغيل XAMPP/WAMP<br>
            • أنشئ قاعدة بيانات جديدة من phpMyAdmin<br>
            • المستخدم الافتراضي عادة: root (بدون كلمة مرور)
        `,
        hostinger: `
            <i class="bi bi-lightbulb"></i>
            <strong>نصيحة لـ Hostinger:</strong><br>
            • أنشئ قاعدة البيانات من hPanel أولاً<br>
            • استخدم اسم المستخدم الكامل (مثل: u123456789_sarh)<br>
            • المضيف عادة: localhost أو mysql.hostinger.com
        `
    };

    function selectEnv(el) {
        document.querySelectorAll('.env-option').forEach(e => e.classList.remove('selected'));
        el.classList.add('selected');
        selectedEnv = el.dataset.env;
        document.getElementById('envTip').innerHTML = envTips[selectedEnv];
        document.getElementById('envTip').style.display = 'block';
        document.getElementById('step1Next').disabled = false;
    }

    function updateStepIndicator() {
        document.querySelectorAll('.step-dot').forEach((dot, i) => {
            dot.classList.remove('active', 'completed');
            if (i + 1 === currentStep) dot.classList.add('active');
            else if (i + 1 < currentStep) dot.classList.add('completed');
        });
    }

    function showStep(step) {
        document.querySelectorAll('.step-content').forEach(s => s.classList.remove('active'));
        document.getElementById('step' + step).classList.add('active');
        currentStep = step;
        updateStepIndicator();
    }

    function nextStep() {
        if (currentStep === 1 && !selectedEnv) return;
        if (currentStep === 2 && !dbTested) {
            Swal.fire('تنبيه', 'يرجى اختبار الاتصال بقاعدة البيانات أولاً', 'warning');
            return;
        }
        if (currentStep < 5) showStep(currentStep + 1);
    }

    function prevStep() {
        if (currentStep > 1) showStep(currentStep - 1);
    }

    async function testConnection() {
        const host = document.getElementById('dbHost').value;
        const name = document.getElementById('dbName').value;
        const user = document.getElementById('dbUser').value;
        const pass = document.getElementById('dbPass').value;

        if (!host || !name || !user) {
            Swal.fire('خطأ', 'يرجى ملء جميع الحقول المطلوبة', 'error');
            return;
        }

        document.getElementById('testResult').innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'test_db', host, name, user, pass })
            });
            const data = await res.json();

            if (data.success) {
                document.getElementById('testResult').innerHTML = '<span class="test-result success"><i class="bi bi-check-circle"></i> اتصال ناجح</span>';
                document.getElementById('step2Next').disabled = false;
                dbTested = true;
            } else {
                document.getElementById('testResult').innerHTML = `<span class="test-result error"><i class="bi bi-x-circle"></i> ${data.message}</span>`;
                dbTested = false;
            }
        } catch (e) {
            document.getElementById('testResult').innerHTML = '<span class="test-result error"><i class="bi bi-x-circle"></i> خطأ في الاتصال</span>';
        }
    }

    async function runInstall() {
        const data = {
            action: 'install',
            db: {
                host: document.getElementById('dbHost').value,
                name: document.getElementById('dbName').value,
                user: document.getElementById('dbUser').value,
                pass: document.getElementById('dbPass').value
            },
            company: {
                name: document.getElementById('companyName').value,
                logo: document.getElementById('logoUrl').value
            },
            branch: {
                lat: document.getElementById('branchLat').value || 24.7136,
                lng: document.getElementById('branchLng').value || 46.6753
            },
            admin: {
                name: document.getElementById('adminName').value,
                username: document.getElementById('adminUsername').value,
                email: document.getElementById('adminEmail').value,
                password: document.getElementById('adminPassword').value
            }
        };

        if (!data.company.name || !data.admin.name || !data.admin.username || !data.admin.email || !data.admin.password) {
            Swal.fire('خطأ', 'يرجى ملء جميع الحقول المطلوبة', 'error');
            return;
        }

        if (data.admin.password.length < 8) {
            Swal.fire('خطأ', 'كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'error');
            return;
        }

        document.getElementById('progressContainer').style.display = 'block';
        
        const steps = [
            { text: 'جاري الاتصال بقاعدة البيانات...', progress: 10 },
            { text: 'جاري إنشاء الجداول...', progress: 30 },
            { text: 'جاري إدراج البيانات الأساسية...', progress: 50 },
            { text: 'جاري إنشاء حساب المدير...', progress: 70 },
            { text: 'جاري إنشاء ملف الإعدادات...', progress: 90 }
        ];

        let stepIndex = 0;
        const progressInterval = setInterval(() => {
            if (stepIndex < steps.length) {
                document.getElementById('progressBar').style.width = steps[stepIndex].progress + '%';
                document.getElementById('progressText').textContent = steps[stepIndex].text;
                stepIndex++;
            }
        }, 800);

        try {
            const res = await fetch('handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            clearInterval(progressInterval);

            if (result.success) {
                document.getElementById('progressBar').style.width = '100%';
                document.getElementById('progressText').textContent = 'تم التثبيت بنجاح!';
                
                setTimeout(() => {
                    showStep(5);
                }, 1000);
            } else {
                Swal.fire('خطأ في التثبيت', result.message, 'error');
                document.getElementById('progressContainer').style.display = 'none';
            }
        } catch (e) {
            clearInterval(progressInterval);
            Swal.fire('خطأ', 'حدث خطأ أثناء التثبيت: ' + e.message, 'error');
            document.getElementById('progressContainer').style.display = 'none';
        }
    }
    </script>
</body>
</html>
