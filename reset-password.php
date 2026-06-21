<?php
require_once __DIR__ . '/config/functions.php';

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
$error_msg = '';
$success_msg = '';

// Simple validation token
$expected_token = md5($email . 'secure_salt');

if (empty($email) || empty($token) || $token !== $expected_token) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error_msg = 'Please fill out all fields.';
    } elseif ($password !== $confirm_password) {
        $error_msg = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters.';
    } else {
        // Hash and update
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `users` SET `password` = ? WHERE `email` = ?");
        
        if ($stmt->execute([$hashed_password, $email])) {
            $success_msg = 'Password reset successfully! You can now log in.';
            
            // Log action
            $u_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $u_stmt->execute([$email]);
            $u_id = $u_stmt->fetchColumn();
            if ($u_id) {
                $_SESSION['user_id'] = $u_id;
                log_action('reset_password', 'Password reset successfully');
                unset($_SESSION['user_id']);
            }
        } else {
            $error_msg = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('reset_password') . ' - ' . __('app_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="auth-wrapper">

<div class="auth-card p-4">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-danger text-white rounded-circle mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-shield-lock-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-success mb-1"><?php echo __('reset_password'); ?></h4>
        <p class="text-muted small">Update your password for: <strong><?php echo htmlspecialchars($email); ?></strong></p>
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
        <div class="text-center">
            <a href="login.php" class="btn btn-primary w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i> <?php echo __('sign_in'); ?>
            </a>
        </div>
    <?php else: ?>
        <form action="" method="POST">
            <div class="mb-3">
                <label for="password" class="form-label fw-bold text-secondary"><?php echo __('password'); ?></label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label fw-bold text-secondary"><?php echo __('confirm_password'); ?></label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                <i class="bi bi-check-lg me-2"></i> <?php echo __('reset_password'); ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
