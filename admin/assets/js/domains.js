document.addEventListener("DOMContentLoaded", function () {
  const qs = (k) => new URLSearchParams(window.location.search).get(k);
  const debounce = (fn, wait) => {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  };

  const tableBody = document.getElementById("domains-tbody");
  const pager = document.getElementById("domains-pager");
  const statusCounts = document.getElementById("domains-status-counts");
  const perPageSelect = document.getElementById("domains-per-page");
  const statusSelect = document.getElementById("domains-status");
  const searchInput = document.getElementById("domains-search");
  const ownerToggle = document.getElementById("domains-owner-toggle");
  const spinner = document.getElementById("domains-spinner");

  function showSpinner(show) {
    if (!spinner) return;
    spinner.style.display = show ? "inline-block" : "none";
  }

  function highlight(text, query) {
    if (!query) return text;
    try {
      const re = new RegExp(
        "(" + query.replace(/[-/\\^$*+?.()|[\]{}]/g, "\\$&") + ")",
        "ig",
      );
      return text.replace(re, "<mark>$1</mark>");
    } catch (e) {
      return text;
    }
  }

  function buildParams(page = 1) {
    return {
      search: searchInput ? searchInput.value.trim() : "",
      status: statusSelect ? statusSelect.value : "all",
      page: page,
      per_page: perPageSelect ? perPageSelect.value : 25,
      owner_only: ownerToggle ? (ownerToggle.checked ? "1" : "0") : "0",
    };
  }

  function updateUrl(params) {
    const url = new URL(window.location.href);
    Object.keys(params).forEach((k) => url.searchParams.set(k, params[k]));
    history.replaceState({}, "", url.toString());
  }

  function renderRows(rows, query) {
    if (!tableBody) return;
    if (!rows || rows.length === 0) {
      tableBody.innerHTML =
        '<tr><td colspan="6" class="text-center">No domains found.</td></tr>';
      return;
    }
    let out = "";
    rows.forEach((r) => {
      const domain = highlight(escapeHtml(r.domain_name), query);
      const owner = highlight(escapeHtml(r.email ?? "#" + r.user_id), query);
      out += `<tr>`;
      out += `<td>${r.id}</td>`;
      out += `<td>${domain}</td>`;
      out += `<td>${owner}</td>`;
      out += `<td>${escapeHtml(r.status || "")}</td>`;
      out += `<td>${escapeHtml(r.expires_at || "")}</td>`;
      out += `<td><a class="btn btn-sm btn-primary" href="view_domain.php?id=${r.id}">View</a> <a class="btn btn-sm btn-secondary" href="edit_domain.php?id=${r.id}">Edit</a></td>`;
      out += `</tr>`;
    });
    tableBody.innerHTML = out;
  }

  function renderCounts(counts) {
    if (!statusCounts) return;
    const statuses = [
      "All",
      "Active",
      "Pending",
      "Expired",
      "Suspended",
      "Cancelled",
    ];
    let out = "";
    statuses.forEach((s) => {
      const key = s === "All" ? "all" : s;
      const cnt = counts[key] || counts[s] || 0;
      out += `<button class="btn btn-sm btn-outline-secondary me-1 mb-1 status-pill" data-status="${key}">${s} <span class="badge bg-secondary">${cnt}</span></button>`;
    });
    statusCounts.innerHTML = out;
    // attach click handlers
    statusCounts.querySelectorAll(".status-pill").forEach((btn) => {
      btn.addEventListener("click", function () {
        if (statusSelect) statusSelect.value = this.dataset.status;
        load(1);
      });
    });
  }

  function renderPager(meta) {
    if (!pager) return;
    const page = meta.page;
    const pages = meta.pages;
    const total = meta.total;
    const per = meta.per_page;
    const start = (page - 1) * per + 1;
    const end = Math.min(total, page * per);
    let out = `<div class="d-flex justify-content-between align-items-center">`;
    out += `<div>Showing ${start}-${end} of ${total} total domains</div>`;
    out += `<nav><ul class="pagination mb-0">`;
    const prevDisabled = page <= 1 ? " disabled" : "";
    out += `<li class="page-item${prevDisabled}"><a class="page-link" href="#" data-page="${page - 1}">Previous</a></li>`;
    const displayFrom = Math.max(1, page - 3);
    const displayTo = Math.min(pages, page + 3);
    for (let p = displayFrom; p <= displayTo; p++) {
      const active = p === page ? " active" : "";
      out += `<li class="page-item${active}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
    }
    const nextDisabled = page >= pages ? " disabled" : "";
    out += `<li class="page-item${nextDisabled}"><a class="page-link" href="#" data-page="${page + 1}">Next</a></li>`;
    out += `</ul></nav></div>`;
    pager.innerHTML = out;
    pager.querySelectorAll(".page-link").forEach((a) => {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        const p = parseInt(this.dataset.page, 10);
        if (!isNaN(p) && p >= 1) load(p);
      });
    });
  }

  function escapeHtml(s) {
    if (!s) return "";
    return s.replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }

  async function load(page = 1) {
    const params = buildParams(page);
    updateUrl(params);
    showSpinner(true);
    try {
      const qs = new URLSearchParams(params).toString();
      const res = await fetch("api/domains_filter.php?" + qs, {
        credentials: "same-origin",
      });
      const json = await res.json();
      if (json.success) {
        renderRows(json.data.rows, params.search);
        renderPager(json.data);
        renderCounts(json.data.counts || {});
      } else {
        tableBody.innerHTML =
          '<tr><td colspan="6" class="text-center">Error loading data</td></tr>';
      }
    } catch (e) {
      tableBody.innerHTML =
        '<tr><td colspan="6" class="text-center">Request failed</td></tr>';
    } finally {
      showSpinner(false);
    }
  }

  const debouncedLoad = debounce(() => load(1), 300);

  if (searchInput) searchInput.addEventListener("input", debouncedLoad);
  if (statusSelect) statusSelect.addEventListener("change", () => load(1));
  if (perPageSelect) perPageSelect.addEventListener("change", () => load(1));
  if (ownerToggle) ownerToggle.addEventListener("change", () => load(1));

  // initialize from query params
  const initPage = parseInt(qs("page")) || 1;
  if (searchInput && qs("search")) searchInput.value = qs("search");
  if (statusSelect && qs("status")) statusSelect.value = qs("status");
  if (perPageSelect && qs("per_page")) perPageSelect.value = qs("per_page");
  if (ownerToggle && qs("owner_only"))
    ownerToggle.checked = qs("owner_only") === "1";

  load(initPage);
});
