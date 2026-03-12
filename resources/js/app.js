const setupAdminSidebar = () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (!sidebar || !overlay || !toggle) {
        return;
    }

    const desktopMedia = window.matchMedia('(min-width: 1024px)');

    const setSidebarState = (isOpen) => {
        sidebar.classList.toggle('is-open', isOpen);
        overlay.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('overflow-hidden', isOpen && !desktopMedia.matches);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    };

    const closeSidebar = () => setSidebarState(false);

    toggle.addEventListener('click', () => {
        const nextState = !sidebar.classList.contains('is-open');
        setSidebarState(nextState);
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    desktopMedia.addEventListener('change', (event) => {
        if (event.matches) {
            setSidebarState(false);
        }
    });

    setSidebarState(false);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupAdminSidebar, { once: true });
} else {
    setupAdminSidebar();
}
