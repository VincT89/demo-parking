import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

function initialisePasswordToggles() {
    const showLabel = document.documentElement.dataset.showPassword || 'Show password';
    const hideLabel = document.documentElement.dataset.hidePassword || 'Hide password';

    document.querySelectorAll('input[type="password"]:not([data-password-toggle-ready])').forEach((input) => {
        input.dataset.passwordToggleReady = 'true';

        const wrapper = document.createElement('div');
        wrapper.classList.add('pm-password-field');

        ['block', 'mt-1', 'w-full', 'w-3/4'].forEach((className) => {
            if (input.classList.contains(className)) {
                wrapper.classList.add(className);
                input.classList.remove(className);
            }
        });

        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.classList.add('pm-password-input');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'pm-password-toggle';
        toggle.setAttribute('aria-label', showLabel);
        toggle.setAttribute('aria-pressed', 'false');
        toggle.title = showLabel;
        toggle.innerHTML = `
            <svg class="pm-password-icon pm-password-icon--show" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                <circle cx="12" cy="12" r="2.75" />
            </svg>
            <svg class="pm-password-icon pm-password-icon--hide" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M3 3l18 18" />
                <path d="M10.6 6.15A10.7 10.7 0 0 1 12 6c6 0 9.5 6 9.5 6a16.1 16.1 0 0 1-3.05 3.7M6.2 6.2A16.3 16.3 0 0 0 2.5 12s3.5 6 9.5 6c1.15 0 2.2-.22 3.15-.58M9.88 9.88a3 3 0 0 0 4.24 4.24" />
            </svg>
        `;

        toggle.addEventListener('click', () => {
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            wrapper.classList.toggle('is-password-visible', !isVisible);

            const label = isVisible ? showLabel : hideLabel;
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
            toggle.title = label;
        });

        wrapper.appendChild(toggle);
    });
}

function initialiseFormLabels() {
    let generatedId = 0;

    document.querySelectorAll('label:not([for])').forEach((label) => {
        const control = label.querySelector('input:not([type="hidden"]), select, textarea')
            || label.parentElement?.querySelector('input:not([type="hidden"]), select, textarea');

        if (!control) {
            return;
        }

        if (!control.id) {
            const namePart = (control.name || control.type || 'field')
                .replace(/[^a-zA-Z0-9_-]+/g, '-')
                .replace(/^-|-$/g, '')
                || 'field';

            do {
                generatedId += 1;
                control.id = `pm-${namePart}-${generatedId}`;
            } while (document.getElementById(control.id) !== control);
        }

        label.htmlFor = control.id;
    });
}

function initialiseLocaleSwitchers() {
    const switchers = [...document.querySelectorAll('.pm-locale-switcher')];

    if (!switchers.length) {
        return;
    }

    document.addEventListener('click', (event) => {
        switchers.forEach((switcher) => {
            if (switcher.open && !switcher.contains(event.target)) {
                switcher.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            switchers.forEach((switcher) => {
                switcher.open = false;
            });
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initialiseFormLabels();
        initialisePasswordToggles();
        initialiseLocaleSwitchers();
    });
} else {
    initialiseFormLabels();
    initialisePasswordToggles();
    initialiseLocaleSwitchers();
}
