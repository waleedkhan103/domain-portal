document.addEventListener("DOMContentLoaded", function () {
  const usersTbody = document.getElementById("users-tbody");
  const pager = document.getElementById("users-pager");
  const searchInput = document.getElementById("users-search");
  const roleSel = document.getElementById("users-role");
  const statusSel = document.getElementById("users-status");
  const dateFrom = document.getElementById("date-from");
  const dateTo = document.getElementById("date-to");
  const selectAll = document.getElementById("select-all-users");
  const bulkSelect = document.getElementById("bulk-action-select");
  const applyBulk = document.getElementById("apply-bulk");
  const exportBtn = document.getElementById("export-csv");
  const newUserBtn = document.getElementById("new-user-btn");

  let page = 1,
    per = 25,
    sort = "created_at",
    dir = "desc";
  let debounce = null;
  const _base = (window.BASE_PATH || "").replace(/\/$/, "");

  function getParams() {
    return new URLSearchParams({
      q: searchInput.value.trim(),
      role: roleSel.value,
      status: statusSel.value,
      date_from: dateFrom.value,
      date_to: dateTo.value,
      page,
      per,
      sort,
      dir,
    });
  }

  async function loadUsers() {
    usersTbody.innerHTML =
      '<tr><td colspan="8" class="text-center">Loading...</td></tr>';
    try {
      const res = await fetch(
        _base + "/admin/api/users_list.php?" + getParams().toString(),
        { credentials: "same-origin" },
      );
      if (res.status === 401) {
        usersTbody.innerHTML =
          '<tr><td colspan="8" class="text-danger">Unauthorized. Redirecting to login...</td></tr>';
        setTimeout(() => {
          location.href = _base + "/admin/login.php";
        }, 900);
        return;
      }
      const j = await res.json();
      if (!j || !j.success) {
        usersTbody.innerHTML =
          '<tr><td colspan="8" class="text-danger">' +
          escapeHtml(j && j.message ? j.message : "Error loading users") +
          "</td></tr>";
        return;
      }
      renderUsers(j.data);
    } catch (err) {
      usersTbody.innerHTML =
        '<tr><td colspan="8" class="text-danger">Error loading</td></tr>';
      console.error(err);
    }
  }

  function renderUsers(data) {
    usersTbody.innerHTML = "";
    if (!data.rows || !data.rows.length) {
      usersTbody.innerHTML =
        '<tr><td colspan="8" class="text-center">No users</td></tr>';
      pager.innerHTML = "";
      return;
    }
    for (const u of data.rows) {
      const tr = document.createElement("tr");
      const displayName = u.name || ((u.first_name || "") + " " + (u.last_name || "")).trim() || "—";
      tr.innerHTML = `<td><input class="sel-user" data-id="${u.id}" type="checkbox"></td>
        <td>${escapeHtml(displayName)} ${u.is_admin ? '<span class="badge bg-info ms-1">ADMIN</span>' : ""}</td>
        <td>${escapeHtml(u.email)}${u.username ? '<div class="small text-muted">' + escapeHtml(u.username) + "</div>" : ""}</td>
        <td>${u.domain_count || 0}</td>
        <td>${formatMoney(u.total_spent || 0)}</td>
        <td>${escapeHtml(u.created_at || "")}</td>
        <td>${escapeHtml(u.status || "")}</td>
        <td><div class="dropdown"><button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Actions</button><ul class="dropdown-menu"><li><a class="dropdown-item view-user" href="#" data-id="${u.id}">View Details</a></li><li><a class="dropdown-item edit-user" href="#" data-id="${u.id}">Edit</a></li><li><a class="dropdown-item suspend-user" href="#" data-id="${u.id}">Suspend</a></li><li><a class="dropdown-item delete-user" href="#" data-id="${u.id}">Delete</a></li></ul></div></td>`;
      usersTbody.appendChild(tr);
    }
    // pager
    const total = data.total || data.rows.length;
    const pages = Math.max(1, Math.ceil(total / (data.per || per)));
    pager.innerHTML = `Page ${data.page} / ${pages}`;
    attachRowHandlers();
  }

  function attachRowHandlers() {
    document.querySelectorAll(".view-user").forEach((a) =>
      a.addEventListener("click", async function (e) {
        e.preventDefault();
        const id = this.dataset.id;
        showUserDetails(id);
      }),
    );
    document.querySelectorAll(".edit-user").forEach((a) =>
      a.addEventListener("click", async function (e) {
        e.preventDefault();
        const id = this.dataset.id;
        openEditModal(id);
      }),
    );
    document.querySelectorAll(".suspend-user").forEach((a) =>
      a.addEventListener("click", async function (e) {
        e.preventDefault();
        if (!confirm("Suspend user?")) return;
        await doBulkAction("disable", [this.dataset.id]);
        loadUsers();
      }),
    );
    document.querySelectorAll(".delete-user").forEach((a) =>
      a.addEventListener("click", async function (e) {
        e.preventDefault();
        if (!confirm("Delete user?")) return;
        await doBulkAction("delete", [this.dataset.id]);
        loadUsers();
      }),
    );
  }

  async function showUserDetails(id) {
    const modal = new bootstrap.Modal(
      document.getElementById("user-details-modal"),
    );
    const body = document.getElementById("user-details-body");
    body.innerHTML = "Loading...";
    modal.show();
    try {
      const res = await fetch(
        _base + "/admin/api/user_details.php?id=" + encodeURIComponent(id),
        { credentials: "same-origin" },
      );
      const j = await res.json();
      if (!j.success) {
        body.innerHTML = '<div class="text-danger">Error loading</div>';
        return;
      }
      const u = j.data.user;
      const uName = u.name || ((u.first_name || "") + " " + (u.last_name || "")).trim() || u.email;
      let out = `<h5>${escapeHtml(uName)}</h5><p>${escapeHtml(u.email)}</p><p>Registered: ${escapeHtml(u.created_at || "")}</p>`;
      out += "<h6>Domains</h6><ul>";
      for (const d of j.data.domains)
        out += `<li>${escapeHtml(d.domain_name)} <span class="text-muted small">${escapeHtml(d.status)}</span></li>`;
      out += "</ul><h6>Recent Orders</h6><ul>";
      for (const o of j.data.orders)
        out += `<li>${escapeHtml(o.order_number || o.id)} - ${escapeHtml(o.status)} - ${formatMoney(o.total)}</li>`;
      out += "</ul>";
      out += `<div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="domains.php?user=${u.id}">View Domains</a> <a class="btn btn-sm btn-outline-secondary" href="orders.php?user=${u.id}">View Orders</a></div>`;
      body.innerHTML = out;
    } catch (err) {
      body.innerHTML = '<div class="text-danger">Error</div>';
    }
  }

  function openEditModal(id) {
    const modalEl = document.getElementById("user-edit-modal");
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById("edit-user-id").value = "";
    document.getElementById("edit-name").value = "";
    document.getElementById("edit-email").value = "";
    document.getElementById("edit-username").value = "";
    document.getElementById("edit-password").value = "";
    document.getElementById("edit-is-admin").checked = false;
    if (id) {
      fetch(_base + "/admin/api/user_details.php?id=" + id, {
        credentials: "same-origin",
      })
        .then((r) => r.json())
        .then((j) => {
          if (j.success) {
            const u = j.data.user;
            document.getElementById("edit-user-id").value = u.id;
            document.getElementById("edit-name").value =
              u.name || ((u.first_name || "") + " " + (u.last_name || "")).trim();
            document.getElementById("edit-email").value = u.email;
            document.getElementById("edit-username").value = u.username || "";
            document.getElementById("edit-is-admin").checked = !!u.is_admin;
          }
        })
        .catch(console.error);
    }
    modal.show();
  }

  document
    .getElementById("user-edit-form")
    .addEventListener("submit", async function (e) {
      e.preventDefault();
      const form = new FormData(this);
      const btn = document.getElementById("save-user-btn");
      btn.disabled = true;
      try {
        const res = await fetch(_base + "/admin/api/save_user.php", {
          method: "POST",
          body: form,
          credentials: "same-origin",
        });
        const j = await res.json();
        alert(j.message || "Saved");
        if (j.success) {
          bootstrap.Modal.getInstance(
            document.getElementById("user-edit-modal"),
          ).hide();
          loadUsers();
        }
      } catch (err) {
        alert("Error");
        console.error(err);
      }
      btn.disabled = false;
    });

  selectAll.addEventListener("change", function () {
    document
      .querySelectorAll(".sel-user")
      .forEach((cb) => (cb.checked = this.checked));
  });
  applyBulk.addEventListener("click", async function () {
    const action = bulkSelect.value;
    if (!action) return alert("Select action");
    const ids = Array.from(document.querySelectorAll(".sel-user:checked")).map(
      (i) => i.dataset.id,
    );
    if (!ids.length) return alert("No users selected");
    if (!confirm("Confirm bulk action: " + action)) return;
    await doBulkAction(action, ids);
    loadUsers();
  });

  async function doBulkAction(action, ids) {
    try {
      const form = new FormData();
      form.append("action", action);
      form.append("ids", JSON.stringify(ids));
      form.append(
        "csrf_token",
        document.querySelector('input[name="csrf_token"]').value,
      );
      const res = await fetch(_base + "/admin/api/bulk_users.php", {
        method: "POST",
        body: form,
        credentials: "same-origin",
      });
      const j = await res.json();
      if (!j.success) alert(j.message || "Bulk action failed");
      else alert(j.message || "Done");
    } catch (err) {
      console.error(err);
      alert("Error");
    }
  }

  exportBtn.addEventListener("click", function () {
    const url = _base + "/admin/api/export_users.php?" + getParams().toString();
    window.location = url;
  });
  newUserBtn.addEventListener("click", function () {
    openEditModal(null);
  });

  // sorting
  document.querySelectorAll(".sortable").forEach((h) =>
    h.addEventListener("click", function () {
      const s = this.dataset.sort;
      if (sort === s) dir = dir === "asc" ? "desc" : "asc";
      else {
        sort = s;
        dir = "asc";
      }
      loadUsers();
    }),
  );

  // filters debounce
  [searchInput, roleSel, statusSel, dateFrom, dateTo].forEach((el) =>
    el.addEventListener("input", function () {
      if (debounce) clearTimeout(debounce);
      debounce = setTimeout(() => {
        page = 1;
        loadUsers();
        updateUrl();
      }, 300);
    }),
  );

  function updateUrl() {
    const p = getParams();
    history.replaceState(
      null,
      "",
      _base + "/admin/manage_users.php?" + p.toString(),
    );
  }

  function escapeHtml(s) {
    if (!s && s !== 0) return "";
    return String(s).replace(/[&<>'\"]/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "'": "&#39;",
        '"': "&quot;",
      }[c];
    });
  }
  function formatMoney(v) {
    return "$" + Number(v || 0).toFixed(2);
  }

  // initial load
  // restore filters from URL
  (function restore() {
    const params = new URLSearchParams(window.location.search);
    if (params.get("q")) searchInput.value = params.get("q");
    if (params.get("role")) roleSel.value = params.get("role");
    if (params.get("status")) statusSel.value = params.get("status");
    if (params.get("date_from")) dateFrom.value = params.get("date_from");
    if (params.get("date_to")) dateTo.value = params.get("date_to");
    if (params.get("page")) page = parseInt(params.get("page"));
    loadUsers();
  })();
});
