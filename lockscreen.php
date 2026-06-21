<?php
require_once __DIR__ . '/config/functions.php';

// Set lock status if requested
if (isset($_GET['action']) && $_GET['action'] === 'lock') {
    $_SESSION['locked'] = true;
    log_action('lock_screen', 'User locked their screen');
}

// Redirect to login if not authenticated at all
if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$error_msg = '';

// Load user details
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (empty($password)) {
        $error_msg = __('enter_password');
    } else {
        if (password_verify($password, $user['password'])) {
            // Unlock
            $_SESSION['locked'] = false;
            log_action('unlock_screen', 'User unlocked their screen');
            header("Location: dashboard.php");
            exit();
        } else {
            log_action('failed_unlock', 'Failed unlock screen attempt');
            $error_msg = 'Incorrect password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('lockscreen') . ' - ' . __('app_name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="auth-wrapper">

<div class="auth-card p-4 text-center">
    <div class="mb-3">
        <img src="<?php echo !empty($user['photo']) ? 'uploads/profiles/' . $user['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" 
             alt="Avatar" class="rounded-circle border" style="width: 100px; height: 100px; object-fit: cover;">
    </div>
    
    <h4 class="fw-bold text-success mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
    <p class="text-muted small mb-3"><?php echo __('lockscreen_msg'); ?></p>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="text-start">
        <div class="mb-3">
            <label for="password" class="form-label fw-bold text-secondary"><?php echo __('password'); ?></label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autofocus>
                <button class="btn btn-success" type="submit">
                    <i class="bi bi-unlock-fill"></i> <?php echo __('unlock'); ?>
                </button>
            </div>
        </div>
    </form>

    <hr>
    <div class="text-center">
        <a href="logout.php" class="text-danger small text-decoration-none fw-semibold">
            <i class="bi bi-box-arrow-right"></i> <?php echo __('different_user'); ?>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
