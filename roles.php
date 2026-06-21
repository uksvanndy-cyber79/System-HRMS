<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_roles');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $perms = $_POST['permissions'] ?? [];
            
            if (empty($name)) {
                $error_msg = 'Role name cannot be empty.';
            } else {
                // Insert role
                $stmt = $conn->prepare("INSERT INTO `roles` (`name`, `description`) VALUES (?, ?)");
                if ($stmt->execute([$name, $description])) {
                    $role_id = $conn->lastInsertId();
                    
                    // Insert role permissions
                    if (!empty($perms)) {
                        $stmt_p = $conn->prepare("INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
                        foreach ($perms as $p_id) {
                            $stmt_p->execute([$role_id, $p_id]);
                        }
                    }
                    
                    log_action('create_role', "Created role: $name");
                    set_alert('success', 'Role added successfully!');
                    header("Location: roles.php");
                    exit();
                } else {
                    $error_msg = 'Failed to create role.';
                }
            }
        } elseif ($action === 'edit' && $id) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $perms = $_POST['permissions'] ?? [];
            
            if (empty($name)) {
                $error_msg = 'Role name cannot be empty.';
            } else {
                // Update role
                $stmt = $conn->prepare("UPDATE `roles` SET `name` = ?, `description` = ? WHERE `id` = ?");
                if ($stmt->execute([$name, $description, $id])) {
                    
                    // Clear current permissions
                    $conn->prepare("DELETE FROM `role_permissions` WHERE `role_id` = ?")->execute([$id]);
                    
                    // Insert new permissions
                    if (!empty($perms)) {
                        $stmt_p = $conn->prepare("INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (?, ?)");
                        foreach ($perms as $p_id) {
                            $stmt_p->execute([$id, $p_id]);
                        }
                    }
                    
                    log_action('edit_role', "Updated role permissions for ID: $id");
                    set_alert('success', 'Role permissions updated successfully!');
                    header("Location: roles.php");
                    exit();
                } else {
                    $error_msg = 'Failed to update role.';
                }
            }
        }
    }
}

// Delete Role
if ($action === 'delete' && $id) {
    if ($id == 1) {
        set_alert('danger', 'You cannot delete the default Admin role!');
    } else {
        $stmt = $conn->prepare("DELETE FROM `roles` WHERE `id` = ?");
        if ($stmt->execute([$id])) {
            log_action('delete_role', "Deleted role ID: $id");
            set_alert('success', 'Role deleted successfully!');
        } else {
            set_alert('danger', 'Failed to delete role.');
        }
    }
    header("Location: roles.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-shield-lock-fill me-2"></i> <?php echo __('roles'); ?>
            </h4>
            <?php if ($action === 'list'): ?>
                <a href="roles.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> <?php echo __('add'); ?>
                </a>
            <?php else: ?>
                <a href="roles.php" class="btn btn-light border">
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
        $roles = $conn->query("
            SELECT r.*, COUNT(rp.permission_id) as perm_count 
            FROM `roles` r 
            LEFT JOIN `role_permissions` rp ON r.id = rp.role_id 
            GROUP BY r.id
        ")->fetchAll();
    ?>
        <!-- List roles -->
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Active Permissions</th>
                            <th class="text-end"><?php echo __('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $r): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($r['name']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($r['description']); ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success px-2 py-1">
                                        <?php echo $r['perm_count']; ?> Permissions
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="roles.php?action=edit&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-light border me-1">
                                        <i class="bi bi-pencil-fill text-warning"></i> Modify Permissions
                                    </a>
                                    <a href="roles.php?action=delete&id=<?php echo $r['id']; ?>" 
                                       class="btn btn-sm btn-light border text-danger" 
                                       onclick="return confirm('Are you sure you want to delete this role?')">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): 
        $r = null;
        $active_perms = [];
        if ($action === 'edit' && $id) {
            $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            
            // Get active permissions for this role
            $p_stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
            $p_stmt->execute([$id]);
            $active_perms = $p_stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $all_perms = $conn->query("SELECT * FROM permissions")->fetchAll();
    ?>
        <!-- Add / Edit Role -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4">
                <i class="bi bi-shield-fill-check me-2"></i>
                <?php echo $action === 'add' ? 'Create New Role' : 'Modify Role & Permissions'; ?>
            </h5>
            
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Role Name</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($r['name'] ?? ''); ?>" required
                               <?php echo ($action === 'edit' && $id == 1) ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Description</label>
                        <input type="text" class="form-control" name="description" 
                               value="<?php echo htmlspecialchars($r['description'] ?? ''); ?>">
                    </div>

                    <div class="col-12 mt-4">
                        <label class="form-label fw-bold text-success fs-6"><i class="bi bi-key-fill me-1"></i> Bind Access Permissions</label>
                        <p class="text-muted small">Check the access level permissions assigned to this role</p>
                        
                        <div class="row g-3 p-3 bg-light rounded border">
                            <?php foreach ($all_perms as $p): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" 
                                               value="<?php echo $p['id']; ?>" 
                                               id="perm_<?php echo $p['id']; ?>"
                                               <?php echo in_array($p['id'], $active_perms) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-secondary" for="perm_<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </label>
                                        <div class="text-muted" style="font-size: 0.75rem; margin-left: 20px;">
                                            <?php echo htmlspecialchars($p['description']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="roles.php" class="btn btn-light border"><?php echo __('cancel'); ?></a>
                    <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
