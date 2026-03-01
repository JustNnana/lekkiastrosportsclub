/**
 * Enhanced Marketplace Mobile JavaScript
 * Modern mobile interactions and e-commerce features
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('🛒 Initializing marketplace mobile enhancements...');
    
    // ===== MOBILE PRODUCT GRID ENHANCEMENTS =====
    
    // Lazy loading for product images
    function initLazyLoading() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    img.removeAttribute('data-loading');
                    observer.unobserve(img);
                    
                    // Add fade-in animation
                    img.addEventListener('load', () => {
                        img.style.opacity = '1';
                    });
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            img.setAttribute('data-loading', 'lazy');
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
            imageObserver.observe(img);
        });
    }

    // Enhanced product card interactions
    function initProductCardEnhancements() {
        const productCards = document.querySelectorAll('.product-card');
        
        productCards.forEach(card => {
            // Add touch feedback
            card.addEventListener('touchstart', function(e) {
                this.style.transform = 'scale(0.98)';
            }, { passive: true });
            
            card.addEventListener('touchend', function(e) {
                setTimeout(() => {
                    this.style.transform = '';
                }, 100);
            }, { passive: true });
            
            card.addEventListener('touchcancel', function(e) {
                this.style.transform = '';
            }, { passive: true });

            // Add staggered animation for initial load
            const index = Array.from(productCards).indexOf(card);
            card.style.animationDelay = `${index * 0.1}s`;
        });
    }

    // Enhanced wishlist functionality
    function initWishlistEnhancements() {
        const wishlistBtns = document.querySelectorAll('.wishlist-btn');
        
        wishlistBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.dataset.productId;
                const isActive = this.classList.contains('active');
                
                // Add haptic feedback
                if (navigator.vibrate) {
                    navigator.vibrate(isActive ? [10] : [10, 50, 10]);
                }
                
                // Optimistic UI update
                this.classList.toggle('active');
                
                // Update icon with animation
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.remove('far', 'fas');
                    icon.classList.add(this.classList.contains('active') ? 'fas' : 'far');
                }
                
                // Show toast notification
                showMobileToast(
                    isActive ? 'Removed from wishlist' : 'Added to wishlist',
                    isActive ? 'info' : 'success',
                    2000
                );
                
                // Send to backend (implement your wishlist API here)
                updateWishlist(productId, !isActive);
            });
        });
    }

    // ===== MOBILE SEARCH ENHANCEMENTS =====
    
    // Enhanced search with autocomplete
    function initMobileSearch() {
        const searchInput = document.querySelector('.marketplace-search input');
        const searchForm = document.querySelector('.marketplace-search form');
        
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        showSearchSuggestions(query);
                    }, 300);
                } else {
                    hideSearchSuggestions();
                }
            });
            
            // Enhanced search form submission
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    const query = searchInput.value.trim();
                    if (query.length < 2) {
                        e.preventDefault();
                        showMobileToast('Please enter at least 2 characters', 'warning');
                        searchInput.focus();
                    }
                });
            }
        }
    }

    // Search suggestions functionality
    function showSearchSuggestions(query) {
        // Create suggestions dropdown if it doesn't exist
        let suggestionsContainer = document.querySelector('.search-suggestions');
        if (!suggestionsContainer) {
            suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'search-suggestions';
            suggestionsContainer.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg-card);
                border: 1px solid var(--border-primary);
                border-radius: 0 0 12px 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                max-height: 200px;
                overflow-y: auto;
                display: none;
            `;
            
            const searchContainer = document.querySelector('.search-input-group');
            if (searchContainer) {
                searchContainer.style.position = 'relative';
                searchContainer.appendChild(suggestionsContainer);
            }
        }
        
        // Mock suggestions (replace with actual API call)
        const mockSuggestions = [
            'Electronics',
            'Furniture',
            'Clothing',
            'Books',
            'Sports Equipment'
        ].filter(item => item.toLowerCase().includes(query.toLowerCase()));
        
        if (mockSuggestions.length > 0) {
            suggestionsContainer.innerHTML = mockSuggestions.map(suggestion => `
                <div class="suggestion-item" style="padding: 0.75rem; border-bottom: 1px solid var(--border-primary); cursor: pointer; transition: background 0.2s;">
                    <i class="fas fa-search me-2 text-muted"></i>
                    ${suggestion}
                </div>
            `).join('');
            
            suggestionsContainer.style.display = 'block';
            
            // Add click handlers
            suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'var(--bg-hover)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
                
                item.addEventListener('click', function() {
                    const searchInput = document.querySelector('.marketplace-search input');
                    if (searchInput) {
                        searchInput.value = this.textContent.trim();
                        document.querySelector('.marketplace-search form').submit();
                    }
                });
            });
        } else {
            hideSearchSuggestions();
        }
    }

    function hideSearchSuggestions() {
        const suggestionsContainer = document.querySelector('.search-suggestions');
        if (suggestionsContainer) {
            suggestionsContainer.style.display = 'none';
        }
    }

    // ===== MOBILE FILTER ENHANCEMENTS =====
    
    // Enhanced filter pills with smooth scrolling
    function initFilterPills() {
        const filterContainer = document.querySelector('.filter-pills');
        if (filterContainer) {
            // Add scroll indicators
            const scrollLeft = document.createElement('div');
            const scrollRight = document.createElement('div');
            
            scrollLeft.className = 'scroll-indicator scroll-left';
            scrollRight.className = 'scroll-indicator scroll-right';
            
            scrollLeft.innerHTML = '<i class="fas fa-chevron-left"></i>';
            scrollRight.innerHTML = '<i class="fas fa-chevron-right"></i>';
            
            scrollLeft.style.cssText = `
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                background: linear-gradient(90deg, var(--bg-card), transparent);
                width: 30px;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                z-index: 2;
            `;
            
            scrollRight.style.cssText = `
                position: absolute;
                right: 0;
                top: 50%;
                transform: translateY(-50%);
                background: linear-gradient(270deg, var(--bg-card), transparent);
                width: 30px;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                z-index: 2;
            `;
            
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            filterContainer.parentNode.insertBefore(wrapper, filterContainer);
            wrapper.appendChild(filterContainer);
            wrapper.appendChild(scrollLeft);
            wrapper.appendChild(scrollRight);
            
            // Update scroll indicators
            function updateScrollIndicators() {
                const canScrollLeft = filterContainer.scrollLeft > 0;
                const canScrollRight = filterContainer.scrollLeft < 
                    (filterContainer.scrollWidth - filterContainer.clientWidth);
                
                scrollLeft.style.opacity = canScrollLeft ? '1' : '0';
                scrollRight.style.opacity = canScrollRight ? '1' : '0';
            }
            
            filterContainer.addEventListener('scroll', updateScrollIndicators);
            updateScrollIndicators();
        }
    }

    // Enhanced mobile filter modal
    function initMobileFilterModal() {
        const filterModal = document.querySelector('.mobile-filter-modal');
        if (filterModal) {
            // Add smooth open/close animations
            filterModal.addEventListener('show.bs.modal', function() {
                this.style.animation = 'slideUp 0.3s ease-out';
            });
            
            filterModal.addEventListener('hide.bs.modal', function() {
                this.style.animation = 'slideDown 0.3s ease-in';
            });
            
            // Add filter counters
            const filterInputs = filterModal.querySelectorAll('input[type="checkbox"], input[type="radio"]');
            filterInputs.forEach(input => {
                input.addEventListener('change', updateFilterCounter);
            });
            
            function updateFilterCounter() {
                const checkedInputs = filterModal.querySelectorAll('input:checked');
                const counter = document.querySelector('.filter-counter');
                if (counter) {
                    counter.textContent = checkedInputs.length;
                    counter.style.display = checkedInputs.length > 0 ? 'inline-block' : 'none';
                }
            }
        }
    }

    // ===== MOBILE VIEW TOGGLE ENHANCEMENTS =====
    
    // Enhanced view switching (grid/list)
    function initViewToggle() {
        const gridBtn = document.querySelector('.view-toggle .btn[data-view="grid"]');
        const listBtn = document.querySelector('.view-toggle .btn[data-view="list"]');
        const productContainer = document.querySelector('.products-container');
        
        if (gridBtn && listBtn && productContainer) {
            [gridBtn, listBtn].forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.dataset.view;
                    
                    // Update active state
                    document.querySelectorAll('.view-toggle .btn').forEach(b => 
                        b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update container class with animation
                    productContainer.style.opacity = '0.5';
                    productContainer.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        productContainer.className = productContainer.className
                            .replace(/view-\w+/, '') + ` view-${view}`;
                        
                        productContainer.style.opacity = '1';
                        productContainer.style.transform = 'scale(1)';
                    }, 150);
                    
                    // Save preference
                    localStorage.setItem('marketplace_view', view);
                    
                    // Haptic feedback
                    if (navigator.vibrate) {
                        navigator.vibrate([10]);
                    }
                });
            });
            
            // Load saved preference
            const savedView = localStorage.getItem('marketplace_view') || 'grid';
            const savedBtn = document.querySelector(`.view-toggle .btn[data-view="${savedView}"]`);
            if (savedBtn) {
                savedBtn.click();
            }
        }
    }

    // ===== MOBILE PRODUCT DETAIL ENHANCEMENTS =====
    
    // Enhanced product image gallery
    function initProductGallery() {
        const mainImage = document.querySelector('.product-main-image');
        const thumbnails = document.querySelectorAll('.thumbnail');
        
        if (mainImage && thumbnails.length > 0) {
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    // Update active thumbnail
                    thumbnails.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update main image with fade effect
                    mainImage.style.opacity = '0';
                    setTimeout(() => {
                        mainImage.src = this.src.replace('_thumb', '');
                        mainImage.style.opacity = '1';
                    }, 150);
                    
                    // Haptic feedback
                    if (navigator.vibrate) {
                        navigator.vibrate([10]);
                    }
                });
            });
            
            // Add swipe gesture for gallery
            let startX = 0;
            let currentIndex = 0;
            
            mainImage.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            }, { passive: true });
            
            mainImage.addEventListener('touchend', function(e) {
                const endX = e.changedTouches[0].clientX;
                const diff = startX - endX;
                
                if (Math.abs(diff) > 50) {
                    if (diff > 0 && currentIndex < thumbnails.length - 1) {
                        // Swipe left - next image
                        currentIndex++;
                    } else if (diff < 0 && currentIndex > 0) {
                        // Swipe right - previous image
                        currentIndex--;
                    }
                    
                    thumbnails[currentIndex].click();
                }
            }, { passive: true });
        }
    }

    // ===== MOBILE PERFORMANCE OPTIMIZATIONS =====
    
    // Throttle scroll events for better performance
    function throttleScroll(func, delay) {
        let timeoutId;
        let lastExecTime = 0;
        return function (...args) {
            const currentTime = Date.now();
            
            if (currentTime - lastExecTime > delay) {
                func.apply(this, args);
                lastExecTime = currentTime;
            } else {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                    lastExecTime = Date.now();
                }, delay - (currentTime - lastExecTime));
            }
        };
    }

    // Infinite scroll for product listing
    function initInfiniteScroll() {
        if (!document.querySelector('.products-container')) return;
        
        let loading = false;
        let page = 1;
        
        const loadMore = throttleScroll(() => {
            if (loading) return;
            
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            
            if (scrollTop + windowHeight >= documentHeight - 1000) {
                loading = true;
                page++;
                
                // Show loading indicator
                showLoadingIndicator();
                
                // Load more products (implement your API call here)
                loadMoreProducts(page).then(products => {
                    appendProducts(products);
                    loading = false;
                    hideLoadingIndicator();
                }).catch(() => {
                    loading = false;
                    hideLoadingIndicator();
                });
            }
        }, 100);
        
        window.addEventListener('scroll', loadMore);
    }

    // ===== UTILITY FUNCTIONS =====
    
    // Enhanced mobile toast notifications
    function showMobileToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `mobile-toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            left: 1rem;
            right: 1rem;
            background: var(--${type});
            color: white;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            transform: translateY(-100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            align-items: center;
            font-weight: 500;
            backdrop-filter: blur(10px);
        `;
        
        // Add icon based on type
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        toast.innerHTML = `
            <i class="${icons[type] || icons.info} me-2"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
        }, 100);
        
        // Animate out
        setTimeout(() => {
            toast.style.transform = 'translateY(-100px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, duration);
        
        // Add tap to dismiss
        toast.addEventListener('click', () => {
            toast.style.transform = 'translateY(-100px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        });
    }

    // Loading indicator functions
    function showLoadingIndicator() {
        let loader = document.querySelector('.mobile-loading-more');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'mobile-loading-more';
            loader.innerHTML = `
                <div class="loading-spinner"></div>
                <p>Loading more products...</p>
            `;
            loader.style.cssText = `
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 2rem;
                color: var(--text-muted);
            `;
            
            const container = document.querySelector('.products-container');
            if (container) {
                container.parentNode.appendChild(loader);
            }
        }
        loader.style.display = 'flex';
    }

    function hideLoadingIndicator() {
        const loader = document.querySelector('.mobile-loading-more');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    // Wishlist API function (implement your backend integration)
    async function updateWishlist(productId, add) {
        try {
            const response = await fetch('/api/wishlist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    product_id: productId,
                    action: add ? 'add' : 'remove'
                })
            });
            
            if (!response.ok) {
                throw new Error('Failed to update wishlist');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Wishlist error:', error);
            showMobileToast('Failed to update wishlist', 'error');
        }
    }

    // Load more products API function (implement your backend integration)
    async function loadMoreProducts(page) {
        try {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to load products');
            }
            
            return await response.json();
        } catch (error) {
            console.error('Load more error:', error);
            showMobileToast('Failed to load more products', 'error');
            throw error;
        }
    }

    // Append products to container
    function appendProducts(products) {
        const container = document.querySelector('.products-container .row, .product-grid');
        if (container && products.length > 0) {
            products.forEach((product, index) => {
                const productElement = createProductElement(product);
                productElement.style.animationDelay = `${index * 0.1}s`;
                container.appendChild(productElement);
            });
            
            // Reinitialize enhancements for new products
            initProductCardEnhancements();
            initWishlistEnhancements();
            initLazyLoading();
        }
    }

    // Create product element (implement based on your product structure)
    function createProductElement(product) {
        const div = document.createElement('div');
        div.className = 'col-md-3 col-sm-6 mb-4';
        div.innerHTML = `
            <div class="product-card card h-100">
                <div class="position-relative">
                    <img src="${product.image}" class="product-img card-img-top" alt="${product.title}">
                    <button class="wishlist-btn" data-product-id="${product.id}">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <div class="card-body">
                    <h6 class="card-title">${product.title}</h6>
                    <p class="card-text">₦${product.price}</p>
                </div>
                <div class="card-footer">
                    <small class="text-muted">${product.seller}</small>
                    <small class="text-muted">
                        <i class="fas fa-eye me-1"></i> ${product.views}
                    </small>
                </div>
            </div>
        `;
        return div;
    }

    // ===== INITIALIZE ALL ENHANCEMENTS =====
    
    // Initialize all marketplace mobile enhancements
    function initAllEnhancements() {
        try {
            initLazyLoading();
            initProductCardEnhancements();
            initWishlistEnhancements();
            initMobileSearch();
            initFilterPills();
            initMobileFilterModal();
            initViewToggle();
            initProductGallery();
            initInfiniteScroll();
            
            console.log('✅ Marketplace mobile enhancements initialized successfully!');
        } catch (error) {
            console.error('❌ Error initializing marketplace enhancements:', error);
        }
    }

    // ===== MARKETPLACE-SPECIFIC EVENT LISTENERS =====
    
    // Handle orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            // Recalculate grid layout
            const productGrid = document.querySelector('.product-grid');
            if (productGrid) {
                productGrid.style.gridTemplateColumns = '';
                // Force reflow
                productGrid.offsetHeight;
            }
            
            // Update filter pills scroll indicators
            const filterContainer = document.querySelector('.filter-pills');
            if (filterContainer) {
                filterContainer.dispatchEvent(new Event('scroll'));
            }
        }, 500);
    });

    // Handle resize events for responsive adjustments
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            // Update mobile-specific layouts
            if (window.innerWidth <= 768) {
                // Ensure mobile styles are applied
                document.body.classList.add('mobile-mode');
            } else {
                document.body.classList.remove('mobile-mode');
            }
        }, 250);
    });

    // Handle network status for offline functionality
    window.addEventListener('online', function() {
        showMobileToast('Connection restored', 'success', 2000);
    });

    window.addEventListener('offline', function() {
        showMobileToast('You are offline', 'warning', 3000);
    });

    // ===== MARKETPLACE ACCESSIBILITY ENHANCEMENTS =====
    
    // Enhanced keyboard navigation for mobile
    document.addEventListener('keydown', function(e) {
        // Handle escape key for modals and overlays
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) {
                const closeBtn = activeModal.querySelector('[data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
            
            hideSearchSuggestions();
        }
        
        // Handle enter key for product cards
        if (e.key === 'Enter') {
            const focusedCard = document.activeElement.closest('.product-card');
            if (focusedCard) {
                const link = focusedCard.querySelector('a');
                if (link) link.click();
            }
        }
    });

    // Add focus management for better accessibility
    function initAccessibilityEnhancements() {
        // Add ARIA labels and roles
        const productCards = document.querySelectorAll('.product-card');
        productCards.forEach((card, index) => {
            card.setAttribute('role', 'article');
            card.setAttribute('aria-label', `Product ${index + 1}`);
            card.setAttribute('tabindex', '0');
        });

        // Add ARIA labels to wishlist buttons
        const wishlistBtns = document.querySelectorAll('.wishlist-btn');
        wishlistBtns.forEach(btn => {
            btn.setAttribute('aria-label', 'Add to wishlist');
            btn.setAttribute('role', 'button');
        });

        // Add ARIA labels to filter pills
        const filterPills = document.querySelectorAll('.filter-pill');
        filterPills.forEach(pill => {
            pill.setAttribute('role', 'button');
            pill.setAttribute('aria-pressed', pill.classList.contains('active'));
        });
    }

    // ===== MARKETPLACE PERFORMANCE MONITORING =====
    
    // Monitor Core Web Vitals for marketplace pages
    function initPerformanceMonitoring() {
        // Measure Largest Contentful Paint (LCP)
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                console.log('📊 LCP:', lastEntry.startTime);
            });
            observer.observe({ entryTypes: ['largest-contentful-paint'] });
        }

        // Measure layout shifts
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                let cumulativeLayoutShift = 0;
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput) {
                        cumulativeLayoutShift += entry.value;
                    }
                }
                console.log('📊 CLS:', cumulativeLayoutShift);
            });
            observer.observe({ entryTypes: ['layout-shift'] });
        }
    }

    // ===== MARKETPLACE ANALYTICS EVENTS =====
    
    // Track marketplace interactions for analytics
    function initAnalyticsTracking() {
        // Track product views
        const productCards = document.querySelectorAll('.product-card');
        const viewObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const productId = entry.target.dataset.productId;
                    if (productId) {
                        // Track product impression
                        trackEvent('product_impression', { product_id: productId });
                    }
                }
            });
        }, { threshold: 0.5 });

        productCards.forEach(card => {
            viewObserver.observe(card);
        });

        // Track search events
        const searchForm = document.querySelector('.marketplace-search form');
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                const query = this.querySelector('input').value;
                trackEvent('marketplace_search', { query: query });
            });
        }

        // Track filter usage
        const filterInputs = document.querySelectorAll('.mobile-filter-modal input');
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                trackEvent('filter_used', { 
                    filter_type: this.name,
                    filter_value: this.value 
                });
            });
        });
    }

    // Analytics tracking function (implement with your analytics service)
    function trackEvent(eventName, parameters = {}) {
        // Example implementation for Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, parameters);
        }
        
        // Example implementation for custom analytics
        console.log('📈 Analytics Event:', eventName, parameters);
    }

    // ===== MARKETPLACE DEBUGGING TOOLS =====
    
    // Debug tools for development
    window.marketplaceDebug = {
        showProductBounds: function() {
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.outline = '2px solid red';
            });
        },
        
        hideProductBounds: function() {
            document.querySelectorAll('.product-card').forEach(card => {
                card.style.outline = '';
            });
        },
        
        simulateSlowNetwork: function() {
            // Add artificial delay to image loading
            document.querySelectorAll('img').forEach(img => {
                const originalSrc = img.src;
                img.src = '';
                setTimeout(() => {
                    img.src = originalSrc;
                }, Math.random() * 2000 + 1000);
            });
        },
        
        testToast: function(type = 'info') {
            showMobileToast(`Test ${type} message`, type);
        },
        
        getPerformanceMetrics: function() {
            return {
                loadTime: performance.timing.loadEventEnd - performance.timing.navigationStart,
                domContentLoaded: performance.timing.domContentLoadedEventEnd - performance.timing.navigationStart,
                firstPaint: performance.getEntriesByType('paint').find(entry => entry.name === 'first-paint')?.startTime,
                firstContentfulPaint: performance.getEntriesByType('paint').find(entry => entry.name === 'first-contentful-paint')?.startTime
            };
        }
    };

    // ===== INITIALIZE EVERYTHING =====
    
    // Wait for images to load before initializing
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllEnhancements);
    } else {
        initAllEnhancements();
    }

    // Initialize additional features
    initAccessibilityEnhancements();
    initPerformanceMonitoring();
    initAnalyticsTracking();

    // Make utility functions globally available
    window.showMobileToast = showMobileToast;
    window.updateWishlist = updateWishlist;
    window.trackEvent = trackEvent;

    // Set initial mobile mode class
    if (window.innerWidth <= 768) {
        document.body.classList.add('mobile-mode');
    }

    console.log('🎉 Marketplace mobile experience fully initialized!');
    
    // Show welcome message on first visit
    if (!localStorage.getItem('marketplace_visited')) {
        setTimeout(() => {
            showMobileToast('Welcome to our mobile marketplace! 🛒', 'success', 4000);
            localStorage.setItem('marketplace_visited', 'true');
        }, 1000);
    }
});