<?php
require_once __DIR__ . '/config/functions.php';
check_permission('view_employees');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error_msg = '';
$success_msg = '';

$can_manage = has_permission('manage_employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        if ($action === 'add' || ($action === 'edit' && $id)) {
            $employee_code = trim($_POST['employee_code'] ?? '');
            $first_name_en = trim($_POST['first_name_en'] ?? '');
            $last_name_en = trim($_POST['last_name_en'] ?? '');
            $first_name_kh = trim($_POST['first_name_kh'] ?? '');
            $last_name_kh = trim($_POST['last_name_kh'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $dob = $_POST['dob'] ?? null;
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $worker_type = $_POST['worker_type'] ?? 'permanent';
            $team_id = $_POST['team_id'] ?? null;
            $zone_id = $_POST['zone_id'] ?? null;
            $status = $_POST['status'] ?? 'active';
            $date_joined = $_POST['date_joined'] ?? null;
            $bank_name = trim($_POST['bank_name'] ?? '');
            $bank_account = trim($_POST['bank_account'] ?? '');
            $mobile_money_provider = trim($_POST['mobile_money_provider'] ?? '');
            $mobile_money_number = trim($_POST['mobile_money_number'] ?? '');
            
            // Photo & Cover retaining
            $photo_name = null;
            $cover_name = null;
            
            if ($action === 'edit') {
                $curr = $conn->prepare("SELECT photo, cover FROM employees WHERE id = ?");
                $curr->execute([$id]);
                $curr_emp = $curr->fetch();
                $photo_name = $curr_emp['photo'];
                $cover_name = $curr_emp['cover'];
            }
            
            // Upload photo
            if (has_posted_file('photo')) {
                $upload = upload_file($_FILES['photo'], __DIR__ . '/uploads/profiles/');
                if ($upload['status']) {
                    $photo_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            // Upload cover
            if (empty($error_msg) && has_posted_file('cover')) {
                $upload = upload_file($_FILES['cover'], __DIR__ . '/uploads/covers/');
                if ($upload['status']) {
                    $cover_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg)) {
                if (empty($employee_code) || empty($first_name_en) || empty($last_name_en) || empty($first_name_kh) || empty($last_name_kh) || empty($gender)) {
                    $error_msg = 'Please fill out all required fields.';
                } else {
                    if ($action === 'add') {
                        // Check duplicate code
                        $check = $conn->prepare("SELECT COUNT(*) FROM `employees` WHERE `employee_code` = ?");
                        $check->execute([$employee_code]);
                        if ($check->fetchColumn() > 0) {
                            $error_msg = 'Employee Code already exists.';
                        } else {
                            $stmt = $conn->prepare("
                                INSERT INTO `employees` 
                                (`employee_code`, `first_name_en`, `last_name_en`, `first_name_kh`, `last_name_kh`, `gender`, `dob`, `phone`, `email`, `worker_type`, `team_id`, `zone_id`, `status`, `date_joined`, `bank_name`, `bank_account`, `mobile_money_provider`, `mobile_money_number`, `photo`, `cover`) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $res = $stmt->execute([
                                $employee_code, $first_name_en, $last_name_en, $first_name_kh, $last_name_kh, $gender, $dob ?: null, $phone, $email, $worker_type, 
                                $team_id ?: null, $zone_id ?: null, $status, $date_joined ?: null, $bank_name, $bank_account, $mobile_money_provider, $mobile_money_number, 
                                $photo_name, $cover_name
                            ]);
                            if ($res) {
                                log_action('add_employee', "Added worker: $last_name_en $first_name_en ($employee_code)");
                                set_alert('success', 'Worker added successfully!');
                                header("Location: employees.php");
                                exit();
                            } else {
                                $error_msg = 'Failed to save worker.';
                            }
                        }
                    } else {
                        // Edit
                        $stmt = $conn->prepare("
                            UPDATE `employees` 
                            SET `employee_code` = ?, `first_name_en` = ?, `last_name_en` = ?, `first_name_kh` = ?, `last_name_kh` = ?, `gender` = ?, `dob` = ?, `phone` = ?, `email` = ?, `worker_type` = ?, `team_id` = ?, `zone_id` = ?, `status` = ?, `date_joined` = ?, `bank_name` = ?, `bank_account` = ?, `mobile_money_provider` = ?, `mobile_money_number` = ?, `photo` = ?, `cover` = ? 
                            WHERE `id` = ?
                        ");
                        $res = $stmt->execute([
                            $employee_code, $first_name_en, $last_name_en, $first_name_kh, $last_name_kh, $gender, $dob ?: null, $phone, $email, $worker_type, 
                            $team_id ?: null, $zone_id ?: null, $status, $date_joined ?: null, $bank_name, $bank_account, $mobile_money_provider, $mobile_money_number, 
                            $photo_name, $cover_name, $id
                        ]);
                        if ($res) {
                            log_action('edit_employee', "Updated worker ID: $id");
                            set_alert('success', 'Worker profile updated successfully!');
                            header("Location: employees.php");
                            exit();
                        } else {
                            $error_msg = 'Failed to update worker profile.';
                        }
                    }
                }
            }
        }
    }
}

// Delete Employee Action
if ($action === 'delete' && $id && $can_manage) {
    $stmt = $conn->prepare("DELETE FROM `employees` WHERE `id` = ?");
    if ($stmt->execute([$id])) {
        log_action('delete_employee', "Deleted employee ID: $id");
        set_alert('success', 'Worker deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete worker record.');
    }
    header("Location: employees.php");
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-person-badge-fill me-2"></i> <?php echo __('employees'); ?>
            </h4>
            <?php if ($action === 'list'): ?>
                <?php if ($can_manage): ?>
                    <a href="employees.php?action=add" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-2"></i> <?php echo __('add'); ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="employees.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Registry
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
        // Search filter
        $search = $_GET['search'] ?? '';
        $type_filter = $_GET['type'] ?? '';
        
        $query = "
            SELECT e.*, t.name_en as team_en, t.name_kh as team_kh, z.name_en as zone_en, z.name_kh as zone_kh 
            FROM `employees` e 
            LEFT JOIN `teams` t ON e.team_id = t.id 
            LEFT JOIN `zones` z ON e.zone_id = z.id
            WHERE 1
        ";
        $params = [];
        
        if (!empty($search)) {
            $query .= " AND (e.employee_code LIKE ? OR e.first_name_en LIKE ? OR e.last_name_en LIKE ? OR e.first_name_kh LIKE ? OR e.last_name_kh LIKE ?)";
            $bind_search = "%$search%";
            $params = array_merge($params, [$bind_search, $bind_search, $bind_search, $bind_search, $bind_search]);
        }
        
        if (!empty($type_filter)) {
            $query .= " AND e.worker_type = ?";
            $params[] = $type_filter;
        }
        
        $query .= " ORDER BY e.employee_code ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $workers = $stmt->fetchAll();
    ?>
        <!-- Search & Filter bar -->
        <div class="glass-card p-3 mb-4">
            <form action="" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search by name, ID or code..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="type">
                        <option value="">-- All Types --</option>
                        <option value="permanent" <?php echo $type_filter === 'permanent' ? 'selected' : ''; ?>><?php echo __('permanent'); ?></option>
                        <option value="seasonal" <?php echo $type_filter === 'seasonal' ? 'selected' : ''; ?>><?php echo __('seasonal'); ?></option>
                        <option value="daily" <?php echo $type_filter === 'daily' ? 'selected' : ''; ?>><?php echo __('daily'); ?></option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Workers List Table -->
        <div class="glass-card p-4">
            <?php if (empty($workers)): ?>
                <p class="text-muted text-center py-4"><?php echo __('no_records'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name (EN / KH)</th>
                                <th>Gender</th>
                                <th>Worker Type</th>
                                <th>Team & Zone</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workers as $w): ?>
                                <tr>
                                    <td class="fw-bold text-success"><?php echo htmlspecialchars($w['employee_code']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo !empty($w['photo']) ? 'uploads/profiles/' . $w['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" 
                                                 alt="Worker Photo" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($w['last_name_en'] . ' ' . $w['first_name_en']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($w['last_name_kh'] . ' ' . $w['first_name_kh']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo __($w['gender']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo __($w['worker_type']); ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold"><?php echo get_current_lang() == 'kh' ? htmlspecialchars($w['team_kh'] ?? 'None') : htmlspecialchars($w['team_en'] ?? 'None'); ?></div>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo get_current_lang() == 'kh' ? htmlspecialchars($w['zone_kh'] ?? 'None') : htmlspecialchars($w['zone_en'] ?? 'None'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge badge-status <?php echo $w['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo __($w['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="employees.php?action=view&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-light border me-1" title="View Profile">
                                            <i class="bi bi-eye text-success"></i>
                                        </a>
                                        <a href="employees.php?action=idcard&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-light border me-1" title="Print ID Card">
                                            <i class="bi bi-card-image text-primary"></i>
                                        </a>
                                        <?php if ($can_manage): ?>
                                            <a href="employees.php?action=edit&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-light border me-1" title="Edit">
                                                <i class="bi bi-pencil-fill text-warning"></i>
                                            </a>
                                            <a href="employees.php?action=delete&id=<?php echo $w['id']; ?>" 
                                               class="btn btn-sm btn-light border text-danger" 
                                               onclick="return confirm('Are you sure you want to delete this worker registry?')" title="Delete">
                                                <i class="bi bi-trash-fill"></i>
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

    <?php elseif ($action === 'view' && $id): 
        // View single profile
        $stmt = $conn->prepare("
            SELECT e.*, t.name_en as team_en, t.name_kh as team_kh, z.name_en as zone_en, z.name_kh as zone_kh 
            FROM `employees` e 
            LEFT JOIN `teams` t ON e.team_id = t.id 
            LEFT JOIN `zones` z ON e.zone_id = z.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        if (!$w) {
            echo "<div class='alert alert-danger'>Worker not found.</div>";
            include __DIR__ . '/includes/footer.php';
            exit();
        }
    ?>
        <!-- View worker profile -->
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="glass-card overflow-hidden">
                    <div class="profile-header" style="background-image: url('<?php echo !empty($w['cover']) ? 'uploads/covers/' . $w['cover'] : 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&w=600&q=80'; ?>');">
                        <div class="profile-avatar-wrapper">
                            <img src="<?php echo !empty($w['photo']) ? 'uploads/profiles/' . $w['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" 
                                 class="profile-avatar" alt="Photo">
                        </div>
                    </div>
                    
                    <div class="p-4 pt-5 mt-4 text-center text-lg-start">
                        <h4 class="fw-bold text-success mb-1">
                            <?php echo htmlspecialchars($w['last_name_en'] . ' ' . $w['first_name_en']); ?>
                        </h4>
                        <h6 class="text-muted mb-3">
                            <?php echo htmlspecialchars($w['last_name_kh'] . ' ' . $w['first_name_kh']); ?>
                        </h6>
                        <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill mb-4"><?php echo __($w['worker_type']); ?> Worker</span>
                        
                        <div class="d-flex flex-column gap-3 text-start text-muted small border-top pt-4">
                            <div>
                                <i class="bi bi-qr-code me-2"></i> Employee Code: <strong><?php echo htmlspecialchars($w['employee_code']); ?></strong>
                            </div>
                            <div>
                                <i class="bi bi-telephone me-2"></i> Phone: <strong><?php echo htmlspecialchars($w['phone'] ?: 'N/A'); ?></strong>
                            </div>
                            <div>
                                <i class="bi bi-envelope me-2"></i> Email: <strong><?php echo htmlspecialchars($w['email'] ?: 'N/A'); ?></strong>
                            </div>
                            <div>
                                <i class="bi bi-gender-ambiguous me-2"></i> Gender: <strong><?php echo __($w['gender']); ?></strong>
                            </div>
                            <div>
                                <i class="bi bi-calendar3 me-2"></i> Joined Date: <strong><?php echo htmlspecialchars($w['date_joined'] ?: 'N/A'); ?></strong>
                            </div>
                        </div>
                        
                        <div class="mt-4 border-top pt-3">
                            <a href="employees.php?action=idcard&id=<?php echo $w['id']; ?>" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-card-heading me-1"></i> Print Staff ID Card
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <!-- Details panel -->
                <div class="glass-card p-4 mb-4">
                    <h5 class="fw-bold text-success mb-3 border-bottom pb-2">Employment & Location details</h5>
                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <span class="text-secondary d-block">Work Team:</span>
                            <strong><?php echo get_current_lang() == 'kh' ? htmlspecialchars($w['team_kh'] ?? 'N/A') : htmlspecialchars($w['team_en'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-secondary d-block">Work Zone / Area:</span>
                            <strong><?php echo get_current_lang() == 'kh' ? htmlspecialchars($w['zone_kh'] ?? 'N/A') : htmlspecialchars($w['zone_en'] ?? 'N/A'); ?></strong>
                        </div>
                    </div>
                </div>
                
                <!-- Payment methods -->
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-3 border-bottom pb-2"><i class="bi bi-credit-card me-2"></i> <?php echo __('payment_info'); ?></h5>
                    
                    <div class="row g-3 small">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-bank me-1"></i> Bank Account Information</h6>
                            <div class="p-3 bg-light rounded border">
                                <p class="mb-1 text-muted">Bank Name:</p>
                                <strong class="d-block mb-2"><?php echo htmlspecialchars($w['bank_name'] ?: 'Not Registered'); ?></strong>
                                <p class="mb-1 text-muted">Account Number:</p>
                                <strong><?php echo htmlspecialchars($w['bank_account'] ?: 'Not Registered'); ?></strong>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-phone me-1"></i> Mobile Money Information</h6>
                            <div class="p-3 bg-light rounded border">
                                <p class="mb-1 text-muted">Mobile Money Provider:</p>
                                <strong class="d-block mb-2"><?php echo htmlspecialchars($w['mobile_money_provider'] ?: 'Not Registered'); ?></strong>
                                <p class="mb-1 text-muted">Mobile Money Number:</p>
                                <strong><?php echo htmlspecialchars($w['mobile_money_number'] ?: 'Not Registered'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (($action === 'add' || $action === 'edit') && $can_manage): 
        $w = null;
        $auto_employee_code = '';
        if ($action === 'edit' && $id) {
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $w = $stmt->fetch();
        } else {
            // Generate auto employee code (e.g. EMP-0001)
            $last_id_stmt = $conn->query("SELECT MAX(id) FROM employees");
            $last_id = intval($last_id_stmt->fetchColumn());
            $next_id = $last_id + 1;
            $auto_employee_code = "EMP-" . str_pad($next_id, 4, "0", STR_PAD_LEFT);
        }
        
        $teams = $conn->query("SELECT * FROM teams")->fetchAll();
        $zones = $conn->query("SELECT * FROM zones")->fetchAll();
    ?>
        <!-- Form Add/Edit worker -->
        <div class="glass-card p-4">
            <h5 class="fw-bold text-success mb-4">
                <i class="bi bi-person-fill-add me-2"></i>
                <?php echo $action === 'add' ? 'Register New Plantation Worker' : 'Edit Worker Registry Profile'; ?>
            </h5>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <h6 class="fw-bold text-success border-bottom pb-2 mb-3">1. Personal Profile Data</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary">Employee Code (Unique)</label>
                        <input type="text" class="form-control" name="employee_code" 
                               value="<?php echo htmlspecialchars($w['employee_code'] ?? $auto_employee_code); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('last_name_en'); ?></label>
                        <input type="text" class="form-control" name="last_name_en" 
                               value="<?php echo htmlspecialchars($w['last_name_en'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('first_name_en'); ?></label>
                        <input type="text" class="form-control" name="first_name_en" 
                               value="<?php echo htmlspecialchars($w['first_name_en'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('last_name_kh'); ?></label>
                        <input type="text" class="form-control" name="last_name_kh" 
                               value="<?php echo htmlspecialchars($w['last_name_kh'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('first_name_kh'); ?></label>
                        <input type="text" class="form-control" name="first_name_kh" 
                               value="<?php echo htmlspecialchars($w['first_name_kh'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('gender'); ?></label>
                        <select class="form-select" name="gender" required>
                            <option value="male" <?php echo (isset($w['gender']) && $w['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($w['gender']) && $w['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('dob'); ?></label>
                        <input type="date" class="form-control" name="dob" 
                               value="<?php echo htmlspecialchars($w['dob'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('phone'); ?></label>
                        <input type="text" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($w['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('email'); ?></label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($w['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary">Worker Photo</label>
                        <input type="file" class="form-control" name="photo" accept="image/*">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-secondary">Cover Photo</label>
                        <input type="file" class="form-control" name="cover" accept="image/*">
                    </div>
                </div>

                <h6 class="fw-bold text-success border-bottom pb-2 mb-3">2. Work Assignment Details</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('worker_type'); ?></label>
                        <select class="form-select" name="worker_type" required>
                            <option value="permanent" <?php echo (isset($w['worker_type']) && $w['worker_type'] === 'permanent') ? 'selected' : ''; ?>>Permanent</option>
                            <option value="seasonal" <?php echo (isset($w['worker_type']) && $w['worker_type'] === 'seasonal') ? 'selected' : ''; ?>>Seasonal</option>
                            <option value="daily" <?php echo (isset($w['worker_type']) && $w['worker_type'] === 'daily') ? 'selected' : ''; ?>>Daily / Casual</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary">Work Team / Group</label>
                        <select class="form-select" name="team_id">
                            <option value="">-- Choose Team --</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" 
                                    <?php echo (isset($w['team_id']) && $w['team_id'] == $team['id']) ? 'selected' : ''; ?>>
                                    <?php echo get_current_lang() == 'kh' ? htmlspecialchars($team['name_kh']) : htmlspecialchars($team['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary">Work Zone / Area</label>
                        <select class="form-select" name="zone_id">
                            <option value="">-- Choose Zone --</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>" 
                                    <?php echo (isset($w['zone_id']) && $w['zone_id'] == $zone['id']) ? 'selected' : ''; ?>>
                                    <?php echo get_current_lang() == 'kh' ? htmlspecialchars($zone['name_kh']) : htmlspecialchars($zone['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('date_joined'); ?></label>
                        <input type="date" class="form-control" name="date_joined" 
                               value="<?php echo htmlspecialchars($w['date_joined'] ?? ''); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-secondary">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo (isset($w['status']) && $w['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($w['status']) && $w['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <h6 class="fw-bold text-success border-bottom pb-2 mb-3">3. Payment & Settlement Options</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Bank Name</label>
                        <input type="text" class="form-control" name="bank_name" placeholder="e.g. ABA Bank"
                               value="<?php echo htmlspecialchars($w['bank_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Bank Account Number</label>
                        <input type="text" class="form-control" name="bank_account" placeholder="e.g. 000 123 456"
                               value="<?php echo htmlspecialchars($w['bank_account'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Mobile Money Provider</label>
                        <input type="text" class="form-control" name="mobile_money_provider" placeholder="e.g. Wing / ABA PAY"
                               value="<?php echo htmlspecialchars($w['mobile_money_provider'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Mobile Account / Phone Number</label>
                        <input type="text" class="form-control" name="mobile_money_number" placeholder="e.g. 099888999"
                               value="<?php echo htmlspecialchars($w['mobile_money_number'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="employees.php" class="btn btn-light border">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Registry</button>
                </div>
            </form>
        </div>
    <?php elseif ($action === 'idcard' && $id): 
        // Fetch detailed profile
        $stmt = $conn->prepare("
            SELECT e.*, t.name_en as team_en, t.name_kh as team_kh, z.name_en as zone_en, z.name_kh as zone_kh 
            FROM `employees` e 
            LEFT JOIN `teams` t ON e.team_id = t.id 
            LEFT JOIN `zones` z ON e.zone_id = z.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $w = $stmt->fetch();
        
        if (!$w) {
            echo "<div class='alert alert-danger'>Worker not found.</div>";
            include __DIR__ . '/includes/footer.php';
            exit();
        }
    ?>
        <style>
        .id-card-container {
            font-family: 'Inter', 'Kantumruy Pro', sans-serif;
            background: #fff;
            width: 320px;
            height: 500px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
            background-color: #fafbfc;
        }
        .id-card-container.back {
            padding: 24px;
            justify-content: space-between;
        }
        .id-card-banner {
            background: linear-gradient(135deg, #1b4d3e 0%, #0f3026 100%);
            color: #fff;
            padding: 16px;
            text-align: center;
            border-bottom: 4px solid #c5a880;
            position: relative;
        }
        .id-card-banner::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: #c5a880;
        }
        .id-card-logo-text {
            font-size: 0.95rem;
            font-weight: 700;
        }
        .id-card-sublogo {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        .id-card-body {
            flex-grow: 1;
            padding: 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .id-card-photo {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 4px solid #1b4d3e;
            object-fit: cover;
            margin-bottom: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .id-card-name-kh {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1b4d3e;
        }
        .id-card-name-en {
            font-size: 0.95rem;
            font-weight: 600;
            color: #718096;
        }
        .id-card-type-badge {
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #e8f5e9;
            color: #1b4d3e;
            padding: 4px 16px;
            border-radius: 30px;
            border: 1px solid #1b4d3e;
            display: inline-block;
        }
        .id-card-details-box {
            width: 100%;
            margin-top: 16px;
            border-top: 1px dashed #e2e8f0;
            padding-top: 12px;
        }
        .id-card-detail-item {
            font-size: 0.65rem;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
        }
        .id-card-detail-val {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1b4d3e;
        }
        .id-card-footer {
            background-color: #1b4d3e;
            padding: 10px;
            text-align: center;
            color: #c5a880;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            #id-card-print-area, #id-card-print-area * {
                visibility: visible;
            }
            #id-card-print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                display: flex;
                justify-content: center;
                gap: 30px !important;
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
        }
        </style>
        
        <div class="row mb-4 no-print">
            <div class="col-12 text-end">
                <button onclick="window.print()" class="btn btn-success px-4 py-2">
                    <i class="bi bi-printer-fill me-2"></i> Print Cards
                </button>
            </div>
        </div>

        <div class="row g-4 justify-content-center" id="id-card-print-area">
            <!-- Front of Card -->
            <div class="col-auto d-flex justify-content-center">
                <div class="id-card-container">
                    <div class="id-card-banner">
                        <i class="bi bi-tree-fill text-warning fs-4 mb-1 d-block"></i>
                        <div class="id-card-logo-text">ក្រុមហ៊ុនចំការកៅស៊ូអង្គរ</div>
                        <div class="id-card-sublogo">ANGKOR RUBBER PLANTATION</div>
                    </div>
                    
                    <div class="id-card-body">
                        <img src="<?php echo !empty($w['photo']) ? 'uploads/profiles/' . $w['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; ?>" class="id-card-photo" alt="Photo">
                        
                        <h4 class="id-card-name-kh mb-1"><?php echo htmlspecialchars($w['last_name_kh'] . ' ' . $w['first_name_kh']); ?></h4>
                        <h6 class="id-card-name-en mb-3"><?php echo htmlspecialchars(strtoupper($w['last_name_en'] . ' ' . $w['first_name_en'])); ?></h6>
                        
                        <div class="mb-2">
                            <span class="id-card-type-badge">
                                <?php echo strtoupper(__($w['worker_type'])); ?> STAFF
                            </span>
                        </div>
                        
                        <div class="id-card-details-box">
                            <div class="id-card-detail-item">Employee Code / អត្តលេខ</div>
                            <div class="id-card-detail-val"><?php echo htmlspecialchars($w['employee_code']); ?></div>
                        </div>
                    </div>
                    
                    <div class="id-card-footer">
                        STAFF ID CARD / កាតសម្គាល់ខ្លួន
                    </div>
                </div>
            </div>
            
            <!-- Back of Card -->
            <div class="col-auto d-flex justify-content-center">
                <div class="id-card-container back">
                    <div>
                        <h6 class="fw-bold text-success border-bottom pb-1 mb-2" style="font-size: 0.85rem;">TERMS OF USE / លក្ខខណ្ឌប្រើប្រាស់</h6>
                        <ul class="text-start text-muted p-0 m-0" style="font-size: 0.65rem; list-style: none; line-height: 1.4;">
                            <li class="mb-2"><i class="bi bi-check2 text-success me-1"></i> This card is property of Angkor Rubber. Please return if found.<br><small class="text-muted">កាតនេះជាកម្មសិទ្ធិរបស់ក្រុមហ៊ុន។ សូមប្រគល់ជូនវិញបើបានរកឃើញ។</small></li>
                            <li class="mb-2"><i class="bi bi-check2 text-success me-1"></i> Must be worn visibly while performing duty at the zones.<br><small class="text-muted">ត្រូវពាក់ឲ្យឃើញច្បាស់លាស់ពេលកំពុងបំពេញការងារក្នុងចំការ។</small></li>
                            <li><i class="bi bi-check2 text-success me-1"></i> Alteration of this card is strictly prohibited.<br><small class="text-muted">ការកែសម្រួលព័ត៌មានលើកាតនេះត្រូវបានហាមឃាត់ដាច់ខាត។</small></li>
                        </ul>
                    </div>
                    
                    <!-- Dynamic QR Code & Employee Code -->
                    <div class="text-center my-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=<?php echo urlencode($w['employee_code']); ?>" class="border p-2 bg-white" style="width: 110px; height: 110px;" alt="QR Code">
                        <div class="text-muted mt-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;"><?php echo htmlspecialchars($w['employee_code']); ?></div>
                    </div>
                    
                    <div class="border-top pt-2 text-start" style="font-size: 0.65rem;">
                        <div class="text-success fw-bold">EMERGENCY CONTACT / ទំនាក់ទំនងបន្ទាន់:</div>
                        <div class="text-muted">Office Tel: +855 23 888 999 | Email: info@angkorrubber.com</div>
                        <div class="text-muted">Address: Kompong Cham Province, Kingdom of Cambodia</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
