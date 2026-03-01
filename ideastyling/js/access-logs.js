 /**
 * Gate Wey Access Management System
 * Access Logs JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
  // Initialize tooltips
  initTooltips();
  
  // Initialize date range validation
  initDateRangeValidation();
  
  // Initialize real-time search filtering
  initSearchFiltering();
  
  // Initialize dynamic dropdowns
  initDynamicDropdowns();
});

/**
* Initialize Bootstrap tooltips
*/
function initTooltips() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl, {
          boundary: document.body
      });
  });
}

/**
* Initialize date range validation
* Ensures that date_to is not before date_from
*/
function initDateRangeValidation() {
  const dateFromInput = document.querySelector('input[name="date_from"]');
  const dateToInput = document.querySelector('input[name="date_to"]');
  
  if (!dateFromInput || !dateToInput) return;
  
  // Set max date to today
  const today = new Date().toISOString().split('T')[0];
  dateFromInput.setAttribute('max', today);
  dateToInput.setAttribute('max', today);
  
  // Update min date of date_to when date_from changes
  dateFromInput.addEventListener('change', function() {
      if (this.value) {
          dateToInput.setAttribute('min', this.value);
          
          // If date_to is before date_from, reset it
          if (dateToInput.value && dateToInput.value < this.value) {
              dateToInput.value = this.value;
          }
      } else {
          dateToInput.removeAttribute('min');
      }
  });
  
  // Update max date of date_from when date_to changes
  dateToInput.addEventListener('change', function() {
      if (this.value) {
          dateFromInput.setAttribute('max', this.value);
          
          // If date_from is after date_to, reset it
          if (dateFromInput.value && dateFromInput.value > this.value) {
              dateFromInput.value = this.value;
          }
      } else {
          dateFromInput.setAttribute('max', today);
      }
  });
  
  // Trigger change event to set initial constraints
  if (dateFromInput.value) {
      dateFromInput.dispatchEvent(new Event('change'));
  }
  
  if (dateToInput.value) {
      dateToInput.dispatchEvent(new Event('change'));
  }
}

/**
* Initialize real-time search filtering behavior
* Adds debounced search functionality to the search input
*/
function initSearchFiltering() {
  const searchInput = document.querySelector('input[name="search"]');
  if (!searchInput) return;
  
  // Add clear button to search input
  const searchWrapper = searchInput.parentElement;
  const clearButton = document.createElement('button');
  clearButton.className = 'btn btn-outline-secondary';
  clearButton.type = 'button';
  clearButton.innerHTML = '<i class="fas fa-times"></i>';
  clearButton.style.display = searchInput.value ? 'block' : 'none';
  searchWrapper.appendChild(clearButton);

  // Clear search when clicking the clear button
  clearButton.addEventListener('click', function() {
      searchInput.value = '';
      clearButton.style.display = 'none';
      // Submit the form to refresh results
      searchInput.closest('form').submit();
  });

  // Show/hide clear button based on search input
  searchInput.addEventListener('input', function() {
      clearButton.style.display = this.value ? 'block' : 'none';
  });
}

/**
* Initialize dynamic dropdowns
* Handles filtering and dependencies between dropdown selections
*/
function initDynamicDropdowns() {
  const clanDropdown = document.querySelector('select[name="clan_id"]');
  const guardDropdown = document.querySelector('select[name="guard_id"]');
  
  if (!clanDropdown || !guardDropdown) return;
  
  // Store original guard options
  const originalGuardOptions = Array.from(guardDropdown.options);
  
  // Filter guards by clan when clan selection changes
  clanDropdown.addEventListener('change', function() {
      const selectedClanId = this.value;
      
      // Reset guard dropdown
      guardDropdown.innerHTML = '';
      
      // Add "All Guards" option
      const allOption = document.createElement('option');
      allOption.value = '';
      allOption.textContent = 'All Guards';
      guardDropdown.appendChild(allOption);
      
      // If a clan is selected, only show guards from that clan
      if (selectedClanId) {
          originalGuardOptions.forEach(function(option) {
              const guardData = option.dataset.clanId;
              if (!option.value || guardData === selectedClanId) {
                  guardDropdown.appendChild(option.cloneNode(true));
              }
          });
      } else {
          // If no clan selected, show all guards
          originalGuardOptions.forEach(function(option) {
              guardDropdown.appendChild(option.cloneNode(true));
          });
      }
  });
}

/**
* Format a date string to a readable format
* 
* @param {string} dateString - The date string to format
* @param {string} format - The format to use
* @return {string} Formatted date string
*/
function formatDate(dateString, format = 'medium') {
  if (!dateString) return '';
  
  const date = new Date(dateString);
  
  switch (format) {
      case 'short':
          return date.toLocaleDateString();
      case 'medium':
          return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      case 'long':
          return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
      default:
          return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
}

/**
* Helper function to create date filters for common ranges
* Can be used to quickly set date range inputs
* 
* @param {string} range - The date range to use (today, yesterday, thisWeek, thisMonth, lastMonth)
*/
function setDateRange(range) {
  const dateFromInput = document.querySelector('input[name="date_from"]');
  const dateToInput = document.querySelector('input[name="date_to"]');
  
  if (!dateFromInput || !dateToInput) return;
  
  const today = new Date();
  let fromDate = new Date();
  
  switch (range) {
      case 'today':
          // Do nothing, fromDate is already today
          break;
      case 'yesterday':
          fromDate.setDate(fromDate.getDate() - 1);
          break;
      case 'thisWeek':
          // Set to beginning of current week (Sunday)
          const dayOfWeek = fromDate.getDay();
          fromDate.setDate(fromDate.getDate() - dayOfWeek);
          break;
      case 'thisMonth':
          // Set to beginning of current month
          fromDate.setDate(1);
          break;
      case 'lastMonth':
          // Set to beginning of previous month
          fromDate.setMonth(fromDate.getMonth() - 1);
          fromDate.setDate(1);
          const lastDay = new Date(today.getFullYear(), today.getMonth(), 0).getDate();
          today.setDate(lastDay);
          today.setMonth(today.getMonth() - 1);
          break;
      default:
          return;
  }
  
  // Format dates for input fields (YYYY-MM-DD)
  const formatInputDate = (date) => {
      const year = date.getFullYear();
      const month = (date.getMonth() + 1).toString().padStart(2, '0');
      const day = date.getDate().toString().padStart(2, '0');
      return `${year}-${month}-${day}`;
  };
  
  dateFromInput.value = formatInputDate(fromDate);
  dateToInput.value = formatInputDate(today);
  
  // Trigger change events
  dateFromInput.dispatchEvent(new Event('change'));
  dateToInput.dispatchEvent(new Event('change'));
}
