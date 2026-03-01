/**
 * GateWey - Dasher UI Theme System
 * Enhanced theme management based on Dasher UI patterns
 */

class DasherThemeSystem {
    constructor() {
        this.STORAGE_KEY = 'gatewey-dasher-theme';
        this.currentTheme = this.getStoredTheme() || this.getSystemTheme();
        this.observers = [];
        this.isInitialized = false;
        
        // Bind methods to preserve context
        this.toggle = this.toggle.bind(this);
        this.setTheme = this.setTheme.bind(this);
        this.handleToggleClick = this.handleToggleClick.bind(this);
        
        this.init();
    }
    
    /**
     * Initialize the theme system
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('ðŸŽ¨ Initializing Dasher Theme System...');
        
        // Apply initial theme immediately (prevent flash)
        this.applyTheme(this.currentTheme, false);
        
        // Wait for DOM to be ready, then set up everything else
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupThemeSystem());
        } else {
            this.setupThemeSystem();
        }
        
        this.isInitialized = true;
    }
    
    /**
     * Set up theme system after DOM is ready
     */
    setupThemeSystem() {
        // Set up toggle buttons
        this.setupToggleButtons();
        
        // Watch for new toggle buttons added dynamically
        this.observeNewElements();
        
        // Listen for system theme changes
        this.watchSystemTheme();
        
        // Set up logo switching
        this.updateLogos();
        
        // Update meta theme color for mobile browsers
        this.updateMetaThemeColor();
        
        console.log('âœ… Dasher Theme system initialized successfully');
        console.log('ðŸŽ¯ Current theme:', this.currentTheme);
    }
    
    /**
     * Get stored theme from localStorage
     */
    getStoredTheme() {
        try {
            return localStorage.getItem(this.STORAGE_KEY);
        } catch (e) {
            console.warn('Could not access localStorage:', e);
            return null;
        }
    }
    
    /**
     * Get system preferred theme
     */
    getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }
    
    /**
     * Store theme preference
     */
    storeTheme(theme) {
        try {
            localStorage.setItem(this.STORAGE_KEY, theme);
        } catch (e) {
            console.warn('Could not store theme preference:', e);
        }
    }
    
    /**
     * Apply theme to the document
     */
    applyTheme(theme, animate = true) {
        const html = document.documentElement;
        const body = document.body;
        
        // Prevent invalid themes
        if (!['light', 'dark'].includes(theme)) {
            console.warn('Invalid theme:', theme, 'defaulting to light');
            theme = 'light';
        }
        
        // Disable transitions during theme change to prevent flashing
        if (!animate) {
            html.style.setProperty('--theme-transition', 'none');
        }
        
        // Update data attribute (primary theme controller)
        html.setAttribute('data-theme', theme);
        
        // Also support Bootstrap's data-bs-theme for compatibility
        html.setAttribute('data-bs-theme', theme);
        
        // Update body class for compatibility
        if (theme === 'dark') {
            body.classList.add('theme-dark');
            body.classList.remove('theme-light');
        } else {
            body.classList.add('theme-light');
            body.classList.remove('theme-dark');
        }
        
        // Store theme and update current
        this.currentTheme = theme;
        this.storeTheme(theme);
        
        // Update toggle buttons
        this.updateToggleButtons();
        
        // Update logos
        this.updateLogos();
        
        // Update meta theme color for mobile browsers
        this.updateMetaThemeColor();
        
        // Re-enable transitions after a brief delay
        if (!animate) {
            setTimeout(() => {
                html.style.removeProperty('--theme-transition');
            }, 50);
        }
        
        // Dispatch custom event for other components
        this.dispatchThemeEvent(theme);
        
        console.log('ðŸŽ¨ Dasher theme applied:', theme);
    }
    
    /**
     * Toggle between light and dark theme
     */
    toggle() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }
    
    /**
     * Set specific theme
     */
    setTheme(theme) {
        if (['light', 'dark'].includes(theme)) {
            this.applyTheme(theme);
        } else {
            console.warn('Invalid theme requested:', theme);
        }
    }
    
    /**
     * Get current theme
     */
    getTheme() {
        return this.currentTheme;
    }
    
    /**
     * Setup toggle buttons
     */
    setupToggleButtons() {
        const toggleButtons = document.querySelectorAll('.theme-toggle, [data-theme-toggle]');
        
        toggleButtons.forEach(button => {
            // Remove existing listeners to prevent duplicates
            button.removeEventListener('click', this.handleToggleClick);
            button.addEventListener('click', this.handleToggleClick);
            
            // Update button appearance
            this.updateToggleButton(button);
        });
        
        console.log(`ðŸ”˜ Set up ${toggleButtons.length} theme toggle buttons`);
    }
    
    /**
     * Handle toggle button click
     */
    handleToggleClick(event) {
        event.preventDefault();
        event.stopPropagation();
        
        const button = event.currentTarget;
        
        // Add click animation
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = '';
        }, 150);
        
        this.toggle();
    }
    
    /**
     * Update toggle button appearance
     */
    updateToggleButton(button) {
        if (!button) return;
        
        const isDark = this.currentTheme === 'dark';
        
        // Update icon if it exists
        const icon = button.querySelector('i, svg, .icon');
        if (icon) {
            if (icon.classList) {
                icon.classList.remove('fa-sun', 'fa-moon', 'bi-sun', 'bi-moon');
                if (isDark) {
                    icon.classList.add('fa-sun', 'bi-sun');
                } else {
                    icon.classList.add('fa-moon', 'bi-moon');
                }
            }
        }
        
        // Update text content if no icon
        if (!icon && button.textContent) {
            button.textContent = isDark ? 'â˜€ï¸' : 'ðŸŒ™';
        }
        
        // Update ARIA label for accessibility
        button.setAttribute('aria-label', 
            isDark ? 'Switch to light mode' : 'Switch to dark mode'
        );
        
        // Update title tooltip
        button.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    }
    
    /**
     * Update all toggle buttons
     */
    updateToggleButtons() {
        const toggleButtons = document.querySelectorAll('.theme-toggle, [data-theme-toggle]');
        toggleButtons.forEach(button => this.updateToggleButton(button));
    }
    
    /**
     * Update logos based on theme
     */
    updateLogos() {
        // Method 1: Show/hide different logo elements
        const lightLogos = document.querySelectorAll('[data-theme-logo="light"]');
        const darkLogos = document.querySelectorAll('[data-theme-logo="dark"]');
        
        if (this.currentTheme === 'dark') {
            lightLogos.forEach(logo => logo.style.display = 'none');
            darkLogos.forEach(logo => logo.style.display = 'block');
        } else {
            lightLogos.forEach(logo => logo.style.display = 'block');
            darkLogos.forEach(logo => logo.style.display = 'none');
        }
        
        // Method 2: Update src attribute
        const adaptiveLogos = document.querySelectorAll('[data-logo-light][data-logo-dark]');
        adaptiveLogos.forEach(logo => {
            const lightSrc = logo.getAttribute('data-logo-light');
            const darkSrc = logo.getAttribute('data-logo-dark');
            
            if (this.currentTheme === 'dark' && darkSrc) {
                logo.src = darkSrc;
            } else if (lightSrc) {
                logo.src = lightSrc;
            }
        });
    }
    
    /**
     * Update meta theme color for mobile browsers
     */
    updateMetaThemeColor() {
        let metaThemeColor = document.querySelector('meta[name="theme-color"]');
        
        if (!metaThemeColor) {
            metaThemeColor = document.createElement('meta');
            metaThemeColor.name = 'theme-color';
            document.head.appendChild(metaThemeColor);
        }
        
        // Use CSS custom property values
        const rootStyles = getComputedStyle(document.documentElement);
        const themeColor = rootStyles.getPropertyValue('--bg-navbar').trim() || 
                          (this.currentTheme === 'dark' ? '#1c252e' : '#ffffff');
        
        metaThemeColor.content = themeColor;
    }
    
    /**
     * Watch for system theme changes
     */
    watchSystemTheme() {
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            mediaQuery.addListener((e) => {
                // Only auto-switch if no manual preference is stored
                if (!this.getStoredTheme()) {
                    const systemTheme = e.matches ? 'dark' : 'light';
                    this.setTheme(systemTheme);
                    console.log('ðŸ”„ System theme changed to:', systemTheme);
                }
            });
        }
    }
    
    /**
     * Observe for new elements (for dynamic content)
     */
    observeNewElements() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        // Check if the new node or its children have theme toggles
                        const newToggles = node.querySelectorAll?.('.theme-toggle, [data-theme-toggle]') || [];
                        if (newToggles.length > 0) {
                            this.setupToggleButtons();
                        }
                        
                        // Check if the new node itself is a toggle
                        if (node.matches?.('.theme-toggle, [data-theme-toggle]')) {
                            this.setupToggleButtons();
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        this.observers.push(observer);
    }
    
    /**
     * Dispatch custom theme event
     */
    dispatchThemeEvent(theme) {
        const event = new CustomEvent('themeChanged', {
            detail: { theme, previousTheme: this.currentTheme }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Add theme change listener
     */
    onThemeChange(callback) {
        document.addEventListener('themeChanged', callback);
    }
    
    /**
     * Remove theme change listener
     */
    offThemeChange(callback) {
        document.removeEventListener('themeChanged', callback);
    }
    
    /**
     * Cleanup method
     */
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers = [];
        
        const toggleButtons = document.querySelectorAll('.theme-toggle, [data-theme-toggle]');
        toggleButtons.forEach(button => {
            button.removeEventListener('click', this.handleToggleClick);
        });
        
        this.isInitialized = false;
        console.log('ðŸ—‘ï¸ Theme system destroyed');
    }
}

// Initialize theme system
let dasherTheme;

function _initDasherTheme() {
    if (window.DasherTheme) {
        // Already initialized — re-attach toggle buttons to new DOM elements after Turbo navigation
        dasherTheme = window.DasherTheme;
        dasherTheme.setupToggleButtons();
    } else {
        dasherTheme = new DasherThemeSystem();
        window.DasherTheme = dasherTheme;
        window.GateWeyTheme = dasherTheme;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', _initDasherTheme);
} else {
    _initDasherTheme();
}