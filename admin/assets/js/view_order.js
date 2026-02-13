document.addEventListener("DOMContentLoaded", function () {
  const approveBtn = document.getElementById("approve-order");
  if (!approveBtn) return;
  approveBtn.addEventListener("click", async function (e) {
    e.preventDefault();
    const orderId = this.dataset.orderId;
    const token = document.querySelector('input[name="csrf_token"]').value;
    if (!confirm("Approve and process this order?")) return;
    const form = new FormData();
    form.append("order_id", orderId);
    form.append("csrf_token", token);
    const res = await fetch("/admin/api/approve_order.php", {
      method: "POST",
      body: form,
    });
    const j = await res.json();
    alert(j.message || JSON.stringify(j));
    if (j.success) location.reload();
  });
});
