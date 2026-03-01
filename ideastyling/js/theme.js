/**
 * GateWey Enhanced Theme System
 * Clean, simple, and robust dark mode implementation
 * 
 * Usage:
 * 1. Replace your current theme.js with this file
 * 2. Update your CSS variables (see accompanying CSS)
 * 3. Add data-theme attribute support to your components
 */

class GateWeyTheme {
    constructor() {
        this.STORAGE_KEY = 'gatewey-theme';
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
        
        console.log('🎨 Initializing GateWey Theme System...');
        
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
        
        console.log('✅ Theme system initialized successfully');
        console.log('🎯 Current theme:', this.currentTheme);
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
        
        console.log('🎨 Theme applied:', theme);
    }
    
    /**
     * Toggle between light and dark theme
     */
    toggle() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
        return newTheme;
    }
    
    /**
     * Set specific theme
     */
    setTheme(theme) {
        if (theme !== this.currentTheme) {
            this.applyTheme(theme, true);
        }
    }
    
    /**
     * Get current theme
     */
    getTheme() {
        return this.currentTheme;
    }
    
    /**
     * Check if current theme is dark
     */
    isDark() {
        return this.currentTheme === 'dark';
    }
    
    /**
     * Set up all theme toggle buttons
     */
    setupToggleButtons() {
        const toggles = document.querySelectorAll('.theme-toggle, [data-theme-toggle]');
        
        toggles.forEach((button, index) => {
            // Remove any existing listeners
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            // Add event listeners
            newButton.addEventListener('click', this.handleToggleClick);
            newButton.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.handleToggleClick(e);
                }
            });
            
            // Ensure proper attributes
            newButton.setAttribute('aria-label', this.getToggleAriaLabel());
            newButton.setAttribute('title', this.getToggleTitle());
        });
        
        this.updateToggleButtons();
        console.log('🔘 Set up', toggles.length, 'toggle buttons');
    }
    
    /**
     * Handle toggle button clicks
     */
    handleToggleClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Add visual feedback
        const button = e.currentTarget;
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
            button.style.transform = '';
        }, 150);
        
        this.toggle();
    }
    
    /**
     * Update all toggle button states
     */
    updateToggleButtons() {
        const toggles = document.querySelectorAll('.theme-toggle, [data-theme-toggle]');
        const isDark = this.isDark();
        
        toggles.forEach(button => {
            // Update icon
            const icon = button.querySelector('i, .icon');
            if (icon) {
                if (icon.classList.contains('fa-moon') || icon.classList.contains('fa-sun')) {
                    icon.className = `fas ${isDark ? 'fa-sun' : 'fa-moon'}`;
                }
            }
            
            // Update text if present
            const text = button.querySelector('.toggle-text');
            if (text) {
                text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            }
            
            // Update attributes
            button.setAttribute('aria-label', this.getToggleAriaLabel());
            button.setAttribute('title', this.getToggleTitle());
            button.setAttribute('data-current-theme', this.currentTheme);
        });
    }
    
    /**
     * Get toggle button aria label
     */
    getToggleAriaLabel() {
        return `Switch to ${this.isDark() ? 'light' : 'dark'} mode`;
    }
    
    /**
     * Get toggle button title
     */
    getToggleTitle() {
        return `Switch to ${this.isDark() ? 'light' : 'dark'} mode`;
    }
    
    /**
     * Update logos based on theme
     */
    updateLogos() {
        // Method 1: Show/hide different logo elements
        document.querySelectorAll('[data-theme-logo]').forEach(logo => {
            const logoTheme = logo.getAttribute('data-theme-logo');
            logo.style.display = (logoTheme === this.currentTheme) ? 'block' : 'none';
        });
        
        // Method 2: Update src attribute of single logo
        document.querySelectorAll('[data-logo-light][data-logo-dark]').forEach(img => {
            const lightSrc = img.getAttribute('data-logo-light');
            const darkSrc = img.getAttribute('data-logo-dark');
            img.src = this.isDark() ? darkSrc : lightSrc;
        });
        
        // Method 3: CSS background image switching (for elements with data-logo-bg)
        document.querySelectorAll('[data-logo-bg]').forEach(element => {
            const lightBg = element.getAttribute('data-logo-light') || element.getAttribute('data-logo-bg');
            const darkBg = element.getAttribute('data-logo-dark') || element.getAttribute('data-logo-bg');
            
            if (lightBg && darkBg) {
                element.style.backgroundImage = `url(${this.isDark() ? darkBg : lightBg})`;
            }
        });
    }
    
    /**
     * Update meta theme color for mobile browsers
     */
    updateMetaThemeColor() {
        let metaTag = document.querySelector('meta[name="theme-color"]');
        
        if (!metaTag) {
            metaTag = document.createElement('meta');
            metaTag.name = 'theme-color';
            document.head.appendChild(metaTag);
        }
        
        // Use CSS custom property values if available
        const rootStyles = getComputedStyle(document.documentElement);
        const navbarColor = rootStyles.getPropertyValue('--bg-navbar').trim() || 
                           (this.isDark() ? '#161b22' : '#ffffff');
        
        metaTag.content = navbarColor;
    }
    
    /**
     * Watch for system theme changes
     */
    watchSystemTheme() {
        if (!window.matchMedia) return;
        
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleSystemThemeChange = (e) => {
            // Only apply system theme if user hasn't manually set a preference
            if (!this.getStoredTheme()) {
                const systemTheme = e.matches ? 'dark' : 'light';
                this.applyTheme(systemTheme, true);
            }
        };
        
        mediaQuery.addEventListener('change', handleSystemThemeChange);
    }
    
    /**
     * Watch for new elements that need theme setup
     */
    observeNewElements() {
        if (!window.MutationObserver) return;
        
        const observer = new MutationObserver((mutations) => {
            let needsUpdate = false;
            
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check for new toggle buttons
                        if (node.matches?.('.theme-toggle, [data-theme-toggle]') ||
                            node.querySelector?.('.theme-toggle, [data-theme-toggle]')) {
                            needsUpdate = true;
                        }
                        
                        // Check for new logo elements
                        if (node.matches?.('[data-theme-logo], [data-logo-light], [data-logo-dark]') ||
                            node.querySelector?.('[data-theme-logo], [data-logo-light], [data-logo-dark]')) {
                            needsUpdate = true;
                        }
                    }
                });
            });
            
            if (needsUpdate) {
                // Debounce updates
                clearTimeout(this.updateTimeout);
                this.updateTimeout = setTimeout(() => {
                    this.setupToggleButtons();
                    this.updateLogos();
                }, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        this.observers.push(observer);
    }
    
    /**
     * Dispatch theme change event
     */
    dispatchThemeEvent(theme) {
        try {
            const event = new CustomEvent('gateweythemechange', {
                detail: {
                    theme,
                    isDark: theme === 'dark',
                    timestamp: Date.now()
                },
                bubbles: true
            });
            document.dispatchEvent(event);
            
            // Also dispatch the legacy event name for compatibility
            const legacyEvent = new CustomEvent('themeChanged', {
                detail: { theme },
                bubbles: true
            });
            document.dispatchEvent(legacyEvent);
        } catch (e) {
            console.warn('Could not dispatch theme event:', e);
        }
    }
    
    /**
     * Reset theme to system default
     */
    reset() {
        try {
            localStorage.removeItem(this.STORAGE_KEY);
        } catch (e) {
            console.warn('Could not clear theme preference:', e);
        }
        
        const systemTheme = this.getSystemTheme();
        this.applyTheme(systemTheme, true);
    }
    
    /**
     * Clean up observers and event listeners
     */
    destroy() {
        this.observers.forEach(observer => observer.disconnect());
        this.observers = [];
        this.isInitialized = false;
        
        // Remove global references
        if (window.themeManager === this) {
            delete window.themeManager;
        }
    }
    
    /**
     * Debug information
     */
    debug() {
        console.group('🎨 GateWey Theme Debug Info');
        console.log('Current theme:', this.currentTheme);
        console.log('Is dark mode:', this.isDark());
        console.log('Stored preference:', this.getStoredTheme());
        console.log('System preference:', this.getSystemTheme());
        console.log('Data attribute:', document.documentElement.getAttribute('data-theme'));
        console.log('Toggle buttons:', document.querySelectorAll('.theme-toggle, [data-theme-toggle]').length);
        console.log('Logo elements:', document.querySelectorAll('[data-theme-logo], [data-logo-light], [data-logo-dark]').length);
        
        // Test CSS variables
        const styles = getComputedStyle(document.documentElement);
        console.log('CSS Variables:');
        console.log('  --bg-body:', styles.getPropertyValue('--bg-body'));
        console.log('  --text-primary:', styles.getPropertyValue('--text-primary'));
        console.log('  --bg-card:', styles.getPropertyValue('--bg-card'));
        
        console.groupEnd();
    }
}

// Auto-initialize when script loads
let themeManager;

function initializeTheme() {
    if (!themeManager) {
        themeManager = new GateWeyTheme();
        
        // Make globally accessible
        window.themeManager = themeManager;
        
        // Legacy compatibility functions
        window.getCurrentTheme = () => themeManager.getTheme();
        window.setTheme = (theme) => themeManager.setTheme(theme);
        window.toggleTheme = () => themeManager.toggle();
        window.isDarkMode = () => themeManager.isDark();
        
        // Debug function (only in development)
        if (window.location.hostname === 'localhost' || 
            window.location.hostname.includes('dev') ||
            window.location.search.includes('debug')) {
            window.debugTheme = () => themeManager.debug();
        }
    }
}

// Initialize immediately to prevent flash of wrong theme
initializeTheme();

// Also initialize on DOMContentLoaded as backup
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTheme);
} else {
    // DOM already loaded, run initialization
    setTimeout(initializeTheme, 0);
}