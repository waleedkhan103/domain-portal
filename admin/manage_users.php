<?php
$pageTitle = 'Admin - Manage Users';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/header.php';

// Page UI - data loaded via AJAX
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1>Users</h1>
  <div class="d-flex gap-2">
    <button id="export-csv" class="btn btn-outline-secondary btn-sm">Export to CSV</button>
    <button id="new-user-btn" class="btn btn-primary btn-sm">New User</button>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-4">
        <input id="users-search" class="form-control" placeholder="Search name, email or username">
      </div>
      <div class="col-md-2">
        <select id="users-role" class="form-select">
          <option value="all">All Users</option>
          <option value="admins">Admins Only</option>
          <option value="regular">Regular Users</option>
        </select>
      </div>
      <div class="col-md-2">
        <select id="users-status" class="form-select">
          <option value="all">Any Status</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="deleted">Deleted</option>
        </select>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <input id="date-from" type="date" class="form-control">
        <input id="date-to" type="date" class="form-control">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover table-bordered" id="users-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="select-all-users"></th>
            <th data-sort="name" class="sortable">Name</th>
            <th data-sort="email" class="sortable">Email</th>
            <th data-sort="domains" class="sortable">Domains</th>
            <th data-sort="spent" class="sortable">Total Spent</th>
            <th data-sort="created_at" class="sortable">Registered</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="users-tbody">
          <tr>
            <td colspan="8" class="text-center">Loading users...</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div id="users-pager" class="mt-2"></div>
  </div>
</div>

<!-- Bulk actions -->
<div class="mt-3 d-flex gap-2">
  <select id="bulk-action-select" class="form-select" style="max-width:260px;">
    <option value="">Bulk actions</option>
    <option value="enable">Enable Selected</option>
    <option value="disable">Disable Selected</option>
    <option value="delete">Delete Selected</option>
  </select>
  <button id="apply-bulk" class="btn btn-secondary">Apply</button>
</div>

<!-- User Details Modal -->
<div class="modal" tabindex="-1" id="user-details-modal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User Details</h5><button type="button" class="btn-close"
          data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="user-details-body">Loading...</div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<!-- Edit/Create User Modal -->
<div class="modal" tabindex="-1" id="user-edit-modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="user-edit-form">
        <div class="modal-header">
          <h5 class="modal-title">Edit User</h5><button type="button" class="btn-close"
            data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php echo getCSRFField(); ?>
          <input type="hidden" name="id" id="edit-user-id">
          <div class="mb-2"><label class="form-label">Name</label><input name="name" id="edit-name" class="form-control"
              required></div>
          <div class="mb-2"><label class="form-label">Email</label><input name="email" id="edit-email" type="email"
              class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input name="username" id="edit-username"
              class="form-control"></div>
          <div class="mb-2"><label class="form-label">Password (leave blank to keep)</label><input name="password"
              id="edit-password" type="password" class="form-control"></div>
          <div class="form-check form-switch mb-2"><input name="is_admin" id="edit-is-admin" class="form-check-input"
              type="checkbox"><label class="form-check-label">Is Admin</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button
            class="btn btn-primary" id="save-user-btn">Save</button></div>
      </form>
    </div>
  </div>
</div>

<script src="<?php echo BASE_PATH; ?>/admin/assets/js/manage_users.js" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>