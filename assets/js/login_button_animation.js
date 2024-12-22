"use strict";

window.addEventListener('DOMContentLoaded', () => {
    let login_buttons = document.querySelectorAll('.login-btn-container button[type="submit"]');

    for (let login_button of login_buttons) {
        login_button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            submitForm(login_button);

            return false;
        });
    }

    /**
     * Add an animation class to the submit button
     * @param login_button
     * @returns {boolean}
     */
    function submitForm(login_button) {
        if (login_button.classList.contains('button--loading')) {
            // Prevent multiple form submits
            return false;
        }

        login_button.classList.add('button--loading');
        login_button.setAttribute('disabled', '');

        let delay = login_button.dataset.delay ?? 1000;

        window.setTimeout(() => {
            let formBe = login_button.closest('form');
            let formFe = login_button.closest('form');

            if (formBe) {
                formBe.submit();
            }

            if (formFe) {
                formFe.submit();
            }

        }, parseInt(delay));
    }

});
