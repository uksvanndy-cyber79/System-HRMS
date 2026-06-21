<?php
require_once __DIR__ . '/config/functions.php';
check_permission('record_attendance');

$error_msg = '';
$success_msg = '';

// Handle AJAX Scan Process
if (isset($_GET['action']) && $_GET['action'] === 'process_scan') {
    header('Content-Type: application/json');
    
    $employee_code = trim($_POST['employee_code'] ?? '');
    
    if (empty($employee_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty QR Code scanned.']);
        exit();
    }
    
    // Find worker
    $stmt = $conn->prepare("SELECT * FROM `employees` WHERE `employee_code` = ? AND `status` = 'active'");
    $stmt->execute([$employee_code]);
    $worker = $stmt->fetch();
    
    if (!$worker) {
        echo json_encode(['status' => 'error', 'message' => 'No active worker found with code: ' . htmlspecialchars($employee_code)]);
        exit();
    }
    
    $employee_id = $worker['id'];
    $today = date('Y-m-d');
    $now_time = date('H:i:s');
    
    // Check if attendance already exists for today
    $att_stmt = $conn->prepare("SELECT * FROM `attendance` WHERE `employee_id` = ? AND `date` = ?");
    $att_stmt->execute([$employee_id, $today]);
    $att = $att_stmt->fetch();
    
    $recorded_by = $_SESSION['user_id'];
    
    if (!$att) {
        // 1. Initial Check-In
        $ins = $conn->prepare("
            INSERT INTO `attendance` (`employee_id`, `date`, `status`, `check_in`, `recorded_by`) 
            VALUES (?, ?, 'present', ?, ?)
        ");
        if ($ins->execute([$employee_id, $today, $now_time, $recorded_by])) {
            log_action('qr_check_in', "Checked in worker: {$worker['last_name_en']} ({$worker['employee_code']}) via QR scan");
            echo json_encode([
                'status' => 'success',
                'scan_type' => 'check_in',
                'time' => date('H:i A', strtotime($now_time)),
                'employee' => [
                    'id' => $worker['id'],
                    'code' => $worker['employee_code'],
                    'name_en' => $worker['last_name_en'] . ' ' . $worker['first_name_en'],
                    'name_kh' => $worker['last_name_kh'] . ' ' . $worker['first_name_kh'],
                    'photo' => !empty($worker['photo']) ? 'uploads/profiles/' . $worker['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
                ]
            ]);
            exit();
        }
    } else {
        // 2. Check-Out
        if (empty($att['check_out'])) {
            $upd = $conn->prepare("
                UPDATE `attendance` 
                SET `check_out` = ?, `recorded_by` = ? 
                WHERE `id` = ?
            ");
            if ($upd->execute([$now_time, $recorded_by, $att['id']])) {
                log_action('qr_check_out', "Checked out worker: {$worker['last_name_en']} ({$worker['employee_code']}) via QR scan");
                echo json_encode([
                    'status' => 'success',
                    'scan_type' => 'check_out',
                    'time' => date('H:i A', strtotime($now_time)),
                    'employee' => [
                        'id' => $worker['id'],
                        'code' => $worker['employee_code'],
                        'name_en' => $worker['last_name_en'] . ' ' . $worker['first_name_en'],
                        'name_kh' => $worker['last_name_kh'] . ' ' . $worker['first_name_kh'],
                        'photo' => !empty($worker['photo']) ? 'uploads/profiles/' . $worker['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
                    ]
                ]);
                exit();
            }
        } else {
            // Already checked out
            echo json_encode([
                'status' => 'info',
                'message' => 'Already checked out today.',
                'employee' => [
                    'id' => $worker['id'],
                    'code' => $worker['employee_code'],
                    'name_en' => $worker['last_name_en'] . ' ' . $worker['first_name_en'],
                    'name_kh' => $worker['last_name_kh'] . ' ' . $worker['first_name_kh'],
                    'photo' => !empty($worker['photo']) ? 'uploads/profiles/' . $worker['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
                ]
            ]);
            exit();
        }
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Operation failed. Please try again.']);
    exit();
}

// Handle Yield Update from Scanning Panel
if (isset($_GET['action']) && $_GET['action'] === 'save_yield') {
    header('Content-Type: application/json');
    $employee_id = $_POST['employee_id'] ?? '';
    $latex_kg = floatval($_POST['latex_kg'] ?? 0.00);
    $trees_tapped = intval($_POST['trees_tapped'] ?? 0);
    
    if (empty($employee_id)) {
        echo json_encode(['status' => 'error', 'message' => 'No worker selected.']);
        exit();
    }
    
    $today = date('Y-m-d');
    
    // Update yield in today's attendance record
    $stmt = $conn->prepare("
        UPDATE `attendance` 
        SET `latex_kg` = ?, `trees_tapped` = ? 
        WHERE `employee_id` = ? AND `date` = ?
    ");
    
    if ($stmt->execute([$latex_kg, $trees_tapped, $employee_id, $today])) {
        log_action('qr_save_yield', "Recorded yield ($latex_kg kg, $trees_tapped trees) for worker ID: $employee_id via QR scanner");
        echo json_encode(['status' => 'success', 'message' => 'Productivity logs saved successfully!']);
        exit();
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Failed to save yield.']);
    exit();
}

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-success">
                <i class="bi bi-qr-code-scan me-2"></i> Scan QR Code Attendance
            </h4>
            <a href="attendance.php" class="btn btn-light border">
                <i class="bi bi-arrow-left me-1"></i> Back to Attendance
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Scanner Viewport Card -->
        <div class="col-lg-5">
            <div class="glass-card p-4 text-center">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-camera-fill me-1"></i> Field Scanner Camera</h5>
                
                <div class="p-2 bg-black rounded border border-secondary mb-3 overflow-hidden position-relative" style="max-width: 100%; height: 300px; display: flex; justify-content: center; align-items: center;">
                    <div id="reader" style="width: 100%; height: 100%;"></div>
                    <!-- Mock scan overlay line -->
                    <div class="position-absolute w-100 bg-success border-top border-success border-2" style="height: 2px; top: 50%; left: 0; animation: scanAnim 2s linear infinite; box-shadow: 0 0 8px #198754; opacity: 0.8; pointer-events: none;"></div>
                </div>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <button id="btnStart" class="btn btn-primary btn-sm"><i class="bi bi-play-fill me-1"></i> Start Camera</button>
                    <button id="btnStop" class="btn btn-danger btn-sm" disabled><i class="bi bi-stop-fill me-1"></i> Stop Camera</button>
                </div>
                
                <div class="p-3 bg-light rounded text-muted small text-start border">
                    <i class="bi bi-info-circle me-1"></i> <strong>Instructions:</strong> Position the employee ID card QR code in front of the camera. The system will scan, register check-in/out, and prompt you to input their latex yield output.
                </div>
            </div>
        </div>

        <!-- Scan Result & Interactive Logs Panel -->
        <div class="col-lg-7">
            <!-- Scan Status / Yield Entry Modal-like Card -->
            <div class="glass-card p-4 mb-4 d-none" id="resultPanel">
                <div class="d-flex align-items-center gap-3 border-bottom pb-3 mb-3">
                    <img id="resPhoto" src="" alt="Avatar" class="rounded-circle border" style="width: 70px; height: 70px; object-fit: cover;">
                    <div>
                        <h4 class="fw-bold text-success mb-0" id="resName">Somnang Mao</h4>
                        <div class="badge bg-secondary mb-1" id="resCode">EMP-0004</div>
                        <div class="text-muted small" id="resTime">Scan Time: 11:32 AM</div>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="badge px-3 py-2 rounded-pill fs-6" id="resTypeBadge">CHECKED IN</div>
                    </div>
                </div>

                <!-- Yield Form -->
                <h6 class="fw-bold text-success mb-3"><i class="bi bi-bucket-fill me-1"></i> Enter Latex Yield (Optional)</h6>
                <form id="yieldForm" action="" method="POST" class="row g-3">
                    <input type="hidden" name="employee_id" id="resEmployeeId" value="">
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Latex Weight (kg)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" class="form-control" name="latex_kg" id="latex_kg" placeholder="e.g. 15.50" required>
                            <span class="input-group-text">kg</span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-secondary">Trees Tapped</label>
                        <input type="number" class="form-control" name="trees_tapped" id="trees_tapped" placeholder="e.g. 120" required>
                    </div>
                    
                    <div class="col-12 text-end">
                        <button type="button" id="btnSaveYield" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Save Yield & Continue
                        </button>
                    </div>
                </form>
            </div>

            <!-- Recent Scans log -->
            <div class="glass-card p-4">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-clock-history me-1"></i> Today's Scanned Log</h5>
                <div class="table-responsive">
                    <table class="table table-hover border small" id="scansTable">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Latex (kg)</th>
                                <th>Tapped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $today = date('Y-m-d');
                            $today_scans = $conn->query("
                                SELECT a.*, e.employee_code, e.first_name_en, e.last_name_en, e.first_name_kh, e.last_name_kh
                                FROM `attendance` a
                                JOIN `employees` e ON a.employee_id = e.id
                                WHERE a.date = '{$today}'
                                ORDER BY a.check_out DESC, a.check_in DESC
                            ")->fetchAll();
                            
                            if (empty($today_scans)):
                            ?>
                                <tr id="no-scans-row"><td colspan="7" class="text-muted text-center py-3">No scans recorded yet today.</td></tr>
                            <?php else: 
                                foreach ($today_scans as $scan):
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($scan['last_name_en'] . ' ' . $scan['first_name_en']); ?></strong>
                                    </td>
                                    <td class="text-success fw-bold"><?php echo htmlspecialchars($scan['employee_code']); ?></td>
                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($scan['status']); ?></span></td>
                                    <td class="fw-semibold text-muted"><?php echo $scan['check_in'] ? date('h:i A', strtotime($scan['check_in'])) : '-'; ?></td>
                                    <td class="fw-semibold text-muted"><?php echo $scan['check_out'] ? date('h:i A', strtotime($scan['check_out'])) : '-'; ?></td>
                                    <td class="fw-bold text-primary"><?php echo number_format($scan['latex_kg'], 2); ?> kg</td>
                                    <td><?php echo number_format($scan['trees_tapped']); ?></td>
                                </tr>
                            <?php endforeach; 
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Animation Keyframes for scanner overlay line -->
<style>
@keyframes scanAnim {
    0% { top: 0%; }
    50% { top: 98%; }
    100% { top: 0%; }
}
</style>

<!-- HTML5-QRCode Scanner Library CDN -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let html5QrcodeScanner = null;
    const readerElement = document.getElementById('reader');
    const btnStart = document.getElementById('btnStart');
    const btnStop = document.getElementById('btnStop');
    
    const resultPanel = document.getElementById('resultPanel');
    const resPhoto = document.getElementById('resPhoto');
    const resName = document.getElementById('resName');
    const resCode = document.getElementById('resCode');
    const resTime = document.getElementById('resTime');
    const resTypeBadge = document.getElementById('resTypeBadge');
    const resEmployeeId = document.getElementById('resEmployeeId');
    const latexInput = document.getElementById('latex_kg');
    const treesInput = document.getElementById('trees_tapped');
    const scansTableBody = document.getElementById('scansTable').querySelector('tbody');
    
    // Play alert sound on success
    function playBeep() {
        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.type = 'sine';
            oscillator.frequency.value = 880; // High beep pitch
            gain.gain.setValueAtTime(0.1, context.currentTime);
            oscillator.start();
            oscillator.stop(context.currentTime + 0.15); // Beep duration
        } catch (e) {
            console.error("Audio error: ", e);
        }
    }
    
    function startScanner() {
        html5QrcodeScanner = new Html5Qrcode("reader");
        
        html5QrcodeScanner.start(
            { facingMode: "environment" }, // Rear camera
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            onScanSuccess,
            onScanError
        ).then(() => {
            btnStart.disabled = true;
            btnStop.disabled = false;
        }).catch(err => {
            alert("Camera access denied or no camera found: " + err);
        });
    }
    
    function stopScanner() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner = null;
                btnStart.disabled = false;
                btnStop.disabled = true;
            }).catch(err => console.error("Error stopping scanner: ", err));
        }
    }
    
    function onScanSuccess(decodedText, decodedResult) {
        // Pause scanner briefly to prevent multiple scans
        stopScanner();
        playBeep();
        
        // Vibrate mobile device
        if (navigator.vibrate) {
            navigator.vibrate(100);
        }
        
        processQRResult(decodedText);
    }
    
    function onScanError(errorMessage) {
        // Quietly fail, normal for scanning loops
    }
    
    function processQRResult(codeText) {
        // Send AJAX Request
        const formData = new FormData();
        formData.append('employee_code', codeText);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');
        
        fetch('scan-qr.php?action=process_scan', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' || data.status === 'info') {
                const emp = data.employee;
                
                // Show Result Panel
                resultPanel.classList.remove('d-none');
                resPhoto.src = emp.photo;
                resName.textContent = emp.name_en + ' (' + emp.name_kh + ')';
                resCode.textContent = emp.code;
                resEmployeeId.value = emp.id;
                resTime.textContent = 'Scan Time: ' + (data.time || new Date().toLocaleTimeString());
                
                // Reset yield fields
                latexInput.value = '';
                treesInput.value = '';
                
                if (data.scan_type === 'check_in') {
                    resTypeBadge.textContent = 'CHECKED IN';
                    resTypeBadge.className = 'badge bg-success px-3 py-2 rounded-pill fs-6';
                } else if (data.scan_type === 'check_out') {
                    resTypeBadge.textContent = 'CHECKED OUT';
                    resTypeBadge.className = 'badge bg-info text-dark px-3 py-2 rounded-pill fs-6';
                } else {
                    resTypeBadge.textContent = 'ALREADY SCAN';
                    resTypeBadge.className = 'badge bg-warning text-dark px-3 py-2 rounded-pill fs-6';
                }
                
                // Refresh log table
                refreshLogsTable();
                
            } else {
                alert("Error: " + data.message);
                // Restart scanner if error
                startScanner();
            }
        })
        .catch(err => {
            console.error(err);
            alert("Connection error occurred.");
            startScanner();
        });
    }
    
    function refreshLogsTable() {
        fetch('reports.php') // we can scrape or fetch logs, but easier is to query scan logs or reload table
        .then(() => {
            // Simply refresh the page table via simple ajax query to scan logs endpoint in future, 
            // or we just reload page after saving yield. We will reload page after saving yield.
        });
    }
    
    // Save yield output
    document.getElementById('btnSaveYield').addEventListener('click', function() {
        const empId = resEmployeeId.value;
        const latex = latexInput.value || 0.00;
        const trees = treesInput.value || 0;
        
        const formData = new FormData();
        formData.append('employee_id', empId);
        formData.append('latex_kg', latex);
        formData.append('trees_tapped', trees);
        
        fetch('scan-qr.php?action=save_yield', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Hide panel and restart scanner
                resultPanel.classList.add('d-none');
                startScanner();
                
                // Reload page to refresh logs table cleanly and visually
                window.location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            alert("Error saving productivity logs: " + err);
        });
    });
    
    btnStart.addEventListener('click', startScanner);
    btnStop.addEventListener('click', stopScanner);
    
    // Auto-start scanner on load
    startScanner();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
