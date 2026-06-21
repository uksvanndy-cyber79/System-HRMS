<?php
require_once __DIR__ . '/config/functions.php';

// Redirect if already logged in
if (is_logged_in() && (!isset($_SESSION['locked']) || $_SESSION['locked'] !== true)) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error_msg = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error_msg = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters.';
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error_msg = 'Username already exists.';
        } else {
            // Hash password and insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // Default role is 4 (Employee), default company is 1
            $stmt = $conn->prepare("INSERT INTO `users` (`username`, `email`, `password`, `role_id`, `company_id`, `status`) VALUES (?, ?, ?, 4, 1, 'active')");
            
            if ($stmt->execute([$username, $email, $hashed_password])) {
                $success_msg = 'Registration successful! You can now login.';
                // Log action
                $new_id = $conn->lastInsertId();
                // Temporarily mock user_id in session for audit log, then clear
                $_SESSION['user_id'] = $new_id;
                log_action('register', 'New user registered');
                unset($_SESSION['user_id']);
            } else {
                $error_msg = 'Registration failed. Please try again.';
            }
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
    <title><?php echo __('register') . ' - ' . __('app_name'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="auth-wrapper">

<div class="auth-card p-4">
    <!-- Lang Switcher -->
    <div class="d-flex justify-content-end mb-2">
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

    <!-- Header -->
    <div class="text-center mb-3">
        <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-2" style="width: 50px; height: 50px;">
            <i class="bi bi-person-plus-fill fs-3 text-warning"></i>
        </div>
        <h4 class="fw-bold text-success mb-1"><?php echo __('register'); ?></h4>
        <p class="text-muted small">Create a new system user profile</p>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        
        <div class="mb-2">
            <label for="username" class="form-label fw-bold text-secondary small"><?php echo __('username'); ?></label>
            <input type="text" class="form-control" id="username" name="username" placeholder="e.g. somnang" required>
        </div>

        <div class="mb-2">
            <label for="email" class="form-label fw-bold text-secondary small"><?php echo __('email'); ?></label>
            <input type="email" class="form-control" id="email" name="email" placeholder="e.g. somnang@gmail.com" required>
        </div>

        <div class="mb-2">
            <label for="password" class="form-label fw-bold text-secondary small"><?php echo __('password'); ?></label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 characters" required>
        </div>

        <div class="mb-3">
            <label for="confirm_password" class="form-label fw-bold text-secondary small"><?php echo __('confirm_password'); ?></label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
            <i class="bi bi-check2-circle me-2"></i> <?php echo __('register'); ?>
        </button>

        <div class="text-center">
            <span class="text-muted small"><?php echo __('already_have_account'); ?></span>
            <a href="login.php" class="text-success small text-decoration-none fw-semibold ms-1">
                <?php echo __('sign_in'); ?>
            </a>
        </div>
    </form>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
