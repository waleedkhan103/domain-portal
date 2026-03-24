<?php
$pageTitle = 'Update Domain Contacts';
require_once __DIR__ . '/../includes/header.php';
requireLogin();

$user = getCurrentUser();
$domainId = (int) ($_GET['id'] ?? 0);

// Fetch domain
$sql = "SELECT * FROM domains WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $domainId, $user['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$domain = mysqli_fetch_assoc($result);

if (!$domain) {
    header('Location: ' . BASE_PATH . '/pages/my_domains.php');
    exit;
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Update Domain Contacts: <?php echo htmlspecialchars($domain['domain_name']); ?></h1>
        <a href="<?php echo pageUrl('domain_details.php?id=' . $domainId); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Domain
        </a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Important:</strong> Changing the registrant email address will trigger a 60-day transfer lock as per ICANN requirements.
    </div>

    <form id="contactsForm">
        <input type="hidden" name="domain_id" value="<?php echo $domainId; ?>">

        <!-- Registrant Contact -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge me-2"></i>Registrant Contact (Legal Owner)</span>
                <small>Required - Legal responsibility for the domain</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="registrant_first" class="form-control" required
                            value="<?php echo htmlspecialchars($domain['registrant_first'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="registrant_last" class="form-control" required
                            value="<?php echo htmlspecialchars($domain['registrant_last'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="registrant_email" class="form-control" required
                            value="<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>">
                        <small class="text-muted">Changing this triggers 60-day transfer lock</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="registrant_phone" class="form-control" required
                            placeholder="+1.2125551234"
                            value="<?php echo htmlspecialchars($domain['registrant_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="registrant_address" class="form-control" rows="2"><?php echo htmlspecialchars($domain['registrant_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Contact -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-gear me-2"></i>Administrative Contact</span>
                <label class="form-check-label text-white">
                    <input type="checkbox" class="form-check-input" id="adminSameAsRegistrant">
                    Same as Registrant
                </label>
            </div>
            <div class="card-body" id="adminContactFields">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="admin_first" class="form-control admin-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_first'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="admin_last" class="form-control admin-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_last'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="admin_email" class="form-control admin-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="admin_phone" class="form-control admin-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="admin_address" class="form-control admin-field" rows="2"><?php echo htmlspecialchars($domain['registrant_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Contact -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tools me-2"></i>Technical Contact</span>
                <label class="form-check-label text-white">
                    <input type="checkbox" class="form-check-input" id="techSameAsRegistrant">
                    Same as Registrant
                </label>
            </div>
            <div class="card-body" id="techContactFields">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="tech_first" class="form-control tech-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_first'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="tech_last" class="form-control tech-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_last'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="tech_email" class="form-control tech-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="tech_phone" class="form-control tech-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="tech_address" class="form-control tech-field" rows="2"><?php echo htmlspecialchars($domain['registrant_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Contact -->
        <div class="card mb-4">
            <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                <span><i class="bi bi-credit-card me-2"></i>Billing Contact</span>
                <label class="form-check-label">
                    <input type="checkbox" class="form-check-input" id="billingSameAsRegistrant">
                    Same as Registrant
                </label>
            </div>
            <div class="card-body" id="billingContactFields">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="billing_first" class="form-control billing-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_first'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="billing_last" class="form-control billing-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_last'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="billing_email" class="form-control billing-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="billing_phone" class="form-control billing-field" required
                            value="<?php echo htmlspecialchars($domain['registrant_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="billing_address" class="form-control billing-field" rows="2"><?php echo htmlspecialchars($domain['registrant_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-save me-2"></i>Update Contacts
            </button>
            <a href="<?php echo pageUrl('domain_details.php?id=' . $domainId); ?>" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
const basePath = document.documentElement.getAttribute('data-base-path') || '/misc/waleed/domain-portal';

// Copy registrant data to other contacts
function copyRegistrantData(targetPrefix) {
    const registrantData = {
        first: document.querySelector('[name="registrant_first"]').value,
        last: document.querySelector('[name="registrant_last"]').value,
        email: document.querySelector('[name="registrant_email"]').value,
        phone: document.querySelector('[name="registrant_phone"]').value,
        address: document.querySelector('[name="registrant_address"]').value
    };

    document.querySelector(`[name="${targetPrefix}_first"]`).value = registrantData.first;
    document.querySelector(`[name="${targetPrefix}_last"]`).value = registrantData.last;
    document.querySelector(`[name="${targetPrefix}_email"]`).value = registrantData.email;
    document.querySelector(`[name="${targetPrefix}_phone"]`).value = registrantData.phone;
    document.querySelector(`[name="${targetPrefix}_address"]`).value = registrantData.address;
}

// "Same as Registrant" checkboxes
document.getElementById('adminSameAsRegistrant').addEventListener('change', function() {
    if (this.checked) copyRegistrantData('admin');
});

document.getElementById('techSameAsRegistrant').addEventListener('change', function() {
    if (this.checked) copyRegistrantData('tech');
});

document.getElementById('billingSameAsRegistrant').addEventListener('change', function() {
    if (this.checked) copyRegistrantData('billing');
});

// Form submission
document.getElementById('contactsForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    if (!confirm('Are you sure you want to update domain contacts? Changing the registrant email will trigger a 60-day transfer lock.')) {
        return;
    }

    const formData = new FormData(this);
    formData.append('action', 'update_contacts');

    try {
        const response = await fetch(basePath + '/api/update_contacts.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            alert(data.message);
            window.location.href = basePath + '/pages/domain_details.php?id=' + formData.get('domain_id');
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
