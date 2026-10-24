const toggle = document.querySelector("[data-nav-toggle]");
const nav = document.querySelector("[data-nav]");

if (toggle && nav) {
  toggle.addEventListener("click", () => {
    const open = nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  });
}

document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    const message =
      form.getAttribute("data-confirm") || "Bạn có chắc muốn thực hiện?";
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
});

const profileDialog = document.querySelector("[data-profile-dialog]");

if (profileDialog && profileDialog.tagName === "DIALOG") {
  const closeUrl =
    profileDialog.getAttribute("data-close-url") || "/?page=volunteers";
  const closeDialog = () => {
    if (profileDialog.open && typeof profileDialog.close === "function") {
      profileDialog.close();
    } else {
      profileDialog.removeAttribute("open");
      document.body.classList.remove("modal-open");
      window.history.replaceState({}, "", closeUrl);
    }
  };

  profileDialog
    .querySelector("[data-modal-close]")
    ?.addEventListener("click", closeDialog);
  profileDialog.addEventListener("click", (event) => {
    const bounds = profileDialog.getBoundingClientRect();
    const outside =
      event.clientX < bounds.left ||
      event.clientX > bounds.right ||
      event.clientY < bounds.top ||
      event.clientY > bounds.bottom;
    if (outside) {
      closeDialog();
    }
  });
  profileDialog.addEventListener("close", () => {
    document.body.classList.remove("modal-open");
    window.history.replaceState({}, "", closeUrl);
  });

  document.body.classList.add("modal-open");
  if (typeof profileDialog.showModal === "function") {
    profileDialog.showModal();
  } else {
    profileDialog.setAttribute("open", "");
  }
}
