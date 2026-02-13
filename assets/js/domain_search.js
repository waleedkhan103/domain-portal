// Domain Search JavaScript

// Get base path from the page (set it in header.php)
const basePath =
  document.documentElement.getAttribute("data-base-path") || "/domain-portal";

const heroSearchForm = document.getElementById("heroSearchForm");
if (heroSearchForm) {
  heroSearchForm.addEventListener("submit", function (e) {
    e.preventDefault();
    const domain = document.getElementById("heroSearchInput").value.trim();
    if (domain) {
      window.location.href =
        basePath + "/pages/domain_search.php?q=" + encodeURIComponent(domain);
    }
  });
}

// Auto-suggest
const searchInput = document.getElementById("heroSearchInput");
if (searchInput) {
  searchInput.addEventListener("input", function () {
    const value = this.value.trim();
    if (value.length > 2) {
      // Show suggestions
    }
  });
}
