<?php
$pageTitle = 'Admin - Edit Domain';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
    echo '<div class="alert alert-warning">Database unavailable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    echo '<div class="alert alert-danger">Invalid domain ID.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle POST - save changes
$successMsg = '';
$errorMsg   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid CSRF token. Please try again.';
    } else {
        $domainName  = trim($_POST['domain_name'] ?? '');
        $status      = trim($_POST['status'] ?? '');
        $expiryDate  = trim($_POST['expiry_date'] ?? '');
        $userId      = (int) ($_POST['user_id'] ?? 0);

        if ($domainName === '') {
            $errorMsg = 'Domain name is required.';
        } else {
            if ($stmt = $conn->prepare(
                "UPDATE domains SET domain_name = ?, status = ?, expiry_date = ?, user_id = ? WHERE id = ?"
            )) {
                $stmt->bind_param('sssii', $domainName, $status, $expiryDate, $userId, $id);
                if ($stmt->execute()) {
                    $successMsg = 'Domain updated successfully.';
                } else {
                    $errorMsg = 'Failed to update domain: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMsg = 'Query prepare failed: ' . $conn->error;
            }
        }
    }
}

// Load domain
$domain = null;
if ($stmt = $conn->prepare(
    "SELECT d.*, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id WHERE d.id = ? LIMIT 1"
)) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $domain = $res->fetch_assoc();
    }
    $stmt->close();
} else {
    $res    = mysqli_query($conn, "SELECT d.*, u.email FROM domains d LEFT JOIN users u ON u.id = d.user_id WHERE d.id = $id LIMIT 1");
    $domain = $res ? mysqli_fetch_assoc($res) : null;
}

if (!$domain) {
    echo '<div class="alert alert-info">Domain not found.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Load users for owner dropdown
$users = [];
$uRes  = mysqli_query($conn, "SELECT id, email, name FROM users ORDER BY email ASC LIMIT 500");
if (!$uRes) {
    // fallback without name column
    $uRes = mysqli_query($conn, "SELECT id, email FROM users ORDER BY email ASC LIMIT 500");
}
if ($uRes) {
    while ($u = mysqli_fetch_assoc($uRes)) {
        $users[] = $u;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h2">Edit Domain: <?php echo htmlspecialchars($domain['domain_name']); ?></h1>
    <a href="domains.php" class="btn btn-outline-secondary btn-sm">Back to Domains</a>
</div>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Domain Details</h5>
                <form method="post" action="edit_domain.php?id=<?php echo $id; ?>">
                    <?php echo getCSRFField(); ?>

                    <div class="mb-3">
                        <label class="form-label">Domain Name</label>
                        <input type="text" class="form-control" name="domain_name"
                            value="<?php echo htmlspecialchars($domain['domain_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach (['active', 'pending', 'expired', 'suspended', 'cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>"
                                    <?php echo (strtolower($domain['status'] ?? '') === $s) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date"
                            value="<?php echo htmlspecialchars($domain['expiry_date'] ?? $domain['expires_at'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Owner</label>
                        <select class="form-select" name="user_id">
                            <option value="0">-- No owner --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int) $u['id']; ?>"
                                    <?php echo ((int) $domain['user_id'] === (int) $u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['email']); ?>
                                    <?php if (!empty($u['name'])): ?>
                                        (<?php echo htmlspecialchars($u['name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="view_domain.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">View Domain</a>
                        <a href="domains.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Info</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>ID:</strong> <?php echo $domain['id']; ?></li>
                    <li class="list-group-item"><strong>Current Owner:</strong>
                        <?php echo htmlspecialchars($domain['email'] ?? '#' . $domain['user_id']); ?>
                    </li>
                    <li class="list-group-item"><strong>Registered:</strong>
                        <?php echo htmlspecialchars($domain['registration_date'] ?? $domain['created_at'] ?? ''); ?>
                    </li>
                    <li class="list-group-item"><strong>Expires:</strong>
                        <?php echo htmlspecialchars($domain['expiry_date'] ?? $domain['expires_at'] ?? ''); ?>
                    </li>
                </ul>
                <div class="mt-3">
                    <a href="view_domain.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary w-100">
                        View Full Details
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
