<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_payroll');

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$error_msg = '';
$success_msg = '';

// Handle Payroll Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'run') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        $employee_id = $_POST['employee_id'] ?? '';
        $period_start = $_POST['period_start'] ?? '';
        $period_end = $_POST['period_end'] ?? '';
        
        $basic_salary = floatval($_POST['basic_salary'] ?? 0.00);
        $production_wage = floatval($_POST['production_wage'] ?? 0.00);
        $ot_hours = floatval($_POST['ot_hours'] ?? 0.00);
        $ot_amount = floatval($_POST['ot_amount'] ?? 0.00);
        $bonus = floatval($_POST['bonus'] ?? 0.00);
        $allowance = floatval($_POST['allowance'] ?? 0.00);
        $nssf_deduction = floatval($_POST['nssf_deduction'] ?? 0.00);
        $tax_deduction = floatval($_POST['tax_deduction'] ?? 0.00);
        $net_salary = floatval($_POST['net_salary'] ?? 0.00);
        
        $payment_method = $_POST['payment_method'] ?? 'bank';
        $transaction_reference = trim($_POST['transaction_reference'] ?? '');
        $payment_status = $_POST['payment_status'] ?? 'pending';
        $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;

        if (empty($employee_id) || empty($period_start) || empty($period_end)) {
            $error_msg = 'Please complete all required fields.';
        } else {
            // Save to DB
            $stmt = $conn->prepare("
                INSERT INTO `payroll` 
                (`employee_id`, `period_start`, `period_end`, `basic_salary`, `production_wage`, `ot_hours`, `ot_amount`, `bonus`, `allowance`, `nssf_deduction`, `tax_deduction`, `net_salary`, `payment_status`, `payment_method`, `payment_date`, `transaction_reference`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $employee_id, $period_start, $period_end, $basic_salary, $production_wage, $ot_hours, $ot_amount, $bonus, $allowance, $nssf_deduction, $tax_deduction, $net_salary, $payment_status, $payment_method, $payment_date, $transaction_reference
            ])) {
                log_action('generate_payroll', "Generated payroll for employee ID: $employee_id from $period_start to $period_end");
                set_alert('success', 'Payroll calculated and saved successfully!');
                header("Location: payroll.php");
                exit();
            } else {
                $error_msg = 'Failed to generate payroll record.';
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
                <i class="bi bi-cash-stack me-2"></i> <?php echo __('payroll'); ?>
            </h4>
            <?php if ($action === 'list'): ?>
                <a href="payroll.php?action=run" class="btn btn-primary">
                    <i class="bi bi-calculator me-1"></i> Run Payroll
                </a>
            <?php else: ?>
                <a href="payroll.php" class="btn btn-light border">
                    <i class="bi bi-arrow-left me-1"></i> Back to Payroll
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
        // Fetch payroll runs
        $slips = $conn->query("
            SELECT p.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh
            FROM `payroll` p
            JOIN `employees` e ON p.employee_id = e.id
            ORDER BY p.created_at DESC
        ")->fetchAll();
    ?>
        <!-- Payroll list -->
        <div class="glass-card p-4">
            <?php if (empty($slips)): ?>
                <p class="text-muted text-center py-4">No payroll runs found. Click "Run Payroll" to generate salary slips.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Period</th>
                                <th>Basic Salary</th>
                                <th>Productivity Pay</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slips as $s): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold">
                                            <?php echo get_current_lang() == 'kh' 
                                                ? htmlspecialchars($s['last_name_kh'] . ' ' . $s['first_name_kh']) 
                                                : htmlspecialchars($s['last_name_en'] . ' ' . $s['first_name_en']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($s['employee_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold"><?php echo $s['period_start']; ?> to <?php echo $s['period_end']; ?></span>
                                    </td>
                                    <td><?php echo format_money($s['basic_salary']); ?></td>
                                    <td><?php echo format_money($s['production_wage']); ?></td>
                                    <td class="fw-bold text-success"><?php echo format_money($s['net_salary']); ?></td>
                                    <td>
                                        <span class="badge badge-status <?php echo $s['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo htmlspecialchars($s['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="payroll.php?action=payslip&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-light border text-success">
                                            <i class="bi bi-printer me-1"></i> Payslip
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'run'): 
        // Payroll calculator
        $workers = $conn->query("SELECT id, employee_code, first_name_en, last_name_en, first_name_kh, last_name_kh FROM employees WHERE status = 'active'")->fetchAll();
        
        // Calculate parameters if employee selected via GET
        $sel_emp_id = $_GET['calc_emp_id'] ?? '';
        $calc_start = $_GET['calc_start'] ?? date('Y-m-01');
        $calc_end = $_GET['calc_end'] ?? date('Y-m-t');
        
        $c_basic_salary = 0.00;
        $c_production_wage = 0.00;
        $c_present_days = 0;
        $c_total_latex = 0.00;
        $c_contract_daily = 0.00;
        $c_contract_rate_kg = 0.00;
        
        if (!empty($sel_emp_id)) {
            // Get active contract details
            $c_stmt = $conn->prepare("
                SELECT basic_rate_daily, production_rate_per_kg 
                FROM contracts 
                WHERE employee_id = ? AND status = 'active' 
                LIMIT 1
            ");
            $c_stmt->execute([$sel_emp_id]);
            $contract = $c_stmt->fetch();
            
            $c_contract_daily = floatval($contract['basic_rate_daily'] ?? 15.00); // default $15/day
            $c_contract_rate_kg = floatval($contract['production_rate_per_kg'] ?? 0.35); // default $0.35/kg
            
            // Get present days & latex yield
            $att_stmt = $conn->prepare("
                SELECT COUNT(*) as present_days, SUM(latex_kg) as total_latex 
                FROM attendance 
                WHERE employee_id = ? AND date BETWEEN ? AND ? AND status = 'present'
            ");
            $att_stmt->execute([$sel_emp_id, $calc_start, $calc_end]);
            $att_stats = $att_stmt->fetch();
            
            $c_present_days = intval($att_stats['present_days'] ?? 0);
            $c_total_latex = floatval($att_stats['total_latex'] ?? 0.00);
            
            // Perform basic calculations
            $c_basic_salary = $c_present_days * $c_contract_daily;
            $c_production_wage = $c_total_latex * $c_contract_rate_kg;
        }
    ?>
        <!-- Run calculator form -->
        <div class="row g-4">
            <div class="col-md-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-3">1. Retrieve Field Records</h5>
                    
                    <form action="" method="GET" class="d-flex flex-column gap-3">
                        <input type="hidden" name="action" value="run">
                        <div>
                            <label class="form-label fw-bold small text-secondary">Select Worker</label>
                            <select class="form-select" name="calc_emp_id" required>
                                <option value="">-- Choose Worker --</option>
                                <?php foreach ($workers as $w): ?>
                                    <option value="<?php echo $w['id']; ?>" <?php echo $sel_emp_id == $w['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($w['employee_code'] . ' - ' . $w['last_name_en'] . ' ' . $w['first_name_en']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary">Start Date</label>
                                <input type="date" class="form-control" name="calc_start" value="<?php echo htmlspecialchars($calc_start); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary">End Date</label>
                                <input type="date" class="form-control" name="calc_end" value="<?php echo htmlspecialchars($calc_end); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary w-100 mt-2">
                            <i class="bi bi-search me-1"></i> Retrieve Days & Yield
                        </button>
                    </form>
                    
                    <?php if (!empty($sel_emp_id)): ?>
                        <hr>
                        <h6 class="fw-bold text-success">Retrieved Statistics:</h6>
                        <div class="p-3 bg-light rounded border small">
                            <p class="mb-1">Present Days: <strong><?php echo $c_present_days; ?> Days</strong> (Rate: <?php echo format_money($c_contract_daily); ?>/day)</p>
                            <p class="mb-1">Latex Collected: <strong><?php echo number_format($c_total_latex, 2); ?> kg</strong> (Rate: <?php echo format_money($c_contract_rate_kg); ?>/kg)</p>
                            <p class="mb-1">Computed Base Pay: <strong><?php echo format_money($c_basic_salary); ?></strong></p>
                            <p class="mb-0">Computed Yield Pay: <strong><?php echo format_money($c_production_wage); ?></strong></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-7">
                <div class="glass-card p-4">
                    <h5 class="fw-bold text-success mb-3">2. Salary Computation Sheet</h5>
                    
                    <form action="" method="POST" id="payrollForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($sel_emp_id); ?>">
                        <input type="hidden" name="period_start" value="<?php echo htmlspecialchars($calc_start); ?>">
                        <input type="hidden" name="period_end" value="<?php echo htmlspecialchars($calc_end); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Basic Base Pay ($)</label>
                                <input type="number" step="0.01" class="form-control" name="basic_salary" id="basic_salary" value="<?php echo $c_basic_salary; ?>" required readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Latex Productivity Pay ($)</label>
                                <input type="number" step="0.01" class="form-control" name="production_wage" id="production_wage" value="<?php echo $c_production_wage; ?>" required readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">OT Hours</label>
                                <input type="number" step="0.5" class="form-control calc-trigger" name="ot_hours" id="ot_hours" value="0.0">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">OT Pay Amount ($)</label>
                                <input type="number" step="0.01" class="form-control calc-trigger" name="ot_amount" id="ot_amount" value="0.00">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Allowances ($)</label>
                                <input type="number" step="0.01" class="form-control calc-trigger" name="allowance" id="allowance" value="0.00">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Bonuses ($)</label>
                                <input type="number" step="0.01" class="form-control calc-trigger" name="bonus" id="bonus" value="0.00">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">NSSF Contribution Deduction ($)</label>
                                <input type="number" step="0.01" class="form-control calc-trigger" name="nssf_deduction" id="nssf_deduction" value="<?php echo round($c_basic_salary * 0.019, 2); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Tax Deductions ($)</label>
                                <input type="number" step="0.01" class="form-control calc-trigger" name="tax_deduction" id="tax_deduction" value="0.00">
                            </div>

                            <div class="col-12">
                                <div class="bg-success-subtle p-3 rounded border border-success d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 text-success fw-bold">Net Salary Pay:</h5>
                                    <h3 class="mb-0 text-success fw-bold" id="net_salary_display">$0.00</h3>
                                    <input type="hidden" name="net_salary" id="net_salary" value="0.00">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Payment Method</label>
                                <select class="form-select" name="payment_method">
                                    <option value="bank">Bank Transfer (ABA)</option>
                                    <option value="mobile_money">Mobile Money (Wing)</option>
                                    <option value="cash">Cash Settlement</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Ref / Transaction ID</label>
                                <input type="text" class="form-control" name="transaction_reference" placeholder="e.g. TXN-887766">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Payment Date</label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-secondary">Payout Status</label>
                                <select class="form-select" name="payment_status">
                                    <option value="pending">Pending Settlement</option>
                                    <option value="paid" selected>Completed / Paid</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary w-100 py-2" <?php echo empty($sel_emp_id) ? 'disabled' : ''; ?>>
                                <i class="bi bi-wallet2 me-2"></i> Save and Generate Payslip
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const triggers = document.querySelectorAll(".calc-trigger");
            const basicInput = document.getElementById("basic_salary");
            const prodInput = document.getElementById("production_wage");
            const otInput = document.getElementById("ot_amount");
            const allowanceInput = document.getElementById("allowance");
            const bonusInput = document.getElementById("bonus");
            const nssfInput = document.getElementById("nssf_deduction");
            const taxInput = document.getElementById("tax_deduction");
            const netDisplay = document.getElementById("net_salary_display");
            const netHidden = document.getElementById("net_salary");

            function calculateNet() {
                const basic = parseFloat(basicInput.value) || 0;
                const prod = parseFloat(prodInput.value) || 0;
                const ot = parseFloat(otInput.value) || 0;
                const allowance = parseFloat(allowanceInput.value) || 0;
                const bonus = parseFloat(bonusInput.value) || 0;
                const nssf = parseFloat(nssfInput.value) || 0;
                const tax = parseFloat(taxInput.value) || 0;

                const net = (basic + prod + ot + allowance + bonus) - (nssf + tax);
                const rounded = Math.max(0, net).toFixed(2);
                netDisplay.textContent = '$' + rounded;
                netHidden.value = rounded;
            }

            triggers.forEach(t => t.addEventListener("input", calculateNet));
            calculateNet(); // Initial run
        });
        </script>

    <?php elseif ($action === 'payslip' && $id): 
        // Fetch detailed payslip
        $stmt = $conn->prepare("
            SELECT p.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh, e.worker_type,
                   t.name_en as team_en, t.name_kh as team_kh, z.name_en as zone_en, z.name_kh as zone_kh
            FROM `payroll` p
            JOIN `employees` e ON p.employee_id = e.id
            LEFT JOIN `teams` t ON e.team_id = t.id
            LEFT JOIN `zones` z ON e.zone_id = z.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        
        if (!$s) {
            echo "<p>Payslip not found.</p>";
            include __DIR__ . '/includes/footer.php';
            exit();
        }
    ?>
        <!-- Printable payslip layout -->
        <div class="glass-card p-5" id="printablePayslip">
            <!-- Header -->
            <div class="row align-items-center mb-4 border-bottom pb-4">
                <div class="col-8">
                    <h4 class="fw-bold text-success mb-1">Angkor Rubber Plantation Co., Ltd.</h4>
                    <h6 class="text-secondary mb-1">ក្រុមហ៊ុនចំការកៅស៊ូអង្គរ ឯ.ក</h6>
                    <small class="text-muted">Kompong Cham Province, Cambodia | NSSF-99887766</small>
                </div>
                <div class="col-4 text-end">
                    <h5 class="fw-bold text-success mb-0"><?php echo __('generate_slip'); ?></h5>
                    <p class="text-muted mb-0 small">PAYSLIP SHEET</p>
                </div>
            </div>

            <!-- Details -->
            <div class="row mb-4 bg-light p-3 rounded border">
                <div class="col-md-6 mb-2">
                    <span class="text-muted small d-block">Employee Name / ឈ្មោះកម្មករ:</span>
                    <strong class="text-success">
                        <?php echo htmlspecialchars($s['last_name_kh'] . ' ' . $s['first_name_kh']); ?> 
                        (<?php echo htmlspecialchars($s['last_name_en'] . ' ' . $s['first_name_en']); ?>)
                    </strong>
                </div>
                <div class="col-md-6 mb-2">
                    <span class="text-muted small d-block">Employee ID / អត្តលេខ:</span>
                    <strong><?php echo htmlspecialchars($s['employee_code']); ?></strong>
                </div>
                <div class="col-md-6 mb-2">
                    <span class="text-muted small d-block">Work Team & Zone / ក្រុមការងារ & ចំការ:</span>
                    <strong>
                        <?php echo htmlspecialchars($s['team_kh'] . ' (' . $s['zone_kh'] . ')'); ?>
                    </strong>
                </div>
                <div class="col-md-6 mb-2">
                    <span class="text-muted small d-block">Pay Period / វដ្តទូទាត់:</span>
                    <strong><?php echo $s['period_start']; ?> to <?php echo $s['period_end']; ?></strong>
                </div>
            </div>

            <!-- Income vs Deductions table -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <h6 class="fw-bold text-success border-bottom pb-2">EARNINGS / ប្រាក់ចំណូល</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Basic Base Salary / ប្រាក់ខែគោល:</span>
                        <strong><?php echo format_money($s['basic_salary']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Productivity Pay / ប្រាក់ទឹកជ័រ:</span>
                        <strong><?php echo format_money($s['production_wage']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>OT Pay / ប្រាក់ម៉ោងបន្ថែម:</span>
                        <strong><?php echo format_money($s['ot_amount']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Allowances / ប្រាក់ឧបត្ថម្ភ:</span>
                        <strong><?php echo format_money($s['allowance']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Bonuses / ប្រាក់រង្វាន់:</span>
                        <strong><?php echo format_money($s['bonus']); ?></strong>
                    </div>
                </div>

                <div class="col-md-6 border-start">
                    <h6 class="fw-bold text-danger border-bottom pb-2">DEDUCTIONS / ការកាត់កង</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>NSSF Contribution / ភាគទាន ប.ស.ស.:</span>
                        <strong><?php echo format_money($s['nssf_deduction']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Salary Tax / ពន្ធលើប្រាក់បៀវត្សរ៍:</span>
                        <strong><?php echo format_money($s['tax_deduction']); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Settlement net -->
            <div class="bg-success-subtle p-3 rounded border border-success d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="mb-0 text-success fw-bold">Net Salary Payout / ទឹកប្រាក់ទទួលបានសរុប:</h5>
                    <small class="text-muted"><?php echo __('payment_method'); ?>: <?php echo htmlspecialchars($s['payment_method']); ?> | Ref: <?php echo htmlspecialchars($s['transaction_reference'] ?: 'N/A'); ?></small>
                </div>
                <div class="text-end">
                    <h3 class="mb-0 text-success fw-bold"><?php echo format_money($s['net_salary']); ?></h3>
                    <h5 class="mb-0 text-success fw-bold"><?php echo format_riel($s['net_salary']); ?></h5>
                </div>
            </div>

            <!-- Signatures -->
            <div class="row mt-5 pt-4 text-center">
                <div class="col-6">
                    <p class="mb-5 small text-secondary">Employer Representative / អ្នកបើកប្រាក់</p>
                    <div class="border-top mx-auto" style="width: 150px;"></div>
                </div>
                <div class="col-6">
                    <p class="mb-5 small text-secondary">Worker Signature / ហត្ថលេខាកម្មករ</p>
                    <div class="border-top mx-auto" style="width: 150px;"></div>
                </div>
            </div>
        </div>

        <div class="text-end mt-4">
            <button onclick="window.print()" class="btn btn-success px-4 py-2">
                <i class="bi bi-printer-fill me-2"></i> Print Payslip
            </button>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
