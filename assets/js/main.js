// Main JavaScript
document.addEventListener("DOMContentLoaded", function () {
  // Mobile menu toggle
  const mobileToggle = document.querySelector(".mobile-menu-toggle");
  if (mobileToggle) {
    mobileToggle.addEventListener("click", function () {
      document.querySelector(".nav").classList.toggle("active");
    });
  }

  // User menu dropdown
  const userButton = document.querySelector(".user-button");
  if (userButton) {
    userButton.addEventListener("click", function (e) {
      e.stopPropagation();
      document.querySelector(".user-dropdown").classList.toggle("active");
    });

    document.addEventListener("click", function () {
      document.querySelector(".user-dropdown")?.classList.remove("active");
    });
  }
});
