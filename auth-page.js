async function sendAuth(action, payload) {
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || "Wystąpił błąd.");
    return data;
}
const message = document.getElementById("pageMessage");
function showMessage(text, error = false) {
    message.textContent = text;
    message.classList.toggle("error", error);
    message.classList.toggle("success", !error);
}
const loginForm = document.getElementById("loginForm");
if (loginForm) loginForm.addEventListener("submit", async event => {
    event.preventDefault();
    const form = new FormData(loginForm);
    try {
        const data = await sendAuth("login", { email: form.get("email"), password: form.get("password") });
        showMessage(data.message + " Przenoszenie na stronę główną…");
        window.location.href = "index.php";
    } catch (error) { showMessage(error.message, true); }
});
const registerForm = document.getElementById("registerForm");
if (registerForm) registerForm.addEventListener("submit", async event => {
    event.preventDefault();
    const form = new FormData(registerForm);
    try {
        const data = await sendAuth("register", {
            username: form.get("username"), email: form.get("email"), password: form.get("password")
        });
        showMessage(data.message + " Przenoszenie na stronę główną…");
        window.location.href = "index.php";
    } catch (error) { showMessage(error.message, true); }
});

// Pokaż/ukryj hasło na obu stronach; nie wysyła formularza.
document.querySelectorAll('[data-toggle-password]').forEach(button => {
    const field = document.getElementById(button.dataset.togglePassword);
    if (!field) return;
    button.addEventListener('click', () => {
        const visible = field.type === 'password';
        field.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ukryj hasło' : 'Pokaż hasło';
        button.setAttribute('aria-pressed', String(visible));
    });
});
