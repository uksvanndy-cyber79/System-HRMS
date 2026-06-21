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
            
            if (empty($name_en) || empty($name_kh)) {
                $error_msg = 'Zone names are required.';
            } else {
                $stmt = $conn->prepare("INSERT INTO `zones` (`name_en`, `name_kh`) VALUES (?, ?)");
                if ($stmt->execute([$name_en, $name_kh])) {
                    log_action('create_zone', "Created work zone: $name_en");
                    set_alert('success', 'Work zone added successfully!');
                    header("Location: zones.php");
                    exit();
                } else {
                    $error_msg = 'Failed to create zone.';
                }
            }
        } elseif ($action === 'edit' && $id) {
            $name_en = trim($_POST['name_en'] ?? '');
            $name_kh = trim($_POST['name_kh'] ?? '');
            
            if (empty($name_en) || empty($name_kh)) {
                $error_msg = 'Zone names are required.';
            } else {
                $stmt = $conn->prepare("UPDATE `zones` SET `name_en` = ?, `name_kh` = ? WHERE `id` = ?");
                if ($stmt->execute([$name_en, $name_kh, $id])) {
                    log_action('edit_zone', "Updated work zone ID: $id");
                    set_alert('success', 'Work zone updated successfully!');
                    header("Location: zones.php");
                    exit();
                } else {
                    $error_msg = 'Failed to update zone.';
                }
            }
        }
    }
}

// Delete Zone
if ($action === 'delete' && $id) {
    // Nullify references in employees table before deleting
    $conn->prepare("UPDATE `employees` SET `zone_id` = NULL WHERE `zone_id` = ?")->execute([$id]);
    
    $stmt = $conn->prepare("DELETE FROM `zones` WHERE `id` = ?");
    if ($stmt->execute([$id])) {
        log_action('delete_zone', "Deleted work zone ID: $id");
        set_alert('success', 'Work zone deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete zone.');
    }
    header("Location: zones.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-geo-alt-fill me-2"></i> Manage Work Zones / Sectors
            </h4>
            <?php if ($action === 'list'): ?>
                <a href="zones.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Zone
                </a>
            <?php else: ?>
                <a href="zones.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Zones
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
        // Fetch zones with active worker headcount
        $zones = $conn->query("
            SELECT z.*, 
                   (SELECT COUNT(*) FROM employees WHERE zone_id = z.id) as worker_count
            FROM `zones` z
            ORDER BY z.name_en ASC
        ")->fetchAll();
    ?>
        <div class="glass-card p-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Zone Name (EN)</th>
                            <th>Zone Name (KH)</th>
                            <th>Active Workers Count</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td class="fw-bold text-success"><?php echo htmlspecialchars($zone['name_en']); ?></td>
                                <td><?php echo htmlspecialchars($zone['name_kh']); ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success px-2 py-1 rounded">
                                        <?php echo $zone['worker_count']; ?> Workers
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="zones.php?action=edit&id=<?php echo $zone['id']; ?>" class="btn btn-sm btn-light border me-1">
                                        <i class="bi bi-pencil-fill text-warning"></i> Modify
                                    </a>
                                    <a href="zones.php?action=delete&id=<?php echo $zone['id']; ?>" 
                                       class="btn btn-sm btn-light border text-danger" 
                                       onclick="return confirm('Are you sure you want to delete this zone? Workers will be unassigned.')">
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
        $z = null;
        if ($action === 'edit' && $id) {
            $stmt = $conn->prepare("SELECT * FROM zones WHERE id = ?");
            $stmt->execute([$id]);
            $z = $stmt->fetch();
        }
    ?>
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4">
                <i class="bi bi-geo-alt-fill me-2"></i>
                <?php echo $action === 'add' ? 'Create Work Zone' : 'Modify Work Zone'; ?>
            </h5>
            
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Zone Name (English)</label>
                        <input type="text" class="form-control" name="name_en" value="<?php echo htmlspecialchars($z['name_en'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Zone Name (Khmer)</label>
                        <input type="text" class="form-control" name="name_kh" value="<?php echo htmlspecialchars($z['name_kh'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="zones.php" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
