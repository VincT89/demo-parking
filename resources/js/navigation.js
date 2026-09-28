export function initializeNavigation() {
    const sidebar = document.getElementById('pm-sidebar');
    const content = document.getElementById('pm-content');
    if (!sidebar || !content) return;

    const overlay = document.getElementById('pm-sidebar-overlay');
    const menuButton = document.querySelector('.pm-mobile-menu-btn');
    const collapseButton = document.querySelector('.pm-sidebar-toggle');
    const closeButton = document.querySelector('.pm-sidebar-close');
    const mobile = window.matchMedia('(max-width: 1024px)');
    let collapsed = false;
    let open = false;
    try { collapsed = localStorage.getItem('pm-sidebar-collapsed') === 'true'; } catch { /* Storage can be unavailable. */ }

    function update() {
        const compact = !mobile.matches && collapsed;
        sidebar.classList.toggle('collapsed', compact);
        content.classList.toggle('collapsed', compact);
        sidebar.classList.toggle('mobile-open', open);
        overlay.classList.toggle('active', open);
        sidebar.inert = mobile.matches && !open;
        content.inert = open;
        document.body.classList.toggle('pm-sidebar-open', open);
        menuButton?.setAttribute('aria-expanded', String(open));
        collapseButton.setAttribute('aria-expanded', String(!compact));
        collapseButton.title = compact ? document.documentElement.dataset.expandMenu : document.documentElement.dataset.collapseMenu;
        collapseButton.setAttribute('aria-label', collapseButton.title);
    }

    function close(restoreFocus = true) {
        open = false;
        update();
        if (restoreFocus && mobile.matches) menuButton?.focus();
    }

    collapseButton.addEventListener('click', () => {
        collapsed = !collapsed;
        try { localStorage.setItem('pm-sidebar-collapsed', String(collapsed)); } catch { /* Keep the current page usable. */ }
        update();
    });
    menuButton?.addEventListener('click', () => {
        open = true;
        update();
        closeButton.focus();
    });
    closeButton.addEventListener('click', () => close());
    overlay.addEventListener('click', () => close());
    mobile.addEventListener('change', () => close(false));
    document.addEventListener('keydown', event => {
        if (!open) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key !== 'Tab') return;
        const controls = [...sidebar.querySelectorAll('a[href], button, input, select')]
            .filter(element => !element.disabled && element.getClientRects().length);
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    update();
}

export function initializeDialogs() {
    document.querySelectorAll('[data-open-dialog]').forEach(button => {
        button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal());
    });
    document.querySelectorAll('[data-close-dialog]').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
}
