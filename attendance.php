<?php
require_once __DIR__ . '/config/functions.php';
check_permission('record_attendance');

$error_msg = '';
$success_msg = '';

$date = $_GET['date'] ?? date('Y-m-d');
$team_id = $_GET['team_id'] ?? '';

// Load teams list
$teams = $conn->query("SELECT * FROM `teams` ORDER BY `name_en` ASC")->fetchAll();

// If team_id is empty, select the first team by default
if (empty($team_id) && !empty($teams)) {
    $team_id = $teams[0]['id'];
}

// Handle bulk submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $attendance_data = $_POST['attendance'] ?? [];
        
        if (empty($attendance_data)) {
            $error_msg = 'No attendance records to save.';
        } else {
            try {
                $conn->beginTransaction();
                
                $stmt = $conn->prepare("
                    INSERT INTO `attendance` 
                    (`employee_id`, `date`, `status`, `check_in`, `check_out`, `latex_kg`, `trees_tapped`, `remarks`, `recorded_by`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    `status` = VALUES(`status`),
                    `check_in` = VALUES(`check_in`),
                    `check_out` = VALUES(`check_out`),
                    `latex_kg` = VALUES(`latex_kg`),
                    `trees_tapped` = VALUES(`trees_tapped`),
                    `remarks` = VALUES(`remarks`),
                    `recorded_by` = VALUES(`recorded_by`)
                ");
                
                $recorded_by = $_SESSION['user_id'];
                
                foreach ($attendance_data as $emp_id => $data) {
                    $status = $data['status'] ?? 'absent';
                    $check_in = !empty($data['check_in']) ? $data['check_in'] : null;
                    $check_out = !empty($data['check_out']) ? $data['check_out'] : null;
                    $latex_kg = !empty($data['latex_kg']) ? floatval($data['latex_kg']) : 0.00;
                    $trees_tapped = !empty($data['trees_tapped']) ? intval($data['trees_tapped']) : 0;
                    $remarks = $data['remarks'] ?? '';
                    
                    $stmt->execute([
                        $emp_id, $date, $status, $check_in, $check_out, $latex_kg, $trees_tapped, $remarks, $recorded_by
                    ]);
                }
                
                $conn->commit();
                log_action('record_attendance', "Recorded team attendance and productivity for Team ID: $team_id on $date");
                set_alert('success', 'Attendance and productivity logs saved successfully!');
                header("Location: attendance.php?date=$date&team_id=$team_id");
                exit();
            } catch (Exception $e) {
                $conn->rollBack();
                $error_msg = 'Error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all employees in selected team
$workers = [];
if (!empty($team_id)) {
    $stmt = $conn->prepare("
        SELECT e.*, a.status as att_status, a.check_in, a.check_out, a.latex_kg, a.trees_tapped, a.remarks 
        FROM `employees` e
        LEFT JOIN `attendance` a ON e.id = a.employee_id AND a.date = ?
        WHERE e.team_id = ? AND e.status = 'active'
        ORDER BY e.employee_code ASC
    ");
    $stmt->execute([$date, $team_id]);
    $workers = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-calendar-check-fill me-2"></i> <?php echo __('attendance'); ?>
            </h4>
            <a href="scan-qr.php" class="btn btn-success">
                <i class="bi bi-qr-code-scan me-1"></i> Scan QR Attendance
            </a>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="glass-card p-3 mb-4">
        <form action="" method="GET" class="row g-3 align-items-center">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-secondary">Work Date</label>
                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date); ?>">
            </div>
            
            <div class="col-md-5">
                <label class="form-label fw-bold small text-secondary">Work Group / Team</label>
                <select class="form-select" name="team_id" required>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>" <?php echo $team_id == $team['id'] ? 'selected' : ''; ?>>
                            <?php echo get_current_lang() == 'kh' ? htmlspecialchars($team['name_kh']) : htmlspecialchars($team['name_en']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 d-grid align-self-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Load Group</button>
            </div>
        </form>
    </div>

    <!-- Attendance Entry Panel -->
    <div class="glass-card p-4">
        <h5 class="fw-bold text-success mb-3">
            <i class="bi bi-person-check-fill me-2"></i> 
            Group Attendance & Productivity - <?php echo date('d-M-Y', strtotime($date)); ?>
        </h5>
        
        <?php if (empty($workers)): ?>
            <p class="text-muted text-center py-4">No active workers found assigned to this team.</p>
        <?php else: ?>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover border mb-0" style="vertical-align: middle;">
                        <thead>
                            <tr>
                                <th style="min-width: 180px;">Worker Name</th>
                                <th style="min-width: 250px;">Attendance Status</th>
                                <th style="width: 120px;">Check In</th>
                                <th style="width: 120px;">Check Out</th>
                                <th style="width: 130px;"><?php echo __('latex_collected'); ?></th>
                                <th style="width: 110px;">Trees Tapped</th>
                                <th style="min-width: 150px;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $w): 
                                $status = $w['att_status'] ?? 'present';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($w['last_name_en'] . ' ' . $w['first_name_en']); ?></div>
                                        <div class="text-success small fw-semibold"><?php echo htmlspecialchars($w['employee_code']); ?></div>
                                    </td>
                                    <td>
                                        <!-- Custom radio status -->
                                        <div class="d-flex gap-2">
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $w['id']; ?>][status]" 
                                                   value="present" id="status_p_<?php echo $w['id']; ?>" <?php echo $status === 'present' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success btn-sm" for="status_p_<?php echo $w['id']; ?>"><?php echo __('present'); ?></label>
                                            
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $w['id']; ?>][status]" 
                                                   value="absent" id="status_a_<?php echo $w['id']; ?>" <?php echo $status === 'absent' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger btn-sm" for="status_a_<?php echo $w['id']; ?>"><?php echo __('absent'); ?></label>
                                            
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $w['id']; ?>][status]" 
                                                   value="leave" id="status_l_<?php echo $w['id']; ?>" <?php echo $status === 'leave' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning btn-sm" for="status_l_<?php echo $w['id']; ?>"><?php echo __('leave'); ?></label>
                                            
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $w['id']; ?>][status]" 
                                                   value="late" id="status_t_<?php echo $w['id']; ?>" <?php echo $status === 'late' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info btn-sm" for="status_t_<?php echo $w['id']; ?>"><?php echo __('late'); ?></label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="time" class="form-control form-control-sm" name="attendance[<?php echo $w['id']; ?>][check_in]" 
                                               value="<?php echo htmlspecialchars($w['check_in'] ?? '07:00'); ?>">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control form-control-sm" name="attendance[<?php echo $w['id']; ?>][check_out]" 
                                               value="<?php echo htmlspecialchars($w['check_out'] ?? '16:00'); ?>">
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" class="form-control" name="attendance[<?php echo $w['id']; ?>][latex_kg]" 
                                                   value="<?php echo htmlspecialchars($w['latex_kg'] ?? '0.00'); ?>">
                                            <span class="input-group-text bg-light text-muted" style="font-size: 0.75rem;">kg</span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" name="attendance[<?php echo $w['id']; ?>][trees_tapped]" 
                                               value="<?php echo htmlspecialchars($w['trees_tapped'] ?? '0'); ?>">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="attendance[<?php echo $w['id']; ?>][remarks]" 
                                               value="<?php echo htmlspecialchars($w['remarks'] ?? ''); ?>" placeholder="Notes...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-save-fill me-2"></i> <?php echo __('record_btn'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
