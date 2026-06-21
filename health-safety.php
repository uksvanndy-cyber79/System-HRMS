<?php
require_once __DIR__ . '/config/functions.php';
check_permission('record_safety');

$action = $_GET['action'] ?? 'list';
$error_msg = '';
$success_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $employee_id = $_POST['employee_id'] ?? '';
        $record_type = $_POST['record_type'] ?? '';
        $date = $_POST['date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $severity = $_POST['severity'] ?? 'n/a';
        $action_taken = trim($_POST['action_taken'] ?? '');
        $medical_cost = floatval($_POST['medical_cost'] ?? 0.00);

        if (empty($employee_id) || empty($record_type) || empty($date) || empty($description)) {
            $error_msg = 'Please fill out all required fields.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO `health_safety` (`employee_id`, `record_type`, `date`, `description`, `severity`, `action_taken`, `medical_cost`) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$employee_id, $record_type, $date, $description, $severity, $action_taken, $medical_cost])) {
                log_action('record_safety_incident', "Recorded health/safety event: $record_type for employee ID: $employee_id");
                set_alert('success', 'Health & Safety record created successfully!');
                header("Location: health-safety.php");
                exit();
            } else {
                $error_msg = 'Failed to save health & safety record.';
            }
        }
    }
}

// Handle Delete Action
if ($action === 'delete' && isset($_GET['id'])) {
    $record_id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM `health_safety` WHERE `id` = ?");
    if ($stmt->execute([$record_id])) {
        log_action('delete_safety_record', "Deleted safety record ID: $record_id");
        set_alert('success', 'Record deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete record.');
    }
    header("Location: health-safety.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-heart-pulse-fill me-2"></i> <?php echo __('health_safety'); ?>
            </h4>
            
            <?php if ($action === 'list'): ?>
                <a href="health-safety.php?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Log Safety/Health Event
                </a>
            <?php else: ?>
                <a href="health-safety.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Safety Log
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
        // Fetch all safety logs
        $logs = $conn->query("
            SELECT hs.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh
            FROM `health_safety` hs
            JOIN `employees` e ON hs.employee_id = e.id
            ORDER BY hs.date DESC, hs.created_at DESC
        ")->fetchAll();
    ?>
        <!-- Safety list -->
        <div class="glass-card p-4">
            <?php if (empty($logs)): ?>
                <p class="text-muted text-center py-4">No safety incidents or health records logged. Stay safe!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Record Type</th>
                                <th>Incident Date</th>
                                <th>Severity</th>
                                <th>Description / Action</th>
                                <th>Medical Cost</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo get_current_lang() == 'kh' 
                                                ? htmlspecialchars($l['last_name_kh'] . ' ' . $l['first_name_kh']) 
                                                : htmlspecialchars($l['last_name_en'] . ' ' . $l['first_name_en']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($l['employee_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $l['record_type'] === 'accident' ? 'bg-danger' : 'bg-info text-dark'; ?>">
                                            <?php echo __($l['record_type']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold small"><?php echo $l['date']; ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            if ($l['severity'] === 'high') echo 'bg-danger';
                                            elseif ($l['severity'] === 'medium') echo 'bg-warning text-dark';
                                            else echo 'bg-success';
                                        ?>">
                                            <?php echo __($l['severity']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?php echo htmlspecialchars($l['description']); ?></div>
                                        <small class="text-muted d-block">Treatment: <?php echo htmlspecialchars($l['action_taken'] ?: 'None'); ?></small>
                                    </td>
                                    <td class="fw-bold text-danger"><?php echo format_money($l['medical_cost']); ?></td>
                                    <td class="text-end">
                                        <a href="health-safety.php?action=delete&id=<?php echo $l['id']; ?>" 
                                           class="btn btn-sm btn-light border text-danger" 
                                           onclick="return confirm('Are you sure you want to delete this safety record?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'add'): 
        $workers = $conn->query("SELECT id, employee_code, first_name_en, last_name_en FROM employees WHERE status = 'active'")->fetchAll();
    ?>
        <!-- Add safety log form -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4"><i class="bi bi-heart-pulse-fill me-2"></i> Log Health / Safety Event</h5>
            
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Select Worker</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">-- Choose Worker --</option>
                            <?php foreach ($workers as $w): ?>
                                <option value="<?php echo $w['id']; ?>">
                                    <?php echo htmlspecialchars($w['employee_code'] . ' - ' . $w['last_name_en'] . ' ' . $w['first_name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Record Type</label>
                        <select class="form-select" name="record_type" required>
                            <option value="accident">Workplace Accident / គ្រោះថ្នាក់ការងារ</option>
                            <option value="health_check" selected>Routine Health Check / ពិនិត្យសុខភាព</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Incident/Check Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Severity Level</label>
                        <select class="form-select" name="severity">
                            <option value="n/a">N/A (Health Checks)</option>
                            <option value="low">Minor / Low / ស្រាល</option>
                            <option value="medium">Moderate / Medium / មធ្យម</option>
                            <option value="high">Severe / High / ធ្ងន់ធ្ងរ</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary">Incident Description / Diagnosis</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Describe the accident details or medical diagnosis findings..." required></textarea>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-bold small text-secondary">Medical Action / Treatment Taken</label>
                        <input type="text" class="form-control" name="action_taken" placeholder="e.g. Sent to provincial hospital, bandaged, prescribed medications">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary">Medical Cost Covered ($)</label>
                        <input type="number" step="0.01" class="form-control" name="medical_cost" value="0.00">
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check-circle me-1"></i> Log Record
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
