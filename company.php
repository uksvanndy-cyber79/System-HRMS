<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_company');

$error_msg = '';
$success_msg = '';

// Load company details (id = 1 is the default Angkor Rubber Co)
$company_id = 1;
$stmt = $conn->prepare("SELECT * FROM `companies` WHERE `id` = ?");
$stmt->execute([$company_id]);
$comp = $stmt->fetch();

// Handle form updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error_msg = 'Invalid CSRF token.';
    } else {
        // 1. Update Company Profile Details
        if (isset($_POST['update_profile'])) {
            $name_en = trim($_POST['name_en'] ?? '');
            $name_kh = trim($_POST['name_kh'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $vat_number = trim($_POST['vat_number'] ?? '');
            $nssf_number = trim($_POST['nssf_number'] ?? '');
            
            $logo_name = $comp['logo'];
            
            // Handle logo upload
            if (has_posted_file('logo')) {
                $upload = upload_file($_FILES['logo'], __DIR__ . '/uploads/logos/');
                if ($upload['status']) {
                    $logo_name = $upload['filename'];
                } else {
                    $error_msg = $upload['message'];
                }
            }
            
            if (empty($error_msg)) {
                if (empty($name_en) || empty($name_kh)) {
                    $error_msg = 'Company names are required.';
                } else {
                    $upd = $conn->prepare("
                        UPDATE `companies` 
                        SET `name_en` = ?, `name_kh` = ?, `logo` = ?, `phone` = ?, `email` = ?, `address` = ?, `vat_number` = ?, `nssf_number` = ? 
                        WHERE `id` = ?
                    ");
                    if ($upd->execute([$name_en, $name_kh, $logo_name, $phone, $email, $address, $vat_number, $nssf_number, $company_id])) {
                        log_action('update_company_profile', "Updated company profile details");
                        set_alert('success', 'Company profile updated successfully!');
                        header("Location: company.php");
                        exit();
                    } else {
                        $error_msg = 'Failed to update company profile.';
                    }
                }
            }
        }
        
        // 2. Upload Company Document
        if (isset($_POST['upload_doc'])) {
            $doc_name = trim($_POST['doc_name'] ?? '');
            
            if (empty($doc_name)) {
                $error_msg = 'Document title is required.';
            } elseif (!has_posted_file('doc_file')) {
                $error_msg = 'Please choose a document file to upload.';
            } else {
                $upload = upload_file($_FILES['doc_file'], __DIR__ . '/uploads/documents/', ['pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg']);
                if ($upload['status']) {
                    $file_path = $upload['filename'];
                    $ins = $conn->prepare("INSERT INTO `company_documents` (`company_id`, `doc_name`, `file_path`) VALUES (?, ?, ?)");
                    if ($ins->execute([$company_id, $doc_name, $file_path])) {
                        log_action('upload_company_document', "Uploaded company document: $doc_name");
                        set_alert('success', 'Document uploaded successfully!');
                        header("Location: company.php");
                        exit();
                    } else {
                        $error_msg = 'Failed to record document details.';
                    }
                } else {
                    $error_msg = $upload['message'];
                }
            }
        }
    }
}

// Delete Document Action
if (isset($_GET['delete_doc_id'])) {
    $doc_id = $_GET['delete_doc_id'];
    // Get file path to delete from server
    $d_stmt = $conn->prepare("SELECT file_path FROM company_documents WHERE id = ?");
    $d_stmt->execute([$doc_id]);
    $file_to_delete = $d_stmt->fetchColumn();
    
    if ($file_to_delete) {
        $full_path = __DIR__ . '/uploads/documents/' . $file_to_delete;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
    }
    
    $del = $conn->prepare("DELETE FROM `company_documents` WHERE `id` = ?");
    if ($del->execute([$doc_id])) {
        log_action('delete_company_document', "Deleted company document ID: $doc_id");
        set_alert('success', 'Document deleted successfully!');
    } else {
        set_alert('danger', 'Failed to delete document.');
    }
    header("Location: company.php");
    exit();
}

// Fetch all documents
$docs = $conn->prepare("SELECT * FROM `company_documents` WHERE `company_id` = ? ORDER BY `uploaded_at` DESC");
$docs->execute([$company_id]);
$documents = $docs->fetchAll();

include __DIR__ . '/includes/header.php';
$csrf_token = generate_csrf_token();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="fw-bold text-success">
                <i class="bi bi-building me-2"></i> <?php echo __('company'); ?>
            </h4>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Edit Profile Panel -->
        <div class="col-lg-7">
            <div class="glass-card p-4">
                <h5 class="fw-bold text-success mb-4"><i class="bi bi-gear-fill me-2"></i> <?php echo __('company_info'); ?></h5>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('company_name_en'); ?></label>
                            <input type="text" class="form-control" name="name_en" value="<?php echo htmlspecialchars($comp['name_en'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('company_name_kh'); ?></label>
                            <input type="text" class="form-control" name="name_kh" value="<?php echo htmlspecialchars($comp['name_kh'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($comp['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('email'); ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($comp['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('vat_number'); ?></label>
                            <input type="text" class="form-control" name="vat_number" value="<?php echo htmlspecialchars($comp['vat_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('nssf_number'); ?></label>
                            <input type="text" class="form-control" name="nssf_number" value="<?php echo htmlspecialchars($comp['nssf_number'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('address'); ?></label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($comp['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-secondary"><?php echo __('logo'); ?></label>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($comp['logo'])): ?>
                                    <img src="uploads/logos/<?php echo $comp['logo']; ?>" alt="Logo" class="img-thumbnail" style="max-height: 50px;">
                                <?php endif; ?>
                                <input type="file" class="form-control" name="logo" accept="image/*">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-end">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> <?php echo __('save'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Document Panel -->
        <div class="col-lg-5">
            <!-- Upload Doc Form -->
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-file-earmark-arrow-up-fill me-2"></i> <?php echo __('upload_doc'); ?></h5>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('document_name'); ?></label>
                        <input type="text" class="form-control" name="doc_name" placeholder="e.g. Business Registration Patent" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary"><?php echo __('file'); ?></label>
                        <input type="file" class="form-control" name="doc_file" required>
                    </div>
                    
                    <button type="submit" name="upload_doc" class="btn btn-secondary w-100">
                        <i class="bi bi-cloud-upload me-2"></i> <?php echo __('upload'); ?>
                    </button>
                </form>
            </div>

            <!-- Documents List -->
            <div class="glass-card p-4">
                <h5 class="fw-bold text-success mb-3"><i class="bi bi-folder-fill me-2"></i> Company Repository</h5>
                
                <?php if (empty($documents)): ?>
                    <p class="text-muted small"><?php echo __('no_records'); ?></p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($documents as $doc): ?>
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center bg-transparent border-bottom">
                                <div>
                                    <h6 class="mb-0 fw-bold small"><?php echo htmlspecialchars($doc['doc_name']); ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        Uploaded: <?php echo date('Y-m-d', strtotime($doc['uploaded_at'])); ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="uploads/documents/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-light border px-2 py-1" title="View Document">
                                        <i class="bi bi-eye text-success"></i>
                                    </a>
                                    <a href="company.php?delete_doc_id=<?php echo $doc['id']; ?>" 
                                       class="btn btn-sm btn-light border px-2 py-1 text-danger" 
                                       onclick="return confirm('Are you sure you want to delete this document?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
