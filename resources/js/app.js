/**
 * Prativa Stock Auditing & Procurement - Frontend Scripts
 *
 * Preserves sidebar scroll position across page navigation and ensures
 * that the currently active navigation item remains visible in the sidebar.
 */

(function () {
    const STORAGE_KEY = 'prativa_sidebar_scroll';

    function getSidebarNav() {
        return document.getElementById('sidebar-nav');
    }

    function saveSidebarScroll(nav) {
        if (!nav) nav = getSidebarNav();
        if (!nav) return;
        sessionStorage.setItem(STORAGE_KEY, Math.round(nav.scrollTop).toString());
    }

    function restoreSidebarScroll() {
        const nav = getSidebarNav();
        if (!nav) return;

        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved !== null) {
            const parsed = parseInt(saved, 10);
            if (!isNaN(parsed) && parsed >= 0) {
                nav.scrollTop = parsed;
            }
        }

        // Verify if active item is within the visible area of the sidebar nav
        const activeItem = nav.querySelector('[data-nav-active="true"]');
        if (activeItem) {
            const navRect = nav.getBoundingClientRect();
            const itemRect = activeItem.getBoundingClientRect();

            // If item is scrolled above top of nav
            if (itemRect.top < navRect.top + 12) {
                nav.scrollTop += (itemRect.top - navRect.top) - 16;
                saveSidebarScroll(nav);
            }
            // If item is scrolled below bottom of nav
            else if (itemRect.bottom > navRect.bottom - 12) {
                nav.scrollTop += (itemRect.bottom - navRect.bottom) + 16;
                saveSidebarScroll(nav);
            }
        }
    }

    function bindSidebarEvents() {
        const nav = getSidebarNav();
        if (!nav || nav.dataset.scrollBound === 'true') return;

        nav.dataset.scrollBound = 'true';

        let scrollDebounce = null;
        nav.addEventListener('scroll', () => {
            if (scrollDebounce) clearTimeout(scrollDebounce);
            scrollDebounce = setTimeout(() => {
                saveSidebarScroll(nav);
            }, 50);
        }, { passive: true });

        nav.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && nav.contains(link)) {
                saveSidebarScroll(nav);
            }
        });
    }

    let restoreTimer = null;
    function syncSidebar() {
        bindSidebarEvents();
        restoreSidebarScroll();
        requestAnimationFrame(() => {
            restoreSidebarScroll();
        });
        if (restoreTimer) clearTimeout(restoreTimer);
        restoreTimer = setTimeout(() => {
            restoreSidebarScroll();
        }, 80);
    }

    // Save scroll position immediately before Livewire navigation or page unload
    document.addEventListener('livewire:navigating', () => {
        saveSidebarScroll();
    });

    window.addEventListener('beforeunload', () => {
        saveSidebarScroll();
    });

    // Restore on navigation & initial load
    document.addEventListener('livewire:navigated', syncSidebar);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncSidebar);
    } else {
        syncSidebar();
    }
})();
