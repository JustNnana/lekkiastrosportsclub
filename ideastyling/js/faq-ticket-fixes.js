/**
 * FAQ and Create-Ticket Pages - JavaScript Dark Mode Fixes
 * Add this to your dark-mode-fixes.js file or create a separate file
 */

(function() {
    'use strict';
    
    // Specific fixes for FAQ and Create-Ticket pages
    function fixFaqAndTicketPages() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        if (!isDark) return;
        
        console.log('🔧 Applying FAQ and Create-Ticket specific fixes...');
        
        // Fix FAQ page elements
        if (window.location.pathname.includes('faq.php')) {
            fixFaqPageElements();
        }
        
        // Fix Create-Ticket page elements
        if (window.location.pathname.includes('create-ticket.php')) {
            fixCreateTicketPageElements();
        }
        
        // Fix general ticket-related elements
        fixTicketRelatedElements();
        
        console.log('✅ FAQ and Create-Ticket fixes applied');
    }
    
    function fixFaqPageElements() {
        // Fix FAQ search header
        const faqSearch = document.querySelector('.faq-search');
        if (faqSearch) {
            faqSearch.style.setProperty('background', 'var(--bg-card)', 'important');
            faqSearch.style.setProperty('color', 'var(--text-primary)', 'important');
            faqSearch.style.setProperty('border', '1px solid var(--border-color)', 'important');
        }
        
        // Fix FAQ items
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            item.style.setProperty('background-color', 'var(--bg-card)', 'important');
            item.style.setProperty('border-color', 'var(--border-color)', 'important');
            item.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix FAQ questions
        const faqQuestions = document.querySelectorAll('.faq-question');
        faqQuestions.forEach(question => {
            question.style.setProperty('background-color', 'var(--bg-card)', 'important');
            question.style.setProperty('color', 'var(--text-primary)', 'important');
            
            // Fix hover state
            question.addEventListener('mouseenter', function() {
                if (!this.getAttribute('aria-expanded') || this.getAttribute('aria-expanded') === 'false') {
                    this.style.setProperty('background-color', 'var(--bg-hover)', 'important');
                }
            });
            
            question.addEventListener('mouseleave', function() {
                if (!this.getAttribute('aria-expanded') || this.getAttribute('aria-expanded') === 'false') {
                    this.style.setProperty('background-color', 'var(--bg-card)', 'important');
                }
            });
        });
        
        // Fix FAQ answers
        const faqAnswers = document.querySelectorAll('.faq-answer');
        faqAnswers.forEach(answer => {
            answer.style.setProperty('color', 'var(--text-secondary)', 'important');
        });
        
        // Fix FAQ feedback sections
        const faqFeedback = document.querySelectorAll('.faq-feedback');
        faqFeedback.forEach(feedback => {
            feedback.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
            feedback.style.setProperty('border-top-color', 'var(--border-color)', 'important');
        });
        
        // Fix category filters
        const categoryFilters = document.querySelectorAll('.category-filter');
        categoryFilters.forEach(filter => {
            filter.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
            filter.style.setProperty('color', 'var(--text-primary)', 'important');
        });
    }
    
    function fixCreateTicketPageElements() {
        // Fix form header
        const formHeader = document.querySelector('.form-header');
        if (formHeader) {
            // Keep the gradient but make it darker
            formHeader.style.setProperty('background', 'linear-gradient(135deg, var(--primary), var(--secondary))', 'important');
            formHeader.style.setProperty('color', 'white', 'important');
        }
        
        // Fix ticket form
        const ticketForm = document.querySelector('.ticket-form');
        if (ticketForm) {
            ticketForm.style.setProperty('background-color', 'var(--bg-card)', 'important');
            ticketForm.style.setProperty('color', 'var(--text-primary)', 'important');
            ticketForm.style.setProperty('border', '1px solid var(--border-color)', 'important');
        }
        
        // Fix priority info
        const priorityInfo = document.querySelector('.priority-info');
        if (priorityInfo) {
            priorityInfo.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
            priorityInfo.style.setProperty('color', 'var(--text-primary)', 'important');
            priorityInfo.style.setProperty('border-left-color', 'var(--primary)', 'important');
        }
    }
    
    function fixTicketRelatedElements() {
        // Fix ticket headers
        const ticketHeaders = document.querySelectorAll('.ticket-header, .tickets-header, .response-header');
        ticketHeaders.forEach(header => {
            header.style.setProperty('background-color', 'var(--bg-card)', 'important');
            header.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix ticket cards
        const ticketCards = document.querySelectorAll('.ticket-card');
        ticketCards.forEach(card => {
            card.style.setProperty('background-color', 'var(--bg-card)', 'important');
            card.style.setProperty('border-color', 'var(--border-color)', 'important');
            card.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix ticket info panels
        const ticketInfos = document.querySelectorAll('.ticket-info, .ticket-context');
        ticketInfos.forEach(info => {
            info.style.setProperty('background-color', 'var(--bg-card)', 'important');
            info.style.setProperty('border', '1px solid var(--border-color)', 'important');
            info.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix response forms
        const responseForms = document.querySelectorAll('.response-form');
        responseForms.forEach(form => {
            form.style.setProperty('background-color', 'var(--bg-card)', 'important');
            form.style.setProperty('border', '1px solid var(--border-color)', 'important');
            form.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix quick actions
        const quickActions = document.querySelectorAll('.quick-actions');
        quickActions.forEach(action => {
            action.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
            action.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix message items
        const messageItems = document.querySelectorAll('.message-item');
        messageItems.forEach(item => {
            item.style.setProperty('background-color', 'var(--bg-card)', 'important');
            item.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix avatars
        const avatars = document.querySelectorAll('.user-avatar, .support-avatar, .customer-avatar');
        avatars.forEach(avatar => {
            if (avatar.classList.contains('support-avatar')) {
                avatar.style.setProperty('background-color', 'var(--success)', 'important');
            } else if (avatar.classList.contains('customer-avatar')) {
                avatar.style.setProperty('background-color', 'var(--info)', 'important');
            } else {
                avatar.style.setProperty('background-color', 'var(--primary)', 'important');
            }
            avatar.style.setProperty('color', 'white', 'important');
        });
        
        // Fix ticket numbers
        const ticketNumbers = document.querySelectorAll('.ticket-number');
        ticketNumbers.forEach(number => {
            number.style.setProperty('color', 'var(--primary)', 'important');
        });
    }
    
    // Fix hardcoded inline styles
    function fixHardcodedStyles() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (!isDark) return;
        
        // Fix elements with hardcoded white backgrounds
        const whiteBackgrounds = document.querySelectorAll('[style*="background: white"], [style*="background-color: white"]');
        whiteBackgrounds.forEach(element => {
            element.style.setProperty('background-color', 'var(--bg-card)', 'important');
        });
        
        // Fix elements with hardcoded light gray backgrounds
        const lightGrayBackgrounds = document.querySelectorAll('[style*="background: #f8f9fa"], [style*="background-color: #f8f9fa"]');
        lightGrayBackgrounds.forEach(element => {
            element.style.setProperty('background-color', 'var(--bg-secondary)', 'important');
        });
        
        // Fix elements with hardcoded dark text
        const darkTexts = document.querySelectorAll('[style*="color: #2c3e50"], [style*="color: #495057"], [style*="color: black"]');
        darkTexts.forEach(element => {
            element.style.setProperty('color', 'var(--text-primary)', 'important');
        });
        
        // Fix elements with hardcoded muted text
        const mutedTexts = document.querySelectorAll('[style*="color: #6c757d"]');
        mutedTexts.forEach(element => {
            element.style.setProperty('color', 'var(--text-secondary)', 'important');
        });
    }
    
    // Enhanced observer for these specific pages
    function observePageSpecificElements() {
        const observer = new MutationObserver((mutations) => {
            let needsFix = false;
            
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check for FAQ or ticket related elements
                            if (node.matches && (
                                node.matches('.faq-item, .faq-question, .ticket-card, .ticket-form, .message-item') ||
                                node.querySelector('.faq-item, .faq-question, .ticket-card, .ticket-form, .message-item')
                            )) {
                                needsFix = true;
                            }
                        }
                    });
                }
            });
            
            if (needsFix && document.documentElement.getAttribute('data-theme') === 'dark') {
                setTimeout(() => {
                    fixFaqAndTicketPages();
                    fixHardcodedStyles();
                }, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        return observer;
    }
    
    // Main initialization function
    function initializeFaqTicketFixes() {
        // Apply initial fixes
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                fixFaqAndTicketPages();
                fixHardcodedStyles();
            });
        } else {
            fixFaqAndTicketPages();
            fixHardcodedStyles();
        }
        
        // Listen for theme changes
        document.addEventListener('gateweythemechange', (e) => {
            if (e.detail.theme === 'dark') {
                setTimeout(() => {
                    fixFaqAndTicketPages();
                    fixHardcodedStyles();
                }, 50);
            }
        });
        
        // Set up observer
        observePageSpecificElements();
        
        console.log('🎯 FAQ and Create-Ticket fixes initialized');
    }
    
    // Debug function
    window.fixFaqTicketPages = function() {
        console.log('🔧 Manually fixing FAQ and Create-Ticket pages...');
        fixFaqAndTicketPages();
        fixHardcodedStyles();
    };
    
    // Initialize
    initializeFaqTicketFixes();
    
})();