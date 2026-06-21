<?php
require_once __DIR__ . '/config/functions.php';
check_permission('view_employees');

$action = $_GET['action'] ?? 'list';
$error_msg = '';
$success_msg = '';

$can_manage = has_permission('manage_employees');

// Handle contract registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add' && $can_manage) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $employee_id = $_POST['employee_id'] ?? '';
        $contract_type = $_POST['contract_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        $basic_rate_daily = floatval($_POST['basic_rate_daily'] ?? 0.00);
        $production_rate_per_kg = floatval($_POST['production_rate_per_kg'] ?? 0.00);
        
        $contract_file = null;
        
        if (has_posted_file('contract_file')) {
            $upload = upload_file($_FILES['contract_file'], __DIR__ . '/uploads/contracts/', ['pdf', 'docx', 'jpg', 'jpeg', 'png']);
            if ($upload['status']) {
                $contract_file = $upload['filename'];
            } else {
                $error_msg = $upload['message'];
            }
        }
        
        if (empty($error_msg)) {
            if (empty($employee_id) || empty($contract_type) || empty($start_date)) {
                $error_msg = 'Please fill out all required fields.';
            } else {
                // Set previous contracts of this employee to expired/inactive
                $conn->prepare("UPDATE `contracts` SET `status` = 'expired' WHERE `employee_id` = ?")->execute([$employee_id]);
                
                // Save new contract
                $stmt = $conn->prepare("
                    INSERT INTO `contracts` (`employee_id`, `contract_type`, `start_date`, `end_date`, `basic_rate_daily`, `production_rate_per_kg`, `status`, `contract_file`) 
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
                ");
                
                if ($stmt->execute([$employee_id, $contract_type, $start_date, $end_date ?: null, $basic_rate_daily, $production_rate_per_kg, $contract_file])) {
                    log_action('create_contract', "Created contract for employee ID: $employee_id ($contract_type)");
                    set_alert('success', 'Worker contract registered successfully!');
                    header("Location: compliance.php");
                    exit();
                } else {
                    $error_msg = 'Failed to register contract.';
                }
            }
        }
    }
}

// Delete contract
if ($action === 'delete' && isset($_GET['id']) && $can_manage) {
    $contract_id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM `contracts` WHERE `id` = ?");
    if ($stmt->execute([$contract_id])) {
        log_action('delete_contract', "Deleted contract ID: $contract_id");
        set_alert('success', 'Contract deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete contract.');
    }
    header("Location: compliance.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-file-earmark-text-fill me-2"></i> <?php echo __('compliance'); ?>
            </h4>
            
            <?php if ($action === 'list'): ?>
                <?php if ($can_manage): ?>
                    <a href="compliance.php?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Register Signed Contract
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="compliance.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to compliance
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
        // Load statistics for compliance panel
        $total_active_workers = $conn->query("SELECT COUNT(*) FROM `employees` WHERE `status` = 'active'")->fetchColumn() ?: 1;
        $total_nssf_enrolled = $conn->query("SELECT COUNT(DISTINCT employee_id) FROM `benefits` WHERE `benefit_type` = 'nssf' AND `status` = 'active'")->fetchColumn() ?: 0;
        $nssf_percentage = round(($total_nssf_enrolled / $total_active_workers) * 100);

        // Fetch contracts
        $contracts = $conn->query("
            SELECT c.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh
            FROM `contracts` c
            JOIN `employees` e ON c.employee_id = e.id
            ORDER BY c.status ASC, c.start_date DESC
        ")->fetchAll();
    ?>
        <!-- Labor Compliance Dashboard Card -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-patch-check-fill me-2"></i> Cambodian Labor Law Checklist</h5>
                    
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                            <span>Social Security Fund (NSSF) Registration:</span>
                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded">NSSF ID: Active</span>
                        </li>
                        <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                            <span>Written Employment Contracts for all staff:</span>
                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded">Required</span>
                        </li>
                        <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                            <span>Minimum Wage Standard (Daily equivalent):</span>
                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded">Compliant ($15/day avg)</span>
                        </li>
                        <li class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                            <span>Maximum FDC Contract Duration (FDC Limit):</span>
                            <span class="badge bg-warning-subtle text-warning px-2 py-1 rounded">2 Years Ceiling</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="glass-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="fw-bold text-success mb-2"><i class="bi bi-shield-fill-check me-2"></i> NSSF Social Security Enrollment Rate</h5>
                        <p class="text-muted small">Tracking the NSSF insurance enrollment progress across all active plantation staff.</p>
                    </div>
                    
                    <div>
                        <div class="d-flex justify-content-between mb-1 small fw-bold">
                            <span>Enrollment Rate</span>
                            <span><?php echo $nssf_percentage; ?>%</span>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $nssf_percentage; ?>%" aria-valuenow="<?php echo $nssf_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted mt-2 d-block text-center"><?php echo $total_nssf_enrolled; ?> of <?php echo $total_active_workers; ?> active staff enrolled</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contracts Table -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-file-earmark-lock2-fill me-2"></i> Employee Contract Records</h5>
            
            <?php if (empty($contracts)): ?>
                <p class="text-muted text-center py-4">No contracts registered. Register signed contracts to stay legally compliant.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Contract Type</th>
                                <th>Validity Period</th>
                                <th>Daily rate</th>
                                <th>Production rate</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $c): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo get_current_lang() == 'kh' 
                                                ? htmlspecialchars($c['last_name_kh'] . ' ' . $c['first_name_kh']) 
                                                : htmlspecialchars($c['last_name_en'] . ' ' . $c['first_name_en']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['employee_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo __($c['contract_type']); ?></span>
                                    </td>
                                    <td class="small fw-semibold"><?php echo $c['start_date']; ?> to <?php echo $c['end_date'] ?: 'Indefinite'; ?></td>
                                    <td><?php echo format_money($c['basic_rate_daily']); ?> /day</td>
                                    <td><?php echo format_money($c['production_rate_per_kg']); ?> /kg</td>
                                    <td>
                                        <span class="badge badge-status <?php echo $c['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo htmlspecialchars($c['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!empty($c['contract_file'])): ?>
                                            <a href="uploads/contracts/<?php echo $c['contract_file']; ?>" target="_blank" class="btn btn-sm btn-light border text-success me-1">
                                                <i class="bi bi-file-pdf"></i> View File
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($can_manage): ?>
                                            <a href="compliance.php?action=delete&id=<?php echo $c['id']; ?>" 
                                               class="btn btn-sm btn-light border text-danger" 
                                               onclick="return confirm('Are you sure you want to delete this contract record?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'add' && $can_manage): 
        $workers = $conn->query("SELECT id, employee_code, first_name_en, last_name_en FROM employees WHERE status = 'active'")->fetchAll();
    ?>
        <!-- Register Contract Form -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4"><i class="bi bi-file-plus-fill me-2"></i> Register Signed Contract</h5>
            
            <form action="" method="POST" enctype="multipart/form-data">
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
                        <label class="form-label fw-bold small text-secondary">Contract Category</label>
                        <select class="form-select" name="contract_type" required>
                            <option value="fixed_term">Fixed Duration Contract (FDC) / កិច្ចសន្យាកំណត់ថិរវេលា</option>
                            <option value="undetermined_term">Undetermined Duration Contract (UDC) / កិច្ចសន្យាមិនកំណត់ថិរវេលា</option>
                            <option value="seasonal">Seasonal Contract / កិច្ចសន្យាតាមរដូវកាល</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">End Date (Required for FDC / Seasonal)</label>
                        <input type="date" class="form-control" name="end_date">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Basic Daily Rate ($)</label>
                        <input type="number" step="0.01" class="form-control" name="basic_rate_daily" value="15.00" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Production Latex Rate per kg ($)</label>
                        <input type="number" step="0.01" class="form-control" name="production_rate_per_kg" value="0.35" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary">Upload Signed Contract Document (PDF, Word, or Image)</label>
                        <input type="file" class="form-control" name="contract_file" required>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check-circle me-1"></i> Register Contract
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
