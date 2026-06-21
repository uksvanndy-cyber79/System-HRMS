<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_users');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error_msg = '';
$success_msg = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        if ($action === 'add') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_id = $_POST['role_id'] ?? null;
            $status = $_POST['status'] ?? 'active';
            
            // Photo & Cover
            $photo_name = null;
            $cover_name = null;
            
            if (has_posted_file('photo')) {
                $upload = upload_file($_FILES['photo'], __DIR__ . '/uploads/profiles/');
                if ($upload['status']) {
                    $photo_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg) && has_posted_file('cover')) {
                $upload = upload_file($_FILES['cover'], __DIR__ . '/uploads/covers/');
                if ($upload['status']) {
                    $cover_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg)) {
                if (empty($username) || empty($email) || empty($password) || empty($role_id)) {
                    $error_msg = 'Please fill out all required fields.';
                } else {
                    // Check duplicate username
                    $check = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
                    $check->execute([$username]);
                    if ($check->fetchColumn() > 0) {
                        $error_msg = 'Username already exists.';
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            INSERT INTO `users` (`username`, `email`, `password`, `role_id`, `company_id`, `status`, `photo`, `cover`) 
                            VALUES (?, ?, ?, ?, 1, ?, ?, ?)
                        ");
                        if ($stmt->execute([$username, $email, $hashed, $role_id, $status, $photo_name, $cover_name])) {
                            log_action('create_user', "Created user: $username");
                            set_alert('success', 'User added successfully!');
                            header("Location: users.php");
                            exit();
                        } else {
                            $error_msg = 'Failed to create user.';
                        }
                    }
                }
            }
        } elseif ($action === 'edit' && $id) {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_id = $_POST['role_id'] ?? null;
            $status = $_POST['status'] ?? 'active';
            
            // Get current file names to retain if not changed
            $curr = $conn->prepare("SELECT photo, cover FROM users WHERE id = ?");
            $curr->execute([$id]);
            $curr_user = $curr->fetch();
            $photo_name = $curr_user['photo'];
            $cover_name = $curr_user['cover'];
            
            if (has_posted_file('photo')) {
                $upload = upload_file($_FILES['photo'], __DIR__ . '/uploads/profiles/');
                if ($upload['status']) {
                    $photo_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg) && has_posted_file('cover')) {
                $upload = upload_file($_FILES['cover'], __DIR__ . '/uploads/covers/');
                if ($upload['status']) {
                    $cover_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg)) {
                if (empty($email) || empty($role_id)) {
                    $error_msg = 'Please fill out all required fields.';
                } else {
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            UPDATE `users` 
                            SET `email` = ?, `password` = ?, `role_id` = ?, `status` = ?, `photo` = ?, `cover` = ? 
                            WHERE `id` = ?
                        ");
                        $params = [$email, $hashed, $role_id, $status, $photo_name, $cover_name, $id];
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE `users` 
                            SET `email` = ?, `role_id` = ?, `status` = ?, `photo` = ?, `cover` = ? 
                            WHERE `id` = ?
                        ");
                        $params = [$email, $role_id, $status, $photo_name, $cover_name, $id];
                    }
                    
                    if ($stmt->execute($params)) {
                        log_action('edit_user', "Updated user ID: $id");
                        set_alert('success', 'User updated successfully!');
                        header("Location: users.php");
                        exit();
                    } else {
                        $error_msg = 'Failed to update user.';
                    }
                }
            }
        }
    }
}

// Handle Delete Action
if ($action === 'delete' && $id) {
    // Cannot delete active logged in admin user
    if ($id == $_SESSION['user_id']) {
        set_alert('danger', 'You cannot delete yourself!');
    } else {
        $stmt = $conn->prepare("DELETE FROM `users` WHERE `id` = ?");
        if ($stmt->execute([$id])) {
            log_action('delete_user', "Deleted user ID: $id");
            set_alert('success', 'User deleted successfully!');
        } else {
            set_alert('danger', 'Failed to delete user.');
        }
    }
    header("Location: users.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-people-fill me-2"></i> <?php echo __('users'); ?>
            </h4>
            <?php if ($action === 'list'): ?>
                <a href="users.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> <?php echo __('add'); ?>
                </a>
            <?php else: ?>
                <a href="users.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> <?php echo __('back_to_login'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): 
        // Fetch all users
        $users = $conn->query("
            SELECT u.*, r.name as role_name 
            FROM `users` u 
            LEFT JOIN `roles` r ON u.role_id = r.id 
            ORDER BY u.created_at DESC
        ")->fetchAll();
    ?>
        <!-- Users List -->
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('username'); ?></th>
                            <th><?php echo __('email'); ?></th>
                            <th><?php echo __('role'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th class="text-end"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?php echo !empty($u['photo']) ? 'uploads/profiles/' . $u['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" 
                                             alt="Avatar" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;">
                                        <span class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($u['role_name'] ?? 'None'); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-status <?php echo $u['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars($u['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></td>
                                <td class="text-end">
                                    <a href="users.php?action=view&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border me-1" title="View Profile">
                                        <i class="bi bi-eye text-success"></i>
                                    </a>
                                    <a href="users.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light border me-1" title="Edit">
                                        <i class="bi bi-pencil-fill text-warning"></i>
                                    </a>
                                    <a href="users.php?action=delete&id=<?php echo $u['id']; ?>" 
                                       class="btn btn-sm btn-light border" 
                                       onclick="return confirm('Are you sure you want to delete this user?')" title="Delete">
                                        <i class="bi bi-trash-fill text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'view' && $id): 
        // Fetch detailed profile
        $stmt = $conn->prepare("
            SELECT u.*, r.name as role_name 
            FROM `users` u 
            LEFT JOIN `roles` r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        
        if (!$u) {
            echo "<p>User not found.</p>";
            include __DIR__ . '/includes/footer.php';
            exit();
        }
        
        // Fetch audit logs for this specific user
        $log_stmt = $conn->prepare("
            SELECT * FROM `action_logs` 
            WHERE `user_id` = ? 
            ORDER BY `created_at` DESC 
            LIMIT 15
        ");
        $log_stmt->execute([$id]);
        $user_logs = $log_stmt->fetchAll();
    ?>
        <!-- View Profile & Action Logs -->
        <div class="row g-4">
            <!-- Profile Column -->
            <div class="col-lg-4">
                <div class="glass-card overflow-hidden h-100">
                    <!-- Cover photo -->
                    <div class="profile-header" style="background-image: url('<?php echo !empty($u['cover']) ? 'uploads/covers/' . $u['cover'] : 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&w=600&q=80'; ?>');">
                        <div class="profile-avatar-wrapper">
                            <img src="<?php echo !empty($u['photo']) ? 'uploads/profiles/' . $u['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" 
                                 class="profile-avatar" alt="Photo">
                        </div>
                    </div>
                    
                    <div class="p-4 pt-5 mt-4">
                        <h4 class="fw-bold text-success mb-1"><?php echo htmlspecialchars($u['username']); ?></h4>
                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill mb-4"><?php echo htmlspecialchars($u['role_name'] ?? 'User'); ?></span>
                        
                        <div class="d-flex flex-column gap-3 text-muted small">
                            <div>
                                <i class="bi bi-envelope me-2"></i> <?php echo htmlspecialchars($u['email']); ?>
                            </div>
                            <div>
                                <i class="bi bi-shield-check me-2"></i> Status: <strong><?php echo htmlspecialchars($u['status']); ?></strong>
                            </div>
                            <div>
                                <i class="bi bi-clock me-2"></i> Member Since: <strong><?php echo date('Y-m-d', strtotime($u['created_at'])); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Log Column -->
            <div class="col-lg-8">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-4">
                        <i class="bi bi-activity me-2"></i> <?php echo __('action_history'); ?>
                    </h5>
                    
                    <?php if (empty($user_logs)): ?>
                        <p class="text-muted"><?php echo __('no_records'); ?></p>
                    <?php else: ?>
                        <div class="ps-2">
                            <?php foreach ($user_logs as $log): ?>
                                <div class="log-item mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong class="text-success small"><?php echo htmlspecialchars($log['action']); ?></strong>
                                        <span class="text-muted" style="font-size: 0.75rem;"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></span>
                                    </div>
                                    <p class="mb-0 text-muted small"><?php echo htmlspecialchars($log['details']); ?></p>
                                    <small class="text-secondary" style="font-size: 0.7rem;">IP: <?php echo htmlspecialchars($log['ip_address']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif (($action === 'add' || $action === 'edit')): 
        $roles = $conn->query("SELECT * FROM roles")->fetchAll();
        
        $u = null;
        if ($action === 'edit' && $id) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $u = $stmt->fetch();
        }
    ?>
        <!-- Create / Edit User Form -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4">
                <i class="bi bi-person-fill-gear me-2"></i>
                <?php echo $action === 'add' ? 'Add User Profile' : 'Edit User Profile'; ?>
            </h5>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('username'); ?></label>
                        <input type="text" class="form-control" name="username" 
                               value="<?php echo htmlspecialchars($u['username'] ?? ''); ?>" 
                               <?php echo $action === 'edit' ? 'disabled' : 'required'; ?>>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('email'); ?></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">
                            <?php echo __('password'); ?> <?php echo $action === 'edit' ? '(Leave blank to keep current)' : ''; ?>
                        </label>
                        <input type="password" class="form-control" name="password" 
                               <?php echo $action === 'add' ? 'required' : ''; ?>>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('role'); ?></label>
                        <select class="form-select" name="role_id" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" 
                                    <?php echo (isset($u['role_id']) && $u['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('status'); ?></label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo (isset($u['status']) && $u['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($u['status']) && $u['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('photo'); ?></label>
                        <input type="file" class="form-control" name="photo" accept="image/*">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('cover'); ?></label>
                        <input type="file" class="form-control" name="cover" accept="image/*">
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="users.php" class="btn btn-light border"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
