document.addEventListener("DOMContentLoaded", function () {
  console.log("Admin JS loaded");
  // Handle nameserver form submissions in view_domain.php
  var nsForm = document.getElementById("ns-form");
  if (nsForm) {
    nsForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var alertEl = document.getElementById("ns-alert");
      alertEl.innerHTML = "";
      var fd = new FormData(nsForm);
      fetch(nsForm.action, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (json) {
          var cls = json.success ? "alert alert-success" : "alert alert-danger";
          alertEl.innerHTML =
            '<div class="' +
            cls +
            '">' +
            (json.message || "No response") +
            "</div>";
          if (json.success) {
            setTimeout(function () {
              location.reload();
            }, 900);
          }
        })
        .catch(function (err) {
          alertEl.innerHTML =
            '<div class="alert alert-danger">Request failed</div>';
        });
    });
  }
});
