document.addEventListener("DOMContentLoaded", () => {
  // Password Toggle Logic
  const toggleButtons = document.querySelectorAll(".toggle-password");
  toggleButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const targetId = this.getAttribute("data-target");
      const passwordInput = document.getElementById(targetId);

      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        this.classList.remove("fa-eye");
        this.classList.add("fa-eye-slash");
      } else {
        passwordInput.type = "password";
        this.classList.remove("fa-eye-slash");
        this.classList.add("fa-eye");
      }
    });
  });

  // Sidebar Active State Auto-Selection
  const currentPath = window.location.search;
  const navLinks = document.querySelectorAll(".admin-sidebar-link");
  if (currentPath) {
    navLinks.forEach((link) => {
      if (link.getAttribute("href").includes(currentPath)) {
        link.classList.add("active-nav");
      }
    });
  }

  // Modal Global Toggle (if needed)
  window.toggleModal = function (id) {
    const modal = document.getElementById(id);
    if (modal) {
      modal.style.display = modal.style.display === "none" ? "flex" : "none";
    }
  };
});
