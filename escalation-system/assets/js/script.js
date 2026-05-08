document.addEventListener('DOMContentLoaded', () => {
    const affectedInput = document.querySelector('[name="number_of_users_affected"]');
    const totalInput = document.querySelector('[name="total_number_of_users"]');
    const percentInput = document.querySelector('[name="affected_user_percentage"]');

    const computePercentage = () => {
        if (!affectedInput || !totalInput || !percentInput) {
            return;
        }

        const affected = Number(affectedInput.value || 0);
        const total = Math.max(Number(totalInput.value || 1), 1);
        const percentage = ((affected / total) * 100).toFixed(2);
        percentInput.value = percentage;
    };

    [affectedInput, totalInput].forEach((field) => {
        if (field) {
            field.addEventListener('input', computePercentage);
        }
    });

    computePercentage();

    const toggle = document.querySelector('[data-notification-toggle]');
    const panel = document.querySelector('[data-notification-panel]');
    if (toggle && panel) {
        toggle.addEventListener('click', () => panel.classList.toggle('open'));
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.notification-menu')) {
                panel.classList.remove('open');
            }
        });
    }
});
