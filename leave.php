<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_leaves');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error_msg = '';
$success_msg = '';

// Handle Leave Approval
if (isset($_GET['approve_id'])) {
    $ap_id = $_GET['approve_id'];
    $stmt = $conn->prepare("UPDATE `leave_requests` SET `status` = 'approved', `approved_by` = ? WHERE `id` = ?");
    if ($stmt->execute([$_SESSION['user_id'], $ap_id])) {
        log_action('approve_leave', "Approved leave request ID: $ap_id");
        set_alert('success', 'Leave request approved successfully!');
    } else {
        set_alert('danger', 'Failed to approve leave request.');
    }
    header("Location: leave.php");
    exit();
}

// Handle Leave Rejection
if (isset($_GET['reject_id'])) {
    $rj_id = $_GET['reject_id'];
    $stmt = $conn->prepare("UPDATE `leave_requests` SET `status` = 'rejected', `approved_by` = ? WHERE `id` = ?");
    if ($stmt->execute([$_SESSION['user_id'], $rj_id])) {
        log_action('reject_leave', "Rejected leave request ID: $rj_id");
        set_alert('success', 'Leave request rejected.');
    } else {
        set_alert('danger', 'Failed to reject leave.');
    }
    header("Location: leave.php");
    exit();
}

// Handle new leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'apply') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $employee_id = $_POST['employee_id'] ?? '';
        $leave_type = $_POST['leave_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        
        if (empty($employee_id) || empty($leave_type) || empty($start_date) || empty($end_date)) {
            $error_msg = 'Please fill out all required fields.';
        } else {
            // Calculate days
            $datetime1 = new DateTime($start_date);
            $datetime2 = new DateTime($end_date);
            $interval = $datetime1->diff($datetime2);
            $total_days = $interval->days + 1;
            
            $stmt = $conn->prepare("
                INSERT INTO `leave_requests` (`employee_id`, `leave_type`, `start_date`, `end_date`, `total_days`, `status`, `reason`) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ");
            if ($stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $total_days, $reason])) {
                log_action('apply_leave', "Submitted leave request for employee ID: $employee_id ($total_days days)");
                set_alert('success', 'Leave request logged successfully!');
                header("Location: leave.php");
                exit();
            } else {
                $error_msg = 'Failed to submit leave request.';
            }
        }
    }
}

// Handle Benefits Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'benefit_add') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $employee_id = $_POST['employee_id'] ?? '';
        $benefit_type = $_POST['benefit_type'] ?? '';
        $card_number = trim($_POST['card_number'] ?? '');
        $coverage_details = trim($_POST['coverage_details'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        
        if (empty($employee_id) || empty($benefit_type) || empty($start_date)) {
            $error_msg = 'Please fill out all required fields.';
        } else {
            $stmt = $conn->prepare("
                INSERT INTO `benefits` (`employee_id`, `benefit_type`, `card_number`, `coverage_details`, `start_date`, `end_date`, `status`) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            if ($stmt->execute([$employee_id, $benefit_type, $card_number, $coverage_details, $start_date, $end_date ?: null])) {
                log_action('enroll_benefit', "Enrolled employee ID: $employee_id in benefit: $benefit_type");
                set_alert('success', 'Benefit card registered successfully!');
                header("Location: leave.php");
                exit();
            } else {
                $error_msg = 'Failed to register benefits.';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-calendar-range-fill me-2"></i> <?php echo __('leave'); ?>
            </h4>
            
            <div class="d-flex gap-2">
                <?php if ($action === 'list'): ?>
                    <a href="leave.php?action=apply" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Apply Leave
                    </a>
                    <a href="leave.php?action=benefit_add" class="btn btn-secondary text-white">
                        <i class="bi bi-shield-check me-1"></i> Register Benefit Card
                    </a>
                <?php else: ?>
                    <a href="leave.php" class="btn btn-light border">
                        <i class="bi bi-arrow-left me-1"></i> Back to Leave Panel
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): 
        // Fetch leave requests
        $leaves = $conn->query("
            SELECT lr.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh, u.username as approver
            FROM `leave_requests` lr
            JOIN `employees` e ON lr.employee_id = e.id
            LEFT JOIN `users` u ON lr.approved_by = u.id
            ORDER BY lr.created_at DESC
        ")->fetchAll();

        // Fetch benefits registry
        $benefits = $conn->query("
            SELECT b.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh
            FROM `benefits` b
            JOIN `employees` e ON b.employee_id = e.id
            ORDER BY b.id DESC
        ")->fetchAll();
    ?>
        <div class="row g-4">
            <!-- Leaves Requests Table -->
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-clock-history me-2"></i> Leave Request Approvals</h5>
                    
                    <?php if (empty($leaves)): ?>
                        <p class="text-muted small"><?php echo __('no_records'); ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Worker</th>
                                        <th>Leave Type</th>
                                        <th>Period</th>
                                        <th>Total Days</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaves as $lv): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo get_current_lang() == 'kh' 
                                                        ? htmlspecialchars($lv['last_name_kh'] . ' ' . $lv['first_name_kh']) 
                                                        : htmlspecialchars($lv['last_name_en'] . ' ' . $lv['first_name_en']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($lv['employee_code']); ?></small>
                                            </td>
                                            <td><?php echo __($lv['leave_type'] . '_leave'); ?></td>
                                            <td class="small fw-semibold"><?php echo $lv['start_date']; ?> to <?php echo $lv['end_date']; ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo $lv['total_days']; ?> Days</span></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($lv['reason']); ?></td>
                                            <td>
                                                <span class="badge badge-status <?php 
                                                    if ($lv['status'] === 'approved') echo 'bg-success';
                                                    elseif ($lv['status'] === 'rejected') echo 'bg-danger';
                                                    else echo 'bg-warning text-dark';
                                                ?>">
                                                    <?php echo htmlspecialchars($lv['status']); ?>
                                                </span>
                                                <?php if (!empty($lv['approver'])): ?>
                                                    <div class="text-muted" style="font-size: 0.7rem;">By: <?php echo htmlspecialchars($lv['approver']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($lv['status'] === 'pending'): ?>
                                                    <a href="leave.php?approve_id=<?php echo $lv['id']; ?>" class="btn btn-sm btn-outline-success me-1">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </a>
                                                    <a href="leave.php?reject_id=<?php echo $lv['id']; ?>" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Benefits Registry Table -->
            <div class="col-12">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-3"><i class="bi bi-shield-fill-check me-2"></i> Worker Benefits & Insurance Registry</h5>
                    
                    <?php if (empty($benefits)): ?>
                        <p class="text-muted small"><?php echo __('no_records'); ?></p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Worker</th>
                                        <th>Benefit Scope</th>
                                        <th>Card Reference Number</th>
                                        <th>Coverage Scope</th>
                                        <th>Valid Period</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($benefits as $b): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo get_current_lang() == 'kh' 
                                                        ? htmlspecialchars($b['last_name_kh'] . ' ' . $b['first_name_kh']) 
                                                        : htmlspecialchars($b['last_name_en'] . ' ' . $b['first_name_en']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo htmlspecialchars($b['employee_code']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo __($b['benefit_type']); ?></span>
                                            </td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($b['card_number'] ?: 'N/A'); ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($b['coverage_details']); ?></td>
                                            <td class="small fw-semibold"><?php echo $b['start_date']; ?> to <?php echo $b['end_date'] ?: 'Present'; ?></td>
                                            <td>
                                                <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">
                                                    <?php echo htmlspecialchars($b['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'apply'): 
        $workers = $conn->query("SELECT id, employee_code, first_name_en, last_name_en FROM employees WHERE status = 'active'")->fetchAll();
    ?>
        <!-- Apply form -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4"><i class="bi bi-calendar-plus-fill me-2"></i> Log Leave Request</h5>
            
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
                        <label class="form-label fw-bold small text-secondary">Leave Type</label>
                        <select class="form-select" name="leave_type" required>
                            <option value="sick">Sick Leave / ច្បាប់ឈឺ</option>
                            <option value="maternity">Maternity Leave / សម្រាលកូន</option>
                            <option value="annual" selected>Annual Leave / សម្រាកប្រចាំឆ្នាំ</option>
                            <option value="other">Other / ផ្ទាល់ខ្លួន</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-secondary">Reason / Details</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Provide justification details..." required></textarea>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check-circle me-1"></i> Apply Request
                    </button>
                </div>
            </form>
        </div>

    <?php elseif ($action === 'benefit_add'): 
        $workers = $conn->query("SELECT id, employee_code, first_name_en, last_name_en FROM employees WHERE status = 'active'")->fetchAll();
    ?>
        <!-- Benefit registry form -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4"><i class="bi bi-shield-check me-2"></i> Register Worker Benefit Card</h5>
            
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
                        <label class="form-label fw-bold small text-secondary">Benefit Scope / Program</label>
                        <select class="form-select" name="benefit_type" required>
                            <option value="nssf">NSSF Social Security Card</option>
                            <option value="health_insurance">Health Insurance</option>
                            <option value="accident_insurance">Accident Insurance</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Card Reference Number</label>
                        <input type="text" class="form-control" name="card_number" placeholder="e.g. NSSF-887766-ABCD" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Coverage scope details</label>
                        <input type="text" class="form-control" name="coverage_details" placeholder="e.g. $10,000 accident limit, full outpatient" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Enrollment Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Expiry Date (Optional)</label>
                        <input type="date" class="form-control" name="end_date">
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check-circle me-1"></i> Register Card
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
