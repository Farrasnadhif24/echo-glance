
document.addEventListener('DOMContentLoaded', function() {

    const roleRadios = document.querySelectorAll('input[name="role"]');
    const strFieldContainer = document.getElementById('strFieldContainer');
    const strInput = document.getElementById('str_number');

    function toggleSTRField() {
        const selectedRole = document.querySelector('input[name="role"]:checked');
        if (selectedRole && strFieldContainer) {
            if (selectedRole.value === 'tenaga_medis') {
                strFieldContainer.classList.add('visible');
                if (strInput) strInput.setAttribute('required', 'required');
            } else {
                strFieldContainer.classList.remove('visible');
                if (strInput) strInput.removeAttribute('required');
            }
        }
    }

    roleRadios.forEach(radio => {
        radio.addEventListener('change', toggleSTRField);
    });

    toggleSTRField();

    const password = document.getElementById('reg-password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchError = document.getElementById('passwordMatchError');

    function checkPasswordMatch() {
        if (confirmPassword && password && matchError) {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                matchError.classList.remove('hidden');
                confirmPassword.classList.add('error');
            } else {
                matchError.classList.add('hidden');
                confirmPassword.classList.remove('error');
            }
        }
    }

    if (confirmPassword) {
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
    if (password) {
        password.addEventListener('input', checkPasswordMatch);
    }

    const toggleRegPassword = document.getElementById('toggleRegPassword');
    if (toggleRegPassword && password) {
        toggleRegPassword.addEventListener('click', function() {
            password.type = password.type === 'password' ? 'text' : 'password';
            
            if (password.type === 'password') {
                this.innerHTML = '<i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
            } else {
                this.innerHTML = '<i data-lucide="eye-off" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
            }
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                e.preventDefault();
                checkPasswordMatch();
                confirmPassword.focus();
                return;
            }

            // Let the browser handle the submission natively
            const btn = document.getElementById('btnRegister');
            if (btn) {
                btn.classList.add('btn-loading');
                // Do NOT disable the button, as it can cancel the submission on some browsers
            }
        });
    }
});
