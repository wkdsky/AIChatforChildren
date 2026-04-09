document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('.password-toggle');

    toggles.forEach((toggle) => {
        const group = toggle.closest('.input-group');
        const input = group ? group.querySelector('input[type="password"], input[type="text"]') : null;

        if (!input) {
            return;
        }

        const syncToggleState = () => {
            const isVisible = input.type === 'text';
            toggle.textContent = isVisible ? 'Hide' : 'Show';
            toggle.classList.toggle('is-active', isVisible);
            toggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        };

        toggle.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            syncToggleState();
        });

        syncToggleState();
    });
});
