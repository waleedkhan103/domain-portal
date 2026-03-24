document.addEventListener("DOMContentLoaded", function () {
  const _base = (typeof BASE_PATH !== "undefined" ? BASE_PATH : "").replace(
    /\/$/,
    "",
  );

  const saveBtn = document.getElementById("save-settings-btn");
  const saveSpinner = document.getElementById("save-spinner");
  const alertBox = document.getElementById("settings-alert");
  let csrfToken = document.getElementById("csrf-token").value;
  const settingsTabs = document.getElementById("settings-tabs");

  // Initialize tabs - Bootstrap native + manual fallback
  console.log("Initializing tabs...");
  if (settingsTabs) {
    const tabButtons = settingsTabs.querySelectorAll(
      'button[data-bs-toggle="tab"]',
    );
    console.log("Found tab buttons:", tabButtons.length);

    tabButtons.forEach((button) => {
      button.addEventListener("click", function (e) {
        console.log("Tab clicked:", this.id);

        // Remove active from all tabs
        tabButtons.forEach((btn) => {
          btn.classList.remove("active");
          btn.setAttribute("aria-selected", "false");
        });

        // Hide all tab panes
        document.querySelectorAll(".tab-pane").forEach((pane) => {
          pane.classList.remove("show", "active");
        });

        // Activate clicked tab
        this.classList.add("active");
        this.setAttribute("aria-selected", "true");

        // Show corresponding tab pane
        const targetId = this.getAttribute("data-bs-target");
        const targetPane = document.querySelector(targetId);
        if (targetPane) {
          targetPane.classList.add("show", "active");
          console.log("Activated tab pane:", targetId);
        }
      });
    });
  } else {
    console.error("Settings tabs element not found!");
  }

  // Load settings on page load
  loadSettings();

  async function loadSettings() {
    try {
      const res = await fetch(_base + "/admin/api/settings_get.php", {
        credentials: "same-origin",
      });
      const data = await res.json();

      if (data.success && data.settings) {
        settingsData = data.settings;
        populateForm(data.settings);
      } else {
        showAlert("Failed to load settings", "danger");
      }
    } catch (err) {
      console.error("Error loading settings:", err);
      showAlert("Error loading settings", "danger");
    }
  }

  function populateForm(settings) {
    // Populate all input fields with data-setting attribute
    document.querySelectorAll("[data-setting]").forEach((field) => {
      const key = field.getAttribute("data-setting");
      if (!settings[key]) return;

      const value = settings[key].value;

      if (field.type === "checkbox") {
        field.checked = value === "1" || value === true;
      } else if (field.tagName === "SELECT") {
        field.value = value;
      } else {
        field.value = value;
      }
    });
  }

  // Save settings button
  saveBtn.addEventListener("click", async function () {
    const changedSettings = {};

    // Collect all settings from all forms
    document.querySelectorAll("[data-setting]").forEach((field) => {
      const key = field.getAttribute("data-setting");
      let value;

      if (field.type === "checkbox") {
        value = field.checked ? "1" : "0";
      } else {
        value = field.value;
      }

      changedSettings[key] = value;
    });

    // Show loading state
    saveBtn.disabled = true;
    saveSpinner.classList.remove("d-none");

    try {
      const res = await fetch(_base + "/admin/api/settings_save.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          settings: changedSettings,
          csrf_token: csrfToken,
        }),
      });

      const data = await res.json();

      if (data.success) {
        if (data.csrf_token) csrfToken = data.csrf_token;
        showAlert(data.message || "Settings saved successfully", "success");
        // Reload settings to show saved state
        await loadSettings();
      } else {
        showAlert(data.message || "Failed to save settings", "danger");
      }
    } catch (err) {
      console.error("Error saving settings:", err);
      showAlert("Error saving settings", "danger");
    } finally {
      saveBtn.disabled = false;
      saveSpinner.classList.add("d-none");
    }
  });

  function showAlert(message, type = "info") {
    alertBox.className = `alert alert-${type}`;
    alertBox.textContent = message;
    alertBox.classList.remove("d-none");

    // Auto-hide after 5 seconds
    setTimeout(() => {
      alertBox.classList.add("d-none");
    }, 5000);
  }
});
