<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_employees');

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
            $name_en = trim($_POST['name_en'] ?? '');
            $name_kh = trim($_POST['name_kh'] ?? '');
            $leader_id = $_POST['leader_id'] ?? null;
            
            if (empty($name_en) || empty($name_kh)) {
                $error_msg = 'Team names are required.';
            } else {
                $stmt = $conn->prepare("INSERT INTO `teams` (`name_en`, `name_kh`, `leader_id`) VALUES (?, ?, ?)");
                if ($stmt->execute([$name_en, $name_kh, $leader_id ?: null])) {
                    log_action('create_team', "Created work team: $name_en");
                    set_alert('success', 'Work team added successfully!');
                    header("Location: teams.php");
                    exit();
                } else {
                    $error_msg = 'Failed to create team.';
                }
            }
        } elseif ($action === 'edit' && $id) {
            $name_en = trim($_POST['name_en'] ?? '');
            $name_kh = trim($_POST['name_kh'] ?? '');
            $leader_id = $_POST['leader_id'] ?? null;
            
            if (empty($name_en) || empty($name_kh)) {
                $error_msg = 'Team names are required.';
            } else {
                $stmt = $conn->prepare("UPDATE `teams` SET `name_en` = ?, `name_kh` = ?, `leader_id` = ? WHERE `id` = ?");
                if ($stmt->execute([$name_en, $name_kh, $leader_id ?: null, $id])) {
                    log_action('edit_team', "Updated work team ID: $id");
                    set_alert('success', 'Work team updated successfully!');
                    header("Location: teams.php");
                    exit();
                } else {
                    $error_msg = 'Failed to update team.';
                }
            }
        }
    }
}

// Delete Team
if ($action === 'delete' && $id) {
    // Nullify references in employees table before deleting
    $conn->prepare("UPDATE `employees` SET `team_id` = NULL WHERE `team_id` = ?")->execute([$id]);
    
    $stmt = $conn->prepare("DELETE FROM `teams` WHERE `id` = ?");
    if ($stmt->execute([$id])) {
        log_action('delete_team', "Deleted work team ID: $id");
        set_alert('success', 'Work team deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete team.');
    }
    header("Location: teams.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-people-fill me-2"></i> Manage Work Teams / Groups
            </h4>
            <?php if ($action === 'list'): ?>
                <a href="teams.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Team
                </a>
            <?php else: ?>
                <a href="teams.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Teams
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
        // Fetch teams with leaders
        $teams = $conn->query("
            SELECT t.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh,
                   (SELECT COUNT(*) FROM employees WHERE team_id = t.id) as worker_count
            FROM `teams` t
            LEFT JOIN `employees` e ON t.leader_id = e.id
            ORDER BY t.name_en ASC
        ")->fetchAll();
    ?>
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Team Name (EN)</th>
                            <th>Team Name (KH)</th>
                            <th>Team Leader</th>
                            <th>Active Workers Count</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teams as $team): ?>
                            <tr>
                                <td class="fw-bold text-success"><?php echo htmlspecialchars($team['name_en']); ?></td>
                                <td><?php echo htmlspecialchars($team['name_kh']); ?></td>
                                <td>
                                    <?php if (!empty($team['leader_id'])): ?>
                                        <div class="fw-semibold">
                                            <?php echo get_current_lang() == 'kh' 
                                                ? htmlspecialchars($team['last_name_kh'] . ' ' . $team['first_name_kh']) 
                                                : htmlspecialchars($team['last_name_en'] . ' ' . $team['first_name_en']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($team['employee_code']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">No Leader Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded">
                                        <?php echo $team['worker_count']; ?> Workers
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="teams.php?action=edit&id=<?php echo $team['id']; ?>" class="btn btn-sm btn-light border me-1">
                                        <i class="bi bi-pencil-fill text-warning"></i> Modify
                                    </a>
                                    <a href="teams.php?action=delete&id=<?php echo $team['id']; ?>" 
                                       class="btn btn-sm btn-light border text-danger" 
                                       onclick="return confirm('Are you sure you want to delete this team? Workers will be unassigned.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): 
        $t = null;
        if ($action === 'edit' && $id) {
            $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
            $stmt->execute([$id]);
            $t = $stmt->fetch();
        }
        // Fetch all active employees to select as leader
        $employees = $conn->query("SELECT id, employee_code, first_name_en, last_name_en, first_name_kh, last_name_kh FROM employees WHERE status = 'active'")->fetchAll();
    ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4">
                <i class="bi bi-people-fill me-2"></i>
                <?php echo $action === 'add' ? 'Create Work Team' : 'Modify Work Team'; ?>
            </h5>
            
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Team Name (English)</label>
                        <input type="text" class="form-control" name="name_en" value="<?php echo htmlspecialchars($t['name_en'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Team Name (Khmer)</label>
                        <input type="text" class="form-control" name="name_kh" value="<?php echo htmlspecialchars($t['name_kh'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Team Leader (Supervisor)</label>
                        <select class="form-select" name="leader_id">
                            <option value="">-- No Leader Assigned --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo (isset($t['leader_id']) && $t['leader_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['last_name_en'] . ' ' . $emp['first_name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="teams.php" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
