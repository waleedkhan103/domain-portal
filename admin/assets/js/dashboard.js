document.addEventListener("DOMContentLoaded", function () {
  const _base = (typeof BASE_PATH !== "undefined" ? BASE_PATH : "").replace(
    /\/$/,
    "",
  );
  const dataUrl = _base + "/admin/api/dashboard_data.php";
  const searchUrl = _base + "/admin/api/dashboard_search.php";

  const elTotal = document.getElementById("total-domains");
  const elActive = document.getElementById("active-domains");
  const elExpiring = document.getElementById("expiring-domains");
  const elRevenue = document.getElementById("revenue-month");

  const elPending = document.getElementById("quick-pending");
  const elRegs = document.getElementById("quick-registrations");
  const elFailed = document.getElementById("quick-failed");

  const recentDomainsTbody = document.getElementById("recent-domains-tbody");
  const recentOrdersTbody = document.getElementById("recent-orders-tbody");

  const alertsArea = document.getElementById("alerts-area");

  // loading skeletons already present as 'Loading...'

  async function fetchData() {
    try {
      const res = await fetch(dataUrl, { credentials: "same-origin" });
      const j = await res.json();
      if (!j.success) {
        console.error(j);
        return;
      }
      renderData(j.data);
    } catch (err) {
      console.error(err);
    }
  }

  function fmtMoney(v) {
    return "$" + Number(v || 0).toFixed(2);
  }

  function renderData(d) {
    elTotal.textContent = d.total_domains;
    elActive.textContent = d.active_domains;
    elExpiring.textContent = d.expiring_30;
    elRevenue.textContent = fmtMoney(d.revenue_month);

    // deltas
    const revDelta = computeDelta(d.revenue_month, d.revenue_last_month);
    document.getElementById("revenue-month-delta").textContent = revDelta;

    elPending.textContent = d.pending_orders;
    elRegs.textContent = d.recent_registrations;
    elFailed.textContent = d.failed_payments;

    // alerts
    alertsArea.innerHTML = "";
    if (d.alerts.expiring_7)
      alertsArea.innerHTML += `<div class="alert alert-danger">${d.alerts.expiring_7} domains expiring within 7 days <a href="domains.php?expiring=7" class="stretched-link"></a></div>`;
    if (d.alerts.expiring_14)
      alertsArea.innerHTML += `<div class="alert alert-warning">${d.alerts.expiring_14} domains expiring within 14 days <a href="domains.php?expiring=14" class="stretched-link"></a></div>`;
    if (d.alerts.pending_orders)
      alertsArea.innerHTML += `<div class="alert alert-info">${d.alerts.pending_orders} pending orders <a href="orders.php?status=pending" class="stretched-link"></a></div>`;

    // recent domains
    recentDomainsTbody.innerHTML = "";
    if (Array.isArray(d.recent_domains) && d.recent_domains.length) {
      for (const r of d.recent_domains) {
        const tr = document.createElement("tr");
        const registered = r.registered_at ? r.registered_at : "";
        tr.innerHTML = `<td><a href="view_domain.php?id=${r.id || ""}">${escapeHtml(r.domain_name)}</a></td><td>${escapeHtml(r.email || "#" + (r.user_id || ""))}</td><td>${escapeHtml(registered)}</td><td>${escapeHtml(r.status || "")}</td>`;
        recentDomainsTbody.appendChild(tr);
      }
    } else {
      recentDomainsTbody.innerHTML =
        '<tr><td colspan="4">No recent domains</td></tr>';
    }

    // recent orders
    recentOrdersTbody.innerHTML = "";
    if (Array.isArray(d.recent_orders) && d.recent_orders.length) {
      for (const o of d.recent_orders) {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td><a href="view_order.php?id=${o.id}">${escapeHtml(o.order_number || o.id)}</a></td><td>${escapeHtml(o.email || "#" + (o.user_id || ""))}</td><td>-</td><td>${fmtMoney(o.total)}</td><td>${escapeHtml(o.status || "")}</td><td>${escapeHtml(o.created_at || "")}</td>`;
        recentOrdersTbody.appendChild(tr);
      }
    } else {
      recentOrdersTbody.innerHTML =
        '<tr><td colspan="6">No recent orders</td></tr>';
    }

    // Draw chart
    renderStatusChart(d.domains_by_status || {});
  }

  function computeDelta(current, previous) {
    previous = Number(previous || 0);
    current = Number(current || 0);
    if (previous === 0)
      return previous === current ? "—" : current > 0 ? "+100%" : "0%";
    const pct = ((current - previous) / previous) * 100;
    return (pct >= 0 ? "+" : "") + pct.toFixed(1) + "%";
  }

  // Chart.js
  let statusChart = null;
  function renderStatusChart(obj) {
    const ctx = document
      .getElementById("domains-status-chart")
      .getContext("2d");
    const labels = Object.keys(obj);
    const data = labels.map((k) => obj[k]);
    if (statusChart) {
      statusChart.data.labels = labels;
      statusChart.data.datasets[0].data = data;
      statusChart.update();
      return;
    }
    statusChart = new Chart(ctx, {
      type: "pie",
      data: {
        labels,
        datasets: [{ data, backgroundColor: generateColors(labels.length) }],
      },
      options: { responsive: true },
    });
  }

  function generateColors(n) {
    const pal = [
      "#4e73df",
      "#1cc88a",
      "#36b9cc",
      "#f6c23e",
      "#e74a3b",
      "#858796",
    ];
    const out = [];
    for (let i = 0; i < n; i++) out.push(pal[i % pal.length]);
    return out;
  }

  // Escape helper
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

  // Search auto-suggest
  const searchInput = document.getElementById("admin-quick-search");
  const suggestions = document.getElementById("search-suggestions");
  let debounceTimer = null;
  searchInput.addEventListener("input", function (e) {
    const v = this.value.trim();
    if (debounceTimer) clearTimeout(debounceTimer);
    if (!v) {
      suggestions.style.display = "none";
      suggestions.innerHTML = "";
      return;
    }
    debounceTimer = setTimeout(() => doSuggest(v), 250);
  });

  searchInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      goSearch(this.value.trim());
    }
  });
  document.getElementById("search-btn").addEventListener("click", function () {
    goSearch(searchInput.value.trim());
  });

  async function doSuggest(q) {
    try {
      const res = await fetch(searchUrl + "?q=" + encodeURIComponent(q), {
        credentials: "same-origin",
      });
      const j = await res.json();
      if (!j.success) return;
      suggestions.innerHTML = "";
      if (!j.data || !j.data.length) {
        suggestions.style.display = "none";
        return;
      }
      for (const item of j.data) {
        const a = document.createElement("a");
        a.className = "list-group-item list-group-item-action";
        a.href = "#";
        a.dataset.type = item.type;
        a.dataset.id = item.id;
        a.textContent = item.label;
        a.addEventListener("click", function (e) {
          e.preventDefault();
          if (this.dataset.type === "domain")
            location.href = "view_domain.php?id=" + this.dataset.id;
          else if (this.dataset.type === "order")
            location.href = "view_order.php?id=" + this.dataset.id;
        });
        suggestions.appendChild(a);
      }
      suggestions.style.display = "block";
    } catch (err) {
      console.error(err);
    }
  }

  function goSearch(q) {
    if (!q) return; // try numeric -> order id
    if (/^\d+$/.test(q)) {
      location.href = "view_order.php?id=" + q;
      return;
    }
    // otherwise try domain exact
    location.href = "domains.php?search=" + encodeURIComponent(q);
  }

  // initialize
  fetchData();
});
