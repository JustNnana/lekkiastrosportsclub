/**
 * SCROLLING FIX - Mobile JavaScript
 * This fixes scrolling issues and improves mobile interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Applying scrolling fixes...');
    
    // ===== VARIABLES =====
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    let isMobileOpen = false;
    let overlay = null;
    
    // ===== CREATE PROPER OVERLAY =====
    function createSidebarOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'mobile-sidebar-overlay';
            document.body.appendChild(overlay);
            
            // Click overlay to close sidebar
            overlay.addEventListener('click', function() {
                if (isMobileOpen) {
                    toggleMobileSidebar();
                }
            });
        }
        return overlay;
    }
    
    // ===== FIXED SIDEBAR TOGGLE =====
    function toggleMobileSidebar() {
        if (window.innerWidth > 768) return;
        
        isMobileOpen = !isMobileOpen;
        
        if (!overlay) createSidebarOverlay();
        
        if (isMobileOpen) {
            // Open sidebar
            sidebar.classList.add('active');
            overlay.classList.add('active');
            
            // IMPORTANT: Don't set body overflow hidden
            // This was causing the scrolling issues
            // document.body.style.overflow = 'hidden'; // REMOVED
            
            // Prevent body scroll while keeping content scrollable
            document.body.style.position = 'fixed';
            document.body.style.top = `-${window.scrollY}px`;
            document.body.style.width = '100%';
            
        } else {
            // Close sidebar
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            
            // Restore body scroll position
            const scrollY = document.body.style.top;
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.width = '';
            window.scrollTo(0, parseInt(scrollY || '0') * -1);
        }
        
        console.log('📱 Mobile sidebar:', isMobileOpen ? 'opened' : 'closed');
    }
    
    // ===== SIDEBAR TOGGLE EVENT =====
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMobileSidebar();
        });
    }
    
    // ===== IMPROVED SWIPE GESTURE =====
    let startX = 0;
    let currentX = 0;
    let isDragging = false;
    let startScrollY = 0;
    
    if (sidebar) {
        sidebar.addEventListener('touchstart', function(e) {
            if (window.innerWidth <= 768 && isMobileOpen) {
                startX = e.touches[0].clientX;
                startScrollY = window.scrollY;
                isDragging = true;
                sidebar.style.transition = 'none';
            }
        }, { passive: true });
        
        sidebar.addEventListener('touchmove', function(e) {
            if (!isDragging || window.innerWidth > 768) return;
            
            currentX = e.touches[0].clientX;
            const deltaX = currentX - startX;
            
            // Prevent scrolling when swiping horizontally
            if (Math.abs(deltaX) > 10) {
                e.preventDefault();
            }
            
            // Only allow swiping left (closing)
            if (deltaX < 0) {
                const translateX = Math.max(deltaX, -280);
                sidebar.style.transform = `translateX(${translateX}px)`;
                
                // Adjust overlay opacity
                const opacity = Math.max(0, 1 + (deltaX / 280));
                overlay.style.opacity = opacity;
            }
        }, { passive: false });
        
        sidebar.addEventListener('touchend', function(e) {
            if (!isDragging || window.innerWidth > 768) return;
            
            isDragging = false;
            sidebar.style.transition = '';
            
            const deltaX = currentX - startX;
            
            // Close if swiped more than 50px left
            if (deltaX < -50) {
                toggleMobileSidebar();
            } else {
                // Snap back to open position
                sidebar.style.transform = 'translateX(0)';
                overlay.style.opacity = '1';
            }
        }, { passive: true });
    }
    
    // ===== FIX RESIZE ISSUES =====
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Close mobile sidebar when switching to desktop
            if (window.innerWidth > 768 && isMobileOpen) {
                // Reset everything
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                
                // Restore body
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                
                isMobileOpen = false;
                console.log('🖥️ Switched to desktop - sidebar closed');
            }
        }, 250);
    });
    
    // ===== FIX ORIENTATION CHANGE =====
    window.addEventListener('orientationchange', function() {
        // Close sidebar on orientation change to prevent issues
        if (isMobileOpen) {
            toggleMobileSidebar();
        }
        
        // Fix viewport height issues on mobile
        setTimeout(() => {
            // Force recalculation of viewport units
            document.documentElement.style.setProperty('--vh', `${window.innerHeight * 0.01}px`);
        }, 500);
    });
    
    // ===== FIX iOS SAFARI 100VH ISSUE =====
    function setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
    
    // ===== FIX SCROLL POSITION MEMORY =====
    let scrollPosition = 0;
    
    // Remember scroll position before opening sidebar
    function rememberScrollPosition() {
        scrollPosition = window.scrollY;
    }
    
    // Restore scroll position after closing sidebar
    function restoreScrollPosition() {
        window.scrollTo(0, scrollPosition);
    }
    
    // ===== SMOOTH SCROLLING FIX =====
    // Fix for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                const navbarHeight = document.querySelector('.navbar').offsetHeight;
                const bottomNavHeight = window.innerWidth <= 768 ? 70 : 0;
                
                window.scrollTo({
                    top: target.offsetTop - navbarHeight - 20,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // ===== FIX TABLE SCROLLING =====
    const tableContainers = document.querySelectorAll('.table-responsive');
    tableContainers.forEach(container => {
        // Fix horizontal scrolling on mobile
        container.style.webkitOverflowScrolling = 'touch';
        
        // Add scroll indicators
        const scrollIndicator = document.createElement('div');
        scrollIndicator.innerHTML = '← Scroll to see more →';
        scrollIndicator.style.cssText = `
            text-align: center;
            padding: 0.5rem;
            background: var(--bg-tertiary);
            color: var(--text-muted);
            font-size: 0.75rem;
            display: none;
        `;
        
        container.appendChild(scrollIndicator);
        
        // Check if scrolling is needed
        function checkScrollNeeded() {
            if (container.scrollWidth > container.clientWidth) {
                scrollIndicator.style.display = 'block';
            } else {
                scrollIndicator.style.display = 'none';
            }
        }
        
        checkScrollNeeded();
        window.addEventListener('resize', checkScrollNeeded);
    });
    
    // ===== FIX MODAL SCROLLING =====
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            // Fix modal scrolling on mobile
            const modalBody = this.querySelector('.modal-body');
            if (modalBody) {
                modalBody.style.webkitOverflowScrolling = 'touch';
            }
        });
    });
    
    // ===== FIX INPUT ZOOM ON iOS =====
    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        const inputs = document.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            // Prevent zoom on focus by ensuring font-size is at least 16px
            const computedStyle = window.getComputedStyle(input);
            if (parseInt(computedStyle.fontSize) < 16) {
                input.style.fontSize = '16px';
            }
        });
    }
    
    // ===== DEBUGGING FUNCTIONS =====
    window.debugScrolling = function() {
        console.log('=== SCROLL DEBUG INFO ===');
        console.log('Window size:', window.innerWidth + 'x' + window.innerHeight);
        console.log('Document scroll:', window.scrollY);
        console.log('Body overflow:', window.getComputedStyle(document.body).overflow);
        console.log('Body position:', window.getComputedStyle(document.body).position);
        console.log('Sidebar active:', sidebar?.classList.contains('active'));
        console.log('Mobile open:', isMobileOpen);
        console.log('Overlay exists:', !!overlay);
        console.log('Content height:', content?.scrollHeight);
        console.log('Viewport height:', window.innerHeight);
    };
    
    // ===== PERFORMANCE OPTIMIZATIONS =====
    
    // Throttle scroll events
    let scrollTimeout;
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Only process if scroll position actually changed
        if (currentScrollTop !== lastScrollTop) {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                // Auto-hide bottom navigation on scroll (optional)
                const bottomNav = document.querySelector('.mobile-bottom-nav');
                if (bottomNav && window.innerWidth <= 768) {
                    if (currentScrollTop > lastScrollTop && currentScrollTop > 100) {
                        // Scrolling down
                        bottomNav.style.transform = 'translateY(100%)';
                    } else {
                        // Scrolling up
                        bottomNav.style.transform = 'translateY(0)';
                    }
                }
                
                lastScrollTop = currentScrollTop;
            }, 10);
        }
    }, { passive: true });
    
    // ===== FINAL SETUP =====
    
    // Create overlay on page load
    createSidebarOverlay();
    
    // Fix any existing scroll issues
    document.body.style.overflowX = 'hidden';
    
    console.log('✅ Scrolling fixes applied successfully!');
    
    // Make debug function available globally
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        console.log('🐛 Debug mode: Call debugScrolling() to check scroll state');
    }
});