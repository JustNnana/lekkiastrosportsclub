/**
 * GateWey - Permanent Clean Dark Mode Solution
 * This script automatically applies the clean dark mode styling permanently
 */

(function() {
    'use strict';
    
    // Enhanced resetToCleanDarkMode function
    function resetToCleanDarkMode() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (!isDark) return;
        
        console.log('🧹 Applying permanent clean dark mode...');
        
        // Remove ALL JavaScript-applied dark mode styles that create the "washed out" look
        const allElements = document.querySelectorAll('*');
        allElements.forEach(element => {
            // Remove any background-color set by JavaScript that uses CSS variables
            if (element.style.backgroundColor && element.style.backgroundColor.includes('var(--')) {
                element.style.removeProperty('background-color');
            }
            
            // Remove any color set by JavaScript that uses CSS variables
            if (element.style.color && element.style.color.includes('var(--')) {
                element.style.removeProperty('color');
            }
            
            // Remove any border-color set by JavaScript
            if (element.style.borderColor && element.style.borderColor.includes('var(--')) {
                element.style.removeProperty('border-color');
            }
            
            // Remove all !important flags that were added by JavaScript
            if (element.style.cssText.includes('!important')) {
                const cssText = element.style.cssText.replace(/!important/g, '');
                element.style.cssText = cssText;
            }
        });
        
        // Specifically target problematic elements that cause the washed out look
        
        // Fix table striping issues
        const tableRows = document.querySelectorAll('.table tbody tr, table tbody tr');
        tableRows.forEach(row => {
            row.style.removeProperty('background-color');
            row.style.removeProperty('color');
        });
        
        // Fix filter sections
        const filterSections = document.querySelectorAll('.filter-section, .card-body');
        filterSections.forEach(section => {
            section.style.removeProperty('background-color');
            section.style.removeProperty('color');
        });
        
        // Fix form controls
        const formControls = document.querySelectorAll('.form-control, .form-select, input, select, textarea');
        formControls.forEach(control => {
            control.style.removeProperty('background-color');
            control.style.removeProperty('color');
            control.style.removeProperty('border-color');
        });
        
        // Fix badges and status indicators
        const badges = document.querySelectorAll('.badge, .status-badge');
        badges.forEach(badge => {
            // Only remove if it was set by JavaScript (contains var(--)
            if (badge.style.backgroundColor && badge.style.backgroundColor.includes('var(--')) {
                badge.style.removeProperty('background-color');
            }
            if (badge.style.color && badge.style.color.includes('var(--')) {
                badge.style.removeProperty('color');
            }
        });
        
        // Force a repaint to ensure changes take effect
        document.body.style.display = 'none';
        document.body.offsetHeight; // Trigger reflow
        document.body.style.display = '';
        
        console.log('✅ Clean dark mode applied successfully');
    }
    
    // Auto-apply clean dark mode on page load
    function initPermanentCleanMode() {
        // Apply immediately if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(resetToCleanDarkMode, 50);
            });
        } else {
            setTimeout(resetToCleanDarkMode, 50);
        }
        
        // Apply after window load (in case some styles load later)
        window.addEventListener('load', () => {
            setTimeout(resetToCleanDarkMode, 100);
        });
        
        // Apply when theme changes to dark
        document.addEventListener('gateweythemechange', (e) => {
            if (e.detail.theme === 'dark') {
                setTimeout(resetToCleanDarkMode, 50);
            }
        });
        
        // Apply when any legacy theme change events fire
        document.addEventListener('themeChanged', (e) => {
            if (e.detail.theme === 'dark') {
                setTimeout(resetToCleanDarkMode, 50);
            }
        });
        
        // Watch for dynamic content that might get styled by other scripts
        const observer = new MutationObserver((mutations) => {
            let needsCleanup = false;
            
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const element = mutation.target;
                    // Check if JavaScript added problematic styling
                    if (element.style.backgroundColor && element.style.backgroundColor.includes('var(--') ||
                        element.style.color && element.style.color.includes('var(--')) {
                        needsCleanup = true;
                    }
                }
                
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if new elements have problematic styling
                            const styled = node.querySelectorAll('[style*="var(--"]');
                            if (styled.length > 0) {
                                needsCleanup = true;
                            }
                        }
                    });
                }
            });
            
            if (needsCleanup && document.documentElement.getAttribute('data-theme') === 'dark') {
                setTimeout(resetToCleanDarkMode, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style']
        });
        
        // Periodic cleanup (as backup)
        setInterval(() => {
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                resetToCleanDarkMode();
            }
        }, 5000); // Every 5 seconds
        
        console.log('🎯 Permanent clean dark mode system initialized');
    }
    
    // Enhanced debugging functions
    window.resetToCleanDarkMode = resetToCleanDarkMode;
    
    window.checkDarkModeState = function() {
        console.log('🔍 Current Dark Mode State:');
        console.log('- Theme:', document.documentElement.getAttribute('data-theme'));
        console.log('- Body background:', getComputedStyle(document.body).backgroundColor);
        
        // Check for problematic elements
        const problematicElements = document.querySelectorAll('[style*="var(--"]');
        console.log('- Elements with JS-applied styles:', problematicElements.length);
        
        if (problematicElements.length > 0) {
            console.log('- Sample problematic elements:', Array.from(problematicElements).slice(0, 3));
        }
        
        // Check table state
        const tableRows = document.querySelectorAll('.table tbody tr');
        if (tableRows.length > 0) {
            const firstRowBg = getComputedStyle(tableRows[0]).backgroundColor;
            const secondRowBg = tableRows[1] ? getComputedStyle(tableRows[1]).backgroundColor : 'N/A';
            console.log('- First table row bg:', firstRowBg);
            console.log('- Second table row bg:', secondRowBg);
            console.log('- Rows have consistent bg:', firstRowBg === secondRowBg);
        }
    };
    
    window.forceCleanDarkMode = function() {
        console.log('🧹 Forcing clean dark mode...');
        resetToCleanDarkMode();
        setTimeout(() => {
            window.checkDarkModeState();
        }, 100);
    };
    
    // Initialize the permanent system
    initPermanentCleanMode();
    
    // Mark as loaded
    window.permanentCleanDarkModeLoaded = true;
    
    console.log('✅ Permanent Clean Dark Mode script loaded');
    
})();