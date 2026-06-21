<?php
require_once __DIR__ . '/config/functions.php';

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_msg = 'Please enter your email.';
    } else {
        // Verify email in DB
        $stmt = $conn->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Log security event
            log_action('forgot_password_request', "Password recovery request for email: " . htmlspecialchars($email));
            
            // Redirect to reset password with email parameter
            header("Location: reset-password.php?email=" . urlencode($email) . "&token=" . md5($email . 'secure_salt'));
            exit();
        } else {
            $error_msg = 'No account associated with that email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('forget_password') . ' - ' . __('app_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="auth-wrapper">

<div class="auth-card p-4">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-warning text-white rounded-circle mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-key-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-success mb-1"><?php echo __('forget_password'); ?></h4>
        <p class="text-muted small">Enter your email to reset your security credentials</p>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="mb-3">
            <label for="email" class="form-label fw-bold text-secondary"><?php echo __('email'); ?></label>
            <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
            <i class="bi bi-envelope-fill me-2"></i> <?php echo __('submit'); ?>
        </button>

        <div class="text-center">
            <a href="login.php" class="text-success small text-decoration-none fw-semibold">
                <i class="bi bi-arrow-left"></i> <?php echo __('back_to_login'); ?>
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
