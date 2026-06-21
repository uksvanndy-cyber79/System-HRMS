<?php
require_once __DIR__ . '/config/functions.php';
check_permission('manage_roles');

include __DIR__ . '/includes/header.php';

// Fetch all permissions
$permissions = $conn->query("SELECT * FROM `permissions` ORDER BY `id` ASC")->fetchAll();
?>

<div class="container-fluid px-0">
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="fw-bold text-success">
                <i class="bi bi-key-fill me-2"></i> System Permissions List
            </h4>
        </div>
    </div>

    <div class="glass-card p-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Permission Scope / Key</th>
                        <th>Description / Capabilities</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $p): ?>
                        <tr>
                            <td class="fw-bold text-muted"><?php echo $p['id']; ?></td>
                            <td>
                                <code class="bg-light text-danger px-2 py-1 rounded fw-bold border" style="font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </code>
                            </td>
                            <td><?php echo htmlspecialchars($p['description']); ?></td>
                            <td class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($p['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
