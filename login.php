<?php
require_once __DIR__ . '/config/functions.php';

// Redirect if already logged in
if (is_logged_in() && (!isset($_SESSION['locked']) || $_SESSION['locked'] !== true)) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = __('error') . ': Invalid CSRF token';
    } elseif (empty($username) || empty($password)) {
        $error_msg = __('enter_username') . ' / ' . __('enter_password');
    } else {
        // Fetch user from DB
        $stmt = $conn->prepare("SELECT * FROM `users` WHERE `username` = ? AND `status` = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Success login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['locked'] = false;
            
            log_action('login', 'User logged in successfully');
            
            header("Location: dashboard.php");
            exit();
        } else {
            // Log failure
            log_action('failed_login', "Failed login attempt for username: " . htmlspecialchars($username));
            $error_msg = 'Invalid username or password.';
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login') . ' - ' . __('app_name'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="auth-wrapper">

<div class="auth-card p-4">
    <!-- Lang Switcher in Auth Panel -->
    <div class="d-flex justify-content-end mb-3">
        <div class="dropdown">
            <button class="btn btn-light btn-sm dropdown-toggle border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (get_current_lang() == 'kh'): ?>
                    <span class="me-1">🇰🇭</span> ភាសាខ្មែរ
                <?php else: ?>
                    <span class="me-1">🇺🇸</span> English
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="?lang=kh"><span class="me-2">🇰🇭</span> ភាសាខ្មែរ (Khmer)</a></li>
                <li><a class="dropdown-item" href="?lang=en"><span class="me-2">🇺🇸</span> English (English)</a></li>
            </ul>
        </div>
    </div>

    <!-- Header / Brand -->
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-tree-fill fs-2 text-warning"></i>
        </div>
        <h4 class="fw-bold text-success mb-1"><?php echo __('app_name_kh'); ?></h4>
        <p class="text-muted small"><?php echo __('login_subtitle'); ?></p>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="mb-3">
            <label for="username" class="form-label fw-bold text-secondary"><?php echo __('username'); ?></label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" placeholder="admin" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label fw-bold text-secondary"><?php echo __('password'); ?></label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="rememberMe">
                <label class="form-check-label text-muted small" for="rememberMe">
                    <?php echo __('remember_me'); ?>
                </label>
            </div>
            <a href="forget-password.php" class="text-success small text-decoration-none fw-semibold">
                <?php echo __('forget_password'); ?>
            </a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
            <i class="bi bi-box-arrow-in-right me-2"></i> <?php echo __('sign_in'); ?>
        </button>

        <div class="text-center">
            <span class="text-muted small"><?php echo __('dont_have_account'); ?></span>
            <a href="register.php" class="text-success small text-decoration-none fw-semibold ms-1">
                <?php echo __('register'); ?>
            </a>
        </div>
    </form>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
