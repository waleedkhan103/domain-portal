document.addEventListener("DOMContentLoaded", function () {
  const lockBtn = document.getElementById("domain-lock-btn");
  const authBtn = document.getElementById("domain-auth-btn");
  const renewBtn = document.getElementById("domain-renew-btn");
  const contactsForm = document.getElementById("contacts-form");
  const toastArea = document.getElementById("domain-toast-area");
  const logArea = document.getElementById("domain-activity-log");
  const domainId = document.getElementById("domain-id")
    ? document.getElementById("domain-id").value
    : null;

  function showToast(message, success = true) {
    if (!toastArea) return;
    const el = document.createElement("div");
    el.className = "alert " + (success ? "alert-success" : "alert-danger");
    el.textContent = message;
    toastArea.appendChild(el);
    setTimeout(() => {
      el.remove();
    }, 4000);
  }

  async function postJSON(url, data) {
    try {
      const res = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        body: data,
      });
      return await res.json();
    } catch (e) {
      return { success: false, message: "Request failed" };
    }
  }

  if (lockBtn) {
    lockBtn.addEventListener("click", async function () {
      lockBtn.disabled = true;
      const form = new FormData();
      form.append("domain_id", domainId);
      form.append(
        "csrf_token",
        document.querySelector('input[name="csrf_token"]').value,
      );
      const json = await postJSON("api/toggle_domain_lock.php", form);
      lockBtn.disabled = false;
      if (json.success) {
        const badge = document.getElementById("domain-lock-badge");
        if (badge)
          badge.textContent =
            json.data && json.data.is_locked ? "Locked" : "Unlocked";
        showToast("Lock status updated");
        loadActivity();
      } else {
        showToast(json.message || "Failed", false);
      }
    });
  }

  if (authBtn) {
    authBtn.addEventListener("click", async function () {
      authBtn.disabled = true;
      const form = new FormData();
      form.append("domain_id", domainId);
      form.append(
        "csrf_token",
        document.querySelector('input[name="csrf_token"]').value,
      );
      const json = await postJSON("api/get_auth_code.php", form);
      authBtn.disabled = false;
      if (json.success) {
        const code = json.data.auth_code || json.data;
        // show modal
        const modal = document.getElementById("auth-modal");
        const codeEl = document.getElementById("auth-code");
        if (codeEl) codeEl.textContent = code;
        if (modal) {
          const bs = new bootstrap.Modal(modal);
          bs.show();
          // attach copy handler
          const copyBtn = document.getElementById("copy-auth");
          if (copyBtn) {
            copyBtn.onclick = function () {
              try {
                navigator.clipboard.writeText(code);
                showToast("Copied");
              } catch (e) {
                showToast("Copy failed", false);
              }
            };
          }
        }
        loadActivity();
      } else {
        showToast(json.message || "Failed to retrieve code", false);
      }
    });
  }

  if (renewBtn) {
    renewBtn.addEventListener("click", async function () {
      const years = prompt("Enter number of years to renew (1-10):", "1");
      const n = parseInt(years, 10);
      if (isNaN(n) || n < 1) return;
      renewBtn.disabled = true;
      const form = new FormData();
      form.append("domain_id", domainId);
      form.append("period", n);
      form.append(
        "csrf_token",
        document.querySelector('input[name="csrf_token"]').value,
      );
      const json = await postJSON("api/renew_domain.php", form);
      renewBtn.disabled = false;
      if (json.success) {
        showToast("Domain renewed");
        // update expiry display
        const expEl = document.getElementById("domain-expires");
        if (expEl && json.data && json.data.new_expiry_date)
          expEl.textContent = json.data.new_expiry_date;
        loadActivity();
      } else {
        showToast(json.message || "Renewal failed", false);
      }
    });
  }

  if (contactsForm) {
    contactsForm.addEventListener("submit", async function (e) {
      e.preventDefault();
      const btn = contactsForm.querySelector("button[type=submit]");
      if (btn) btn.disabled = true;
      const fd = new FormData(contactsForm);
      fd.append("domain_id", domainId);
      const json = await postJSON("api/update_contacts.php", fd);
      if (btn) btn.disabled = false;
      if (json.success) {
        showToast("Contacts updated");
        loadActivity();
      } else {
        showToast(json.message || "Failed to update contacts", false);
      }
    });
  }

  async function loadActivity() {
    if (!logArea) return;
    try {
      const res = await fetch(
        "api/domain_activity.php?domain_id=" + encodeURIComponent(domainId),
        { credentials: "same-origin" },
      );
      const j = await res.json();
      if (j.success) {
        let out = '<ul class="list-group">';
        j.data.forEach((it) => {
          out += `<li class="list-group-item"><strong>${escapeHtml(it.action)}</strong> — ${escapeHtml(it.description)} <div class="text-muted small">${escapeHtml(it.created_at)}</div></li>`;
        });
        out += "</ul>";
        logArea.innerHTML = out;
      }
    } catch (e) {}
  }

  loadActivity();
});
