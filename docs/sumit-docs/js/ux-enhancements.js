/**
 * UX Enhancements for SUMIT Gateway Documentation
 * Version: 2.0.0
 * Developer-Friendly Improvements
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        improveSearchUX();
        addBreadcrumbs();
        addCategoryTooltips();
        enhanceNavigationHighlighting();
        addKeyboardShortcuts();
    }

    /**
     * 1. Improve Search UX
     * - Change placeholder to developer-friendly text
     * - Add search icon
     * - Remove technical keyboard hints on mobile
     */
    function improveSearchUX() {
        const searchField = document.querySelector('.phpdocumentor-search__field');
        if (!searchField) return;

        // Better placeholder
        const isMobile = window.innerWidth <= 768;
        searchField.placeholder = isMobile
            ? 'Search classes, methods...'
            : 'Search classes, methods, namespaces... (Press / to focus)';

        // Update on resize
        window.addEventListener('resize', function() {
            const mobile = window.innerWidth <= 768;
            searchField.placeholder = mobile
                ? 'Search classes, methods...'
                : 'Search classes, methods, namespaces... (Press / to focus)';
        });

        // Ensure icon is visible
        const searchIcon = document.querySelector('.phpdocumentor-search svg');
        if (searchIcon) {
            searchIcon.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * 2. Add Breadcrumbs for Context
     * - Shows current location: Home > LaravelSumitGateway > Classes
     */
    function addBreadcrumbs() {
        const content = document.querySelector('.phpdocumentor-content');
        if (!content) return;

        // Check if breadcrumbs already exist
        if (document.querySelector('.phpdocumentor-breadcrumbs')) return;

        // Get page title to determine location
        const title = document.querySelector('.phpdocumentor-content__title');
        if (!title) return;

        const breadcrumbs = document.createElement('nav');
        breadcrumbs.className = 'phpdocumentor-breadcrumbs';
        breadcrumbs.setAttribute('aria-label', 'Breadcrumb');

        // Build breadcrumb trail
        const parts = [];
        parts.push('<a href="index.html">Home</a>');

        const path = window.location.pathname;
        if (path.includes('classes/')) {
            parts.push('<span class="phpdocumentor-breadcrumbs__separator">›</span>');
            parts.push('<a href="namespaces/default.html">OfficeGuy</a>');
            parts.push('<span class="phpdocumentor-breadcrumbs__separator">›</span>');
            parts.push('<span>LaravelSumitGateway</span>');
            parts.push('<span class="phpdocumentor-breadcrumbs__separator">›</span>');
            parts.push('<span>Classes</span>');
        } else if (path.includes('namespaces/')) {
            parts.push('<span class="phpdocumentor-breadcrumbs__separator">›</span>');
            parts.push('<span>Namespaces</span>');
        }

        breadcrumbs.innerHTML = parts.join(' ');

        // Insert before title
        title.parentNode.insertBefore(breadcrumbs, title);
    }

    /**
     * 3. Add Category Tooltips
     * - Explains difference between Namespaces and Packages
     */
    function addCategoryTooltips() {
        const categories = document.querySelectorAll('.phpdocumentor-sidebar__category-header');

        categories.forEach(function(header) {
            const text = header.textContent.trim().toLowerCase();
            let helpText = '';

            if (text.includes('namespace')) {
                helpText = 'Logical code structure';
            } else if (text.includes('package')) {
                helpText = 'Distribution structure';
            } else if (text.includes('report')) {
                helpText = 'Code quality insights';
            } else if (text.includes('indices')) {
                helpText = 'Quick reference lists';
            }

            if (helpText) {
                const help = document.createElement('span');
                help.className = 'category-help';
                help.textContent = helpText;
                help.style.cssText = 'display:block;font-size:12px;font-weight:400;color:#64748b;text-transform:none;letter-spacing:0;margin-top:0.25rem;opacity:0.8;';
                header.appendChild(help);
            }
        });
    }

    /**
     * 4. Enhance Navigation Highlighting
     * - Highlights current page in sidebar
     */
    function enhanceNavigationHighlighting() {
        const currentPath = window.location.pathname;
        const sidebarLinks = document.querySelectorAll('.phpdocumentor-sidebar a');

        sidebarLinks.forEach(function(link) {
            if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
            }
        });
    }

    /**
     * 5. Add Keyboard Shortcuts
     * - "/" to focus search
     * - "Escape" to close modals
     */
    function addKeyboardShortcuts() {
        const searchField = document.querySelector('.phpdocumentor-search__field');

        document.addEventListener('keydown', function(e) {
            // "/" to focus search (only if not in input already)
            if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
                e.preventDefault();
                if (searchField) {
                    searchField.focus();
                }
            }

            // Escape to close search results
            if (e.key === 'Escape') {
                const searchResults = document.querySelector('.phpdocumentor-search-results');
                if (searchResults && !searchResults.classList.contains('phpdocumentor-search-results--hidden')) {
                    searchResults.classList.add('phpdocumentor-search-results--hidden');
                }
                if (searchField) {
                    searchField.blur();
                }
            }
        });
    }

})();
