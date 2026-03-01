/**
 * GateWey - JavaScript Dark Mode Fixes
 * Add this script after your enhanced theme system
 */

(function() {
    'use strict';
    
    // Function to fix specific problematic elements
    function fixProblematicElements() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        if (!isDark) return; // Only run fixes in dark mode
        
        console.log('🔧 Applying JavaScript dark mode fixes...');
        
        // Fix hardcoded background colors
        const hardcodedBackgrounds = [
            'white',
            '#ffffff', 
            '#fff',
            'rgb(255, 255, 255)',
            '#f8f9fa',
            '#e9ecef'
        ];
        
        // Fix hardcoded text colors
        const hardcodedTextColors = [
            '#2c3e50',
            '#495057',
            '#212529',
            'black',
            '#000',
            '#000000'
        ];
        
        // Get all elements
        const allElements = document.querySelectorAll('*');
        
        allElements.forEach(element => {
            const computedStyle = window.getComputedStyle(element);
            
            // Fix hardcoded backgrounds
            const bgColor = computedStyle.backgroundColor;
            if (hardcodedBackgrounds.some(color => bgColor.includes(color.replace('#', '')))) {
                element.style.backgroundColor = 'var(--bg-card)';
            }
            
            // Fix hardcoded text colors
            const textColor = computedStyle.color;
            if (hardcodedTextColors.some(color => textColor.includes(color.replace('#', '')))) {
                element.style.color = 'var(--text-primary)';
            }
        });
        
        // Fix specific problematic selectors
        const problematicSelectors = [
            '.text-muted',
            'small',
            '.small',
            '.card-footer.bg-white',
            '.bg-light',
            '.bg-white',
            'tbody',
            'tbody td',
            'tbody tr',
            '.table tbody',
            '.breadcrumb-item.active',
            '.page-link',
            '.list-group-item',
            '.alert',
            '.badge'
        ];
        
        problematicSelectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (selector.includes('text-muted') || selector === 'small' || selector === '.small') {
                    element.style.setProperty('color', 'var(--text-muted)', 'important');
                } else if (selector.includes('bg-white') || selector.includes('bg-light')) {
                    element.style.setProperty('background-color', 'var(--bg-card)', 'important');
                } else if (selector.includes('tbody')) {
                    element.style.setProperty('background-color', 'transparent', 'important');
                    element.style.setProperty('color', 'var(--text-primary)', 'important');
                } else if (selector.includes('breadcrumb')) {
                    element.style.setProperty('color', 'var(--text-muted)', 'important');
                } else {
                    // Apply general dark theme styles
                    element.style.setProperty('background-color', 'var(--bg-card)', 'important');
                    element.style.setProperty('color', 'var(--text-primary)', 'important');
                    element.style.setProperty('border-color', 'var(--border-color)', 'important');
                }
            });
        });
        
        // Fix badges specifically
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            if (badge.classList.contains('bg-success')) {
                badge.style.setProperty('background-color', 'var(--success)', 'important');
                badge.style.setProperty('color', 'white', 'important');
            } else if (badge.classList.contains('bg-warning')) {
                badge.style.setProperty('background-color', 'var(--warning)', 'important');
                badge.style.setProperty('color', '#000', 'important');
            } else if (badge.classList.contains('bg-danger')) {
                badge.style.setProperty('background-color', 'var(--danger)', 'important');
                badge.style.setProperty('color', 'white', 'important');
            } else if (badge.classList.contains('bg-info')) {
                badge.style.setProperty('background-color', 'var(--info)', 'important');
                badge.style.setProperty('color', 'white', 'important');
            } else if (badge.classList.contains('bg-secondary')) {
                badge.style.setProperty('background-color', 'var(--text-secondary)', 'important');
                badge.style.setProperty('color', 'white', 'important');
            } else if (badge.classList.contains('bg-primary')) {
                badge.style.setProperty('background-color', 'var(--primary)', 'important');
                badge.style.setProperty('color', 'white', 'important');
            }
        });
        
        // Fix table headers
        const tableHeaders = document.querySelectorAll('.table thead th, table thead th');
        tableHeaders.forEach(th => {
            th.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
            th.style.setProperty('color', 'var(--text-secondary)', 'important');
            th.style.setProperty('border-color', 'var(--border-color)', 'important');
        });
        
        // Fix pagination
        const pageLinks = document.querySelectorAll('.page-link');
        pageLinks.forEach(link => {
            const parentItem = link.closest('.page-item');
            if (parentItem && parentItem.classList.contains('active')) {
                link.style.setProperty('background-color', 'var(--primary)', 'important');
                link.style.setProperty('color', 'white', 'important');
                link.style.setProperty('border-color', 'var(--primary)', 'important');
            } else if (parentItem && parentItem.classList.contains('disabled')) {
                link.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
                link.style.setProperty('color', 'var(--text-muted)', 'important');
                link.style.setProperty('border-color', 'var(--border-color)', 'important');
            } else {
                link.style.setProperty('background-color', 'var(--bg-card)', 'important');
                link.style.setProperty('color', 'var(--text-primary)', 'important');
                link.style.setProperty('border-color', 'var(--border-color)', 'important');
            }
        });
        
        // Fix form elements with hardcoded styles
        const formElements = document.querySelectorAll('input, textarea, select, .form-control, .form-select');
        formElements.forEach(element => {
            if (!element.style.backgroundColor || element.style.backgroundColor === 'white') {
                element.style.setProperty('background-color', 'var(--bg-input)', 'important');
            }
            if (!element.style.color || element.style.color === 'black') {
                element.style.setProperty('color', 'var(--text-primary)', 'important');
            }
            element.style.setProperty('border-color', 'var(--border-color)', 'important');
        });
        
        // Fix any remaining white backgrounds
        const whiteElements = document.querySelectorAll('[style*="background-color: white"], [style*="background-color: #fff"], [style*="background-color: #ffffff"]');
        whiteElements.forEach(element => {
            element.style.setProperty('background-color', 'var(--bg-card)', 'important');
        });
        
        // Fix any remaining black text
        const blackTextElements = document.querySelectorAll('[style*="color: black"], [style*="color: #000"], [style*="color: #000000"]');
        blackTextElements.forEach(element => {
            element.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        console.log('✅ JavaScript dark mode fixes applied');
    }
    
    // Function to fix newly added elements
    function observeNewElements() {
        const observer = new MutationObserver((mutations) => {
            let needsFix = false;
            
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            needsFix = true;
                        }
                    });
                }
            });
            
            if (needsFix && document.documentElement.getAttribute('data-theme') === 'dark') {
                // Debounce the fixes
                clearTimeout(observer.fixTimeout);
                observer.fixTimeout = setTimeout(fixProblematicElements, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        return observer;
    }
    
    // Initialize fixes
    function initializeDarkModeFixes() {
        // Apply initial fixes
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fixProblematicElements);
        } else {
            fixProblematicElements();
        }
        
        // Listen for theme changes
        document.addEventListener('gateweythemechange', (e) => {
            if (e.detail.theme === 'dark') {
                setTimeout(fixProblematicElements, 50);
            }
        });
        
        // Also listen for legacy theme change events
        document.addEventListener('themeChanged', (e) => {
            if (e.detail.theme === 'dark') {
                setTimeout(fixProblematicElements, 50);
            }
        });
        
        // Set up observer for new elements
        observeNewElements();
        
        // Apply fixes periodically (as backup)
        setInterval(() => {
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                fixProblematicElements();
            }
        }, 5000); // Every 5 seconds
        
        console.log('🎯 Dark mode fixes system initialized');
    }
    
    // Function to manually trigger fixes (for debugging)
    window.fixDarkMode = function() {
        console.log('🔧 Manually fixing dark mode issues...');
        fixProblematicElements();
    };
    
    // Function to scan for problematic elements (for debugging)
    window.scanProblematicElements = function() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (!isDark) {
            console.log('Not in dark mode - skipping scan');
            return;
        }
        
        console.log('🔍 Scanning for problematic elements...');
        
        const issues = [];
        const allElements = document.querySelectorAll('*');
        
        allElements.forEach((element, index) => {
            const computedStyle = window.getComputedStyle(element);
            const bgColor = computedStyle.backgroundColor;
            const textColor = computedStyle.color;
            
            // Check for white backgrounds
            if (bgColor.includes('255, 255, 255') && !element.matches('.btn, .badge')) {
                issues.push({
                    element,
                    issue: 'White background',
                    selector: element.tagName.toLowerCase() + (element.className ? '.' + element.className.split(' ').join('.') : ''),
                    current: bgColor
                });
            }
            
            // Check for black text
            if (textColor.includes('0, 0, 0') || textColor.includes('33, 37, 41')) {
                issues.push({
                    element,
                    issue: 'Dark text',
                    selector: element.tagName.toLowerCase() + (element.className ? '.' + element.className.split(' ').join('.') : ''),
                    current: textColor
                });
            }
        });
        
        console.log(`Found ${issues.length} problematic elements:`, issues);
        return issues;
    };
    
    // Function to test theme switching
    window.testThemeSwitching = function() {
        console.log('🧪 Testing theme switching...');
        
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        console.log('Current theme:', currentTheme);
        
        if (window.themeManager) {
            console.log('Toggling theme via themeManager...');
            window.themeManager.toggle();
        } else {
            console.log('themeManager not found, manual toggle...');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            setTimeout(fixProblematicElements, 100);
        }
        
        setTimeout(() => {
            const newTheme = document.documentElement.getAttribute('data-theme') || 'light';
            console.log('Theme after toggle:', newTheme);
            
            if (newTheme === 'dark') {
                console.log('Running dark mode scan...');
                window.scanProblematicElements();
            }
        }, 200);
    };
    
    // Function to force theme fixes on specific selectors
    window.forceFixSelectors = function(selectors) {
        if (typeof selectors === 'string') {
            selectors = [selectors];
        }
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (!isDark) {
            console.log('Not in dark mode - no fixes needed');
            return;
        }
        
        console.log('🎯 Force fixing selectors:', selectors);
        
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            console.log(`Found ${elements.length} elements for selector: ${selector}`);
            
            elements.forEach(element => {
                element.style.setProperty('background-color', 'var(--bg-card)', 'important');
                element.style.setProperty('color', 'var(--text-primary)', 'important');
                element.style.setProperty('border-color', 'var(--border-color)', 'important');
            });
        });
    };
    
    // Add CSS class to body when dark mode fixes are active
    function markFixesActive() {
        if (document.documentElement.getAttribute('data-theme') === 'dark') {
            document.body.classList.add('dark-mode-fixes-active');
        } else {
            document.body.classList.remove('dark-mode-fixes-active');
        }
    }
    
    // Enhanced initialization with error handling
    function safeInitialize() {
        try {
            initializeDarkModeFixes();
            markFixesActive();
            
            // Mark fixes as loaded
            window.darkModeFixesLoaded = true;
            
            // Dispatch event to notify other scripts
            document.dispatchEvent(new CustomEvent('darkModeFixesReady', {
                detail: { timestamp: Date.now() }
            }));
            
        } catch (error) {
            console.error('Error initializing dark mode fixes:', error);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInitialize);
    } else {
        safeInitialize();
    }
    
    // Debug information
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        console.log('🔧 Dark Mode Debug Functions Available:');
        console.log('  - fixDarkMode() - Manually apply fixes');
        console.log('  - scanProblematicElements() - Find problem elements');
        console.log('  - testThemeSwitching() - Test theme toggle');
        console.log('  - forceFixSelectors(["selector1", "selector2"]) - Force fix specific selectors');
    }
    
})();