document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.querySelector('[data-login-form]');
  const logoutButtons = document.querySelectorAll('[data-logout]');
  const passwordInput = document.getElementById('password');
  const togglePasswordBtn = document.getElementById('togglePasswordBtn');
  const togglePasswordIcon = document.getElementById('togglePasswordIcon');

  if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const feedback = document.getElementById('loginFeedback');
      const formData = new FormData(loginForm);
      const payload = {
        email: formData.get('email'),
        password: formData.get('password'),
      };

      const submitBtn = loginForm.querySelector('[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Connexion…'; }
      if (feedback) feedback.classList.add('d-none');

      try {
        const response = await apiClient.post('/auth/login', payload);
        try {
          localStorage.setItem('eduschedule_token', response.data.token);
          localStorage.setItem('eduschedule_user', JSON.stringify(response.data.user));
        } catch (storageError) {
          // Le stockage peut être bloqué par le navigateur.
        }
        window.location.href = window.EduScheduleProAccess?.getDefaultRoute(response.data.user) || 'dashboard-admin.html';
      } catch (error) {
        if (feedback) {
          feedback.classList.remove('d-none');
          feedback.innerHTML = `<i class="bi bi-exclamation-circle me-2"></i>${error.message || 'Identifiants incorrects. Veuillez réessayer.'}`;
        }
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = 'Se connecter <i class="bi bi-arrow-right ms-2"></i>'; }
      }
    });
  }

  if (passwordInput && togglePasswordBtn && togglePasswordIcon) {
    togglePasswordBtn.addEventListener('click', () => {
      const showPassword = passwordInput.type === 'password';
      passwordInput.type = showPassword ? 'text' : 'password';
      togglePasswordIcon.className = `bi ${showPassword ? 'bi-eye-slash' : 'bi-eye'}`;
      togglePasswordBtn.setAttribute('aria-label', showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      togglePasswordBtn.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
    });
  }

  logoutButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      try {
        localStorage.removeItem('eduschedule_token');
        localStorage.removeItem('eduschedule_user');
      } catch (storageError) {
        // Ignorer si le stockage est bloqué
      }
      window.location.href = 'index.html';
    });
  });
});
