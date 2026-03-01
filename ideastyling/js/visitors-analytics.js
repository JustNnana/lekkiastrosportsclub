/**
 * Emergency height fix for charts setting extremely large heights
 * 
 * This script directly tackles the excessive height issue by:
 * 1. Setting explicit inline max-height styles with !important
 * 2. Overriding height attributes on canvas elements
 * 3. Forcing Chart.js to use fixed dimensions
 */

// Execute this code immediately (not waiting for DOMContentLoaded)
(function() {
  // Immediate fix function - run as soon as the script loads
  function emergencyHeightFix() {
      console.log("Applying emergency chart height fix");
      
      // 1. Direct attribute override for all canvas elements
      document.querySelectorAll('canvas').forEach(canvas => {
          // Force override the height attribute that's causing the problem
          canvas.setAttribute('height', '300');
          canvas.height = 300;
          
          // Add !important inline styles
          canvas.style.cssText = `
              max-height: 300px !important; 
              height: 300px !important;
          `;
          
          // Also fix the width to maintain proper aspect ratio
          if (canvas.width > 1000) {
              canvas.setAttribute('width', '100%');
              canvas.style.width = '100%';
          }
          
          // Fix parent container
          if (canvas.parentNode) {
              canvas.parentNode.style.cssText = `
                  height: 300px !important;
                  max-height: 300px !important;
                  overflow: hidden !important;
              `;
          }
          
          // Go up to card-body level
          const cardBody = canvas.closest('.card-body');
          if (cardBody) {
              cardBody.style.cssText = `
                  height: 350px !important;
                  max-height: 350px !important;
                  overflow: hidden !important;
              `;
          }
      });
      
      // 2. Add a style tag with !important rules
      const styleTag = document.createElement('style');
      styleTag.textContent = `
          canvas {
              max-height: 300px !important;
              height: auto !important;
          }
          .card-body canvas {
              max-height: 300px !important;
              height: auto !important;
          }
          .card-body:has(canvas) {
              height: 350px !important;
              max-height: 350px !important;
              overflow: hidden !important;
          }
      `;
      document.head.appendChild(styleTag);
      
      // 3. Force Chart.js to obey our height settings
      if (window.Chart && window.Chart.instances) {
          Object.values(window.Chart.instances).forEach(chart => {
              // Override the render method to force height constraints
              const originalRender = chart.render;
              chart.render = function() {
                  // Force canvas to have correct height before rendering
                  this.canvas.height = 300;
                  this.canvas.style.height = '300px';
                  this.canvas.style.maxHeight = '300px';
                  
                  // Call original render method
                  return originalRender.apply(this, arguments);
              };
              
              // Fix options
              chart.options.maintainAspectRatio = false;
              chart.options.responsive = true;
              chart.options.height = 300;
              chart.height = 300;
              
              // Update the chart to apply changes
              chart.resize();
              chart.update();
          });
      }
  }
  
  // Run the fix immediately
  emergencyHeightFix();
  
  // Also run after a delay to catch charts that initialize later
  setTimeout(emergencyHeightFix, 100);
  setTimeout(emergencyHeightFix, 500);
  setTimeout(emergencyHeightFix, 1000);
  
  // Run when DOM is ready
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', emergencyHeightFix);
  }
  
  // One final attempt after window load
  window.addEventListener('load', emergencyHeightFix);
  
  // Override Chart.js constructor to fix all new charts
  if (window.Chart) {
      const originalConstructor = window.Chart;
      window.Chart = function(context, config) {
          // Force height constraints in the config
          if (config) {
              config.options = config.options || {};
              config.options.maintainAspectRatio = false;
              config.options.responsive = true;
              config.options.height = 300;
              
              // Set canvas height directly
              if (context.canvas) {
                  context.canvas.height = 300;
                  context.canvas.style.height = '300px';
                  context.canvas.style.maxHeight = '300px';
              }
          }
          
          // Call original constructor
          return new originalConstructor(context, config);
      };
      // Copy over prototype and properties
      window.Chart.prototype = originalConstructor.prototype;
      Object.assign(window.Chart, originalConstructor);
  }
})();

/**
 * Gate Wey Access Management System
 * Visitor Analytics JavaScript
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  // Initialize date range pickers
  initializeDatePickers();
  
  // Add export functionality
  initializeExportButtons();
  
  // Add quick date filter functionality
  initializeQuickFilters();
  
  // Add table sorting functionality
  initializeTableSorting();
  
  // Add print functionality
  initializePrintButton();
  
  // Add chart data interaction
  initializeChartInteraction();
});

/**
 * Fix for ALL charts (line, bar, pie, doughnut) to prevent elongation
 * Add this to your visitors-analytics.js file
 */

// Add these functions to your existing code
document.addEventListener('DOMContentLoaded', function() {
  // Apply fixes after a short delay to ensure charts are initialized
  setTimeout(fixAllCharts, 100);
});

/**
* Fix all charts on the page
*/
function fixAllCharts() {
  // Approach 1: Style all chart containers directly
  const chartContainers = document.querySelectorAll('.card-body');
  chartContainers.forEach(container => {
      if (container.querySelector('canvas')) {
          container.style.height = '350px';
          container.style.maxHeight = '350px';
          container.style.overflow = 'hidden';
      }
  });

  // Approach 2: Add inline styles to the canvas elements
  const canvasElements = document.querySelectorAll('canvas');
  canvasElements.forEach(canvas => {
      // Set a maximum height for the canvas itself
      canvas.style.maxHeight = '300px';
      
      // Also set height on parent container (the chart-container div)
      if (canvas.parentNode) {
          canvas.parentNode.style.height = '300px';
          canvas.parentNode.style.maxHeight = '300px';
      }
  });

  // Approach 3: Fix via Chart.js configuration
  if (window.Chart && window.Chart.instances) {
      Object.values(window.Chart.instances).forEach(chart => {
          // Set maintainAspectRatio to false and responsive to true
          chart.options.maintainAspectRatio = false;
          chart.options.responsive = true;
          
          // Set a height for the chart
          chart.height = 300;
          
          // Update the chart to apply changes
          chart.resize();
          chart.update();
      });
  }

  // Approach 4: Add a style tag with CSS rules
  const styleTag = document.createElement('style');
  styleTag.textContent = `
      .card-body:has(canvas) {
          height: 350px !important;
          max-height: 350px !important;
          overflow: hidden !important;
      }
      canvas {
          max-height: 300px !important;
      }
  `;
  document.head.appendChild(styleTag);
}

/**
* This function can be used to fix charts individually if needed
* You can call this for specific charts that still have issues
*/
function fixSpecificChart(chartId) {
  const canvas = document.getElementById(chartId);
  if (!canvas) return;
  
  // Fix container
  const container = canvas.closest('.card-body');
  if (container) {
      container.style.height = '350px';
      container.style.maxHeight = '350px';
      container.style.overflow = 'hidden';
  }
  
  // Fix canvas
  canvas.style.maxHeight = '300px';
  
  // Fix Chart.js instance if possible
  if (window.Chart && window.Chart.instances) {
      for (let id in window.Chart.instances) {
          const instance = window.Chart.instances[id];
          if (instance.canvas.id === chartId) {
              instance.options.maintainAspectRatio = false;
              instance.options.responsive = true;
              instance.height = 300;
              instance.resize();
              instance.update();
              break;
          }
      }
  }
}

/**
* Use this when creating charts to ensure they have proper height settings
* Example: const myChart = createFixedHeightChart('chartId', 'line', data, options);
*/
function createFixedHeightChart(canvasId, type, data, customOptions = {}) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return null;
  
  // Fix container
  const container = canvas.closest('.card-body');
  if (container) {
      container.style.height = '350px';
      container.style.maxHeight = '350px';
      container.style.overflow = 'hidden';
  }
  
  // Set default options with fixed height
  const defaultOptions = {
      responsive: true,
      maintainAspectRatio: false,
      height: 300,
      onResize: function(chart, size) {
          // Ensure chart doesn't grow too large
          if (size.height > 300) {
              size.height = 300;
          }
      }
  };
  
  // Merge custom options with defaults
  const options = { ...defaultOptions, ...customOptions };
  
  // Create and return the chart
  return new Chart(canvas.getContext('2d'), {
      type: type,
      data: data,
      options: options
  });
}

/**
* Update or replace the chart initialization code in visitors.php
* Replace all chart initializations with calls to this function
*/
function initializeChartsWithFixedHeight() {
  // Visitor Trends Chart
  if (document.getElementById('visitorTrendsChart')) {
      const trendsCanvas = document.getElementById('visitorTrendsChart');
      const trendLabels = JSON.parse(trendsCanvas.getAttribute('data-labels'));
      const trendValues = JSON.parse(trendsCanvas.getAttribute('data-values'));
      
      createFixedHeightChart('visitorTrendsChart', 'line', {
          labels: trendLabels,
          datasets: [{
              label: 'Visitors',
              data: trendValues,
              backgroundColor: 'rgba(55, 66, 250, 0.2)',
              borderColor: 'rgba(55, 66, 250, 1)',
              borderWidth: 2,
              tension: 0.4,
              fill: true
          }]
      });
  }
  
  // Purpose Distribution Chart
  if (document.getElementById('visitorPurposeChart')) {
      const purposeCanvas = document.getElementById('visitorPurposeChart');
      const purposeLabels = JSON.parse(purposeCanvas.getAttribute('data-labels'));
      const purposeValues = JSON.parse(purposeCanvas.getAttribute('data-values'));
      
      createFixedHeightChart('visitorPurposeChart', 'doughnut', {
          labels: purposeLabels,
          datasets: [{
              data: purposeValues,
              backgroundColor: [
                  'rgba(255, 99, 132, 0.7)',
                  'rgba(54, 162, 235, 0.7)',
                  'rgba(255, 206, 86, 0.7)',
                  'rgba(75, 192, 192, 0.7)',
                  'rgba(153, 102, 255, 0.7)',
                  'rgba(255, 159, 64, 0.7)'
              ],
              borderColor: [
                  'rgba(255, 99, 132, 1)',
                  'rgba(54, 162, 235, 1)',
                  'rgba(255, 206, 86, 1)',
                  'rgba(75, 192, 192, 1)',
                  'rgba(153, 102, 255, 1)',
                  'rgba(255, 159, 64, 1)'
              ],
              borderWidth: 1
          }]
      });
  }
  
  // Busiest Days Chart
  if (document.getElementById('busiestDaysChart')) {
      const daysCanvas = document.getElementById('busiestDaysChart');
      const dayLabels = JSON.parse(daysCanvas.getAttribute('data-labels'));
      const dayValues = JSON.parse(daysCanvas.getAttribute('data-values'));
      
      createFixedHeightChart('busiestDaysChart', 'bar', {
          labels: dayLabels,
          datasets: [{
              label: 'Visitors',
              data: dayValues,
              backgroundColor: 'rgba(54, 162, 235, 0.7)',
              borderColor: 'rgba(54, 162, 235, 1)',
              borderWidth: 1
          }]
      });
  }
  
  // Time Distribution Chart
  if (document.getElementById('timeDistributionChart')) {
      const timeCanvas = document.getElementById('timeDistributionChart');
      const timeLabels = JSON.parse(timeCanvas.getAttribute('data-labels'));
      const timeValues = JSON.parse(timeCanvas.getAttribute('data-values'));
      
      createFixedHeightChart('timeDistributionChart', 'pie', {
          labels: timeLabels,
          datasets: [{
              data: timeValues,
              backgroundColor: [
                  'rgba(255, 99, 132, 0.7)',
                  'rgba(54, 162, 235, 0.7)',
                  'rgba(255, 206, 86, 0.7)',
                  'rgba(153, 102, 255, 0.7)'
              ],
              borderColor: [
                  'rgba(255, 99, 132, 1)',
                  'rgba(54, 162, 235, 1)',
                  'rgba(255, 206, 86, 1)',
                  'rgba(153, 102, 255, 1)'
              ],
              borderWidth: 1
          }]
      });
  }
  
  // Status Distribution Chart
  if (document.getElementById('statusDistributionChart')) {
      const statusCanvas = document.getElementById('statusDistributionChart');
      const statusLabels = JSON.parse(statusCanvas.getAttribute('data-labels'));
      const statusValues = JSON.parse(statusCanvas.getAttribute('data-values'));
      
      createFixedHeightChart('statusDistributionChart', 'pie', {
          labels: statusLabels,
          datasets: [{
              data: statusValues,
              backgroundColor: [
                  'rgba(40, 167, 69, 0.7)',
                  'rgba(255, 193, 7, 0.7)',
                  'rgba(220, 53, 69, 0.7)',
                  'rgba(108, 117, 125, 0.7)'
              ],
              borderColor: [
                  'rgba(40, 167, 69, 1)',
                  'rgba(255, 193, 7, 1)',
                  'rgba(220, 53, 69, 1)',
                  'rgba(108, 117, 125, 1)'
              ],
              borderWidth: 1
          }]
      });
  }
}

/**
* Initialize date pickers with better UX
*/
function initializeDatePickers() {
  // Add flatpickr if available, otherwise use native datepickers
  if (typeof flatpickr !== 'undefined') {
      flatpickr('#fromDate', {
          dateFormat: 'Y-m-d',
          maxDate: 'today',
          onChange: function(selectedDates, dateStr) {
              // Update toDate min date to be equal to fromDate
              const toDatePicker = document.getElementById('toDate')._flatpickr;
              toDatePicker.set('minDate', dateStr);
          }
      });
      
      flatpickr('#toDate', {
          dateFormat: 'Y-m-d',
          maxDate: 'today',
          onChange: function(selectedDates, dateStr) {
              // Update fromDate max date to be equal to toDate
              const fromDatePicker = document.getElementById('fromDate')._flatpickr;
              fromDatePicker.set('maxDate', dateStr);
          }
      });
  }
}

/**
* Add quick date filter options (Last 7 days, Last 30 days, This month, etc.)
*/
function initializeQuickFilters() {
  // Create quick filter container if it doesn't exist
  if (!document.querySelector('.quick-filters')) {
      const filterForm = document.querySelector('form');
      const quickFiltersDiv = document.createElement('div');
      quickFiltersDiv.className = 'quick-filters d-flex flex-wrap mb-3';
      quickFiltersDiv.innerHTML = `
          <span class="me-2 text-muted">Quick filters:</span>
          <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" data-days="7">Last 7 days</button>
          <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" data-days="30">Last 30 days</button>
          <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" data-filter="this-month">This month</button>
          <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" data-filter="last-month">Last month</button>
          <button type="button" class="btn btn-sm btn-outline-secondary me-2 mb-2" data-filter="this-year">This year</button>
      `;
      
      const submitButton = filterForm.querySelector('button[type="submit"]').parentNode;
      filterForm.insertBefore(quickFiltersDiv, submitButton);
      
      // Add click event listeners to quick filter buttons
      const quickFilterButtons = document.querySelectorAll('.quick-filters button');
      quickFilterButtons.forEach(button => {
          button.addEventListener('click', function() {
              const today = new Date();
              let fromDate = new Date();
              let toDate = new Date();
              
              // Set date range based on button data attribute
              if (this.dataset.days) {
                  const days = parseInt(this.dataset.days);
                  fromDate.setDate(today.getDate() - days);
                  toDate = today;
              } else if (this.dataset.filter === 'this-month') {
                  fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                  toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
              } else if (this.dataset.filter === 'last-month') {
                  fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                  toDate = new Date(today.getFullYear(), today.getMonth(), 0);
              } else if (this.dataset.filter === 'this-year') {
                  fromDate = new Date(today.getFullYear(), 0, 1);
                  toDate = today;
              }
              
              // Format dates for input fields (YYYY-MM-DD)
              const formatDate = date => {
                  const year = date.getFullYear();
                  const month = String(date.getMonth() + 1).padStart(2, '0');
                  const day = String(date.getDate()).padStart(2, '0');
                  return `${year}-${month}-${day}`;
              };
              
              // Update date inputs
              document.getElementById('fromDate').value = formatDate(fromDate);
              document.getElementById('toDate').value = formatDate(toDate);
              
              // Highlight active button
              quickFilterButtons.forEach(btn => btn.classList.remove('active', 'btn-primary', 'text-white'));
              this.classList.add('active', 'btn-primary', 'text-white');
              
              // Auto-submit the form
              document.querySelector('form button[type="submit"]').click();
          });
      });
  }
}

/**
* Initialize export buttons functionality
*/
function initializeExportButtons() {
  // Create export buttons container if it doesn't exist
  if (!document.querySelector('.export-buttons')) {
      const contentDiv = document.querySelector('.content .container-fluid');
      const exportButtonsDiv = document.createElement('div');
      exportButtonsDiv.className = 'export-buttons d-flex justify-content-end mb-3';
      exportButtonsDiv.innerHTML = `
          <button type="button" class="btn btn-sm btn-outline-primary me-2" id="exportCSV">
              <i class="fas fa-file-csv me-1"></i> Export to CSV
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger me-2" id="exportPDF">
              <i class="fas fa-file-pdf me-1"></i> Export to PDF
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="printReport">
              <i class="fas fa-print me-1"></i> Print
          </button>
      `;
      
      // Insert after welcome section
      const welcomeSection = contentDiv.querySelector('.welcome-section');
      contentDiv.insertBefore(exportButtonsDiv, welcomeSection.nextSibling);
      
      // Add event listeners
      document.getElementById('exportCSV').addEventListener('click', exportToCSV);
      document.getElementById('exportPDF').addEventListener('click', exportToPDF);
      document.getElementById('printReport').addEventListener('click', printReport);
  }
}

/**
* Export table data to CSV
*/
function exportToCSV() {
  const table = document.querySelector('.table');
  if (!table) return;
  
  // Get table headers
  const headers = [];
  table.querySelectorAll('thead th').forEach(th => {
      headers.push(th.textContent.trim());
  });
  
  // Get table data
  const rows = [];
  table.querySelectorAll('tbody tr').forEach(tr => {
      const rowData = [];
      tr.querySelectorAll('td').forEach(td => {
          // Get text content and remove any extra whitespace
          rowData.push(td.textContent.trim().replace(/\s+/g, ' '));
      });
      rows.push(rowData);
  });
  
  // Create CSV content
  let csvContent = headers.join(',') + '\n';
  rows.forEach(row => {
      csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
  });
  
  // Create download link
  const encodedUri = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', 'visitor_analytics_' + new Date().toISOString().substring(0, 10) + '.csv');
  document.body.appendChild(link);
  
  // Trigger download
  link.click();
  document.body.removeChild(link);
}

/**
* Export to PDF (requires jsPDF library)
*/
function exportToPDF() {
  if (typeof jsPDF === 'undefined') {
      alert('PDF export requires the jsPDF library. Please include it in your project.');
      return;
  }
  
  // Create new PDF document
  const doc = new jsPDF();
  
  // Add title
  doc.setFontSize(16);
  doc.text('Visitor Analytics Report', 15, 15);
  
  // Add date range
  doc.setFontSize(12);
  const fromDate = document.getElementById('fromDate').value;
  const toDate = document.getElementById('toDate').value;
  doc.text(`Date Range: ${fromDate} to ${toDate}`, 15, 25);
  
  // Add statistics summary
  doc.setFontSize(14);
  doc.text('Summary Statistics', 15, 35);
  
  // Get statistics
  const totalVisitors = document.querySelector('.card:nth-child(1) h4').textContent;
  const activeCount = document.querySelector('.card:nth-child(2) h4').textContent;
  const usedCount = document.querySelector('.card:nth-child(3) h4').textContent;
  const expiredCount = document.querySelector('.card:nth-child(4) h4').textContent;
  
  // Add statistics to PDF
  doc.setFontSize(12);
  doc.text(`Total Visitors: ${totalVisitors}`, 15, 45);
  doc.text(`Active Codes: ${activeCount}`, 15, 55);
  doc.text(`Used Codes: ${usedCount}`, 15, 65);
  doc.text(`Expired/Revoked: ${expiredCount}`, 15, 75);
  
  // Convert charts to images and add to PDF
  // This requires html2canvas library for converting Canvas to image
  if (typeof html2canvas !== 'undefined') {
      const chartContainers = document.querySelectorAll('.card:has(canvas)');
      let yPosition = 85;
      
      chartContainers.forEach((container, index) => {
          const heading = container.querySelector('.card-header h5').textContent;
          doc.text(heading, 15, yPosition);
          yPosition += 10;
          
          const canvas = container.querySelector('canvas');
          const imgData = canvas.toDataURL('image/png');
          
          // Add image to PDF
          doc.addImage(imgData, 'PNG', 15, yPosition, 180, 90);
          
          yPosition += 100;
          
          // Add new page if needed
          if (yPosition > 250 && index < chartContainers.length - 1) {
              doc.addPage();
              yPosition = 20;
          }
      });
  }
  
  // Save PDF
  doc.save('visitor_analytics_' + new Date().toISOString().substring(0, 10) + '.pdf');
}

/**
* Print report
*/
function printReport() {
  window.print();
}

/**
* Initialize table sorting functionality
*/
function initializeTableSorting() {
  const table = document.querySelector('.table');
  if (!table) return;
  
  // Add sorting indicators and click events to table headers
  table.querySelectorAll('thead th').forEach((th, index) => {
      // Skip columns that shouldn't be sortable (e.g., actions)
      if (th.classList.contains('no-sort')) return;
      
      // Add sort icon and cursor style
      th.style.cursor = 'pointer';
      th.classList.add('position-relative');
      
      // Create sort indicator element
      const sortIndicator = document.createElement('span');
      sortIndicator.className = 'sort-indicator ms-2';
      sortIndicator.innerHTML = '<i class="fas fa-sort text-muted"></i>';
      th.appendChild(sortIndicator);
      
      // Add click event
      th.addEventListener('click', function() {
          sortTable(table, index, this);
      });
  });
}

/**
* Sort table by column
* 
* @param {HTMLElement} table - Table element
* @param {number} column - Column index
* @param {HTMLElement} header - Header element
*/
function sortTable(table, column, header) {
  const tbody = table.querySelector('tbody');
  const rows = Array.from(tbody.querySelectorAll('tr'));
  
  // Determine sort direction
  const currentDirection = header.getAttribute('data-sort') || 'none';
  const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
  
  // Reset all headers
  table.querySelectorAll('thead th').forEach(th => {
      th.setAttribute('data-sort', 'none');
      th.querySelector('.sort-indicator').innerHTML = '<i class="fas fa-sort text-muted"></i>';
  });
  
  // Update current header
  header.setAttribute('data-sort', newDirection);
  header.querySelector('.sort-indicator').innerHTML = 
      newDirection === 'asc' 
          ? '<i class="fas fa-sort-up text-primary"></i>' 
          : '<i class="fas fa-sort-down text-primary"></i>';
  
  // Sort rows
  rows.sort((a, b) => {
      let aValue = a.querySelectorAll('td')[column].textContent.trim();
      let bValue = b.querySelectorAll('td')[column].textContent.trim();
      
      // Check if values are dates
      if (isDate(aValue) && isDate(bValue)) {
          aValue = new Date(aValue).getTime();
          bValue = new Date(bValue).getTime();
      }
      // Check if values are numbers
      else if (!isNaN(aValue) && !isNaN(bValue)) {
          aValue = parseFloat(aValue);
          bValue = parseFloat(bValue);
      }
      // Default to string comparison
      else {
          aValue = aValue.toLowerCase();
          bValue = bValue.toLowerCase();
      }
      
      if (aValue < bValue) {
          return newDirection === 'asc' ? -1 : 1;
      }
      if (aValue > bValue) {
          return newDirection === 'asc' ? 1 : -1;
      }
      return 0;
  });
  
  // Update table
  rows.forEach(row => tbody.appendChild(row));
}

/**
* Check if a string is a valid date
* 
* @param {string} dateStr - Date string
* @return {boolean} True if valid date
*/
function isDate(dateStr) {
  // Simple date formats check (supports various formats)
  const dateRegex = /^\d{1,4}[-\/]\d{1,2}[-\/]\d{1,2}( \d{1,2}:\d{1,2}(:\d{1,2})?)?/;
  if (!dateRegex.test(dateStr)) return false;
  
  // Try parsing the date
  const date = new Date(dateStr);
  return !isNaN(date.getTime());
}

/**
* Initialize chart interaction
*/
function initializeChartInteraction() {
  // Make charts highlight data points on hover with better tooltips
  if (typeof Chart !== 'undefined') {
      Chart.defaults.global.hover.mode = 'nearest';
      Chart.defaults.global.hover.intersect = true;
      Chart.defaults.global.hover.animationDuration = 400;
      
      Chart.defaults.global.tooltips.backgroundColor = 'rgba(0, 0, 0, 0.8)';
      Chart.defaults.global.tooltips.titleFontStyle = 'bold';
      Chart.defaults.global.tooltips.titleFontColor = '#fff';
      Chart.defaults.global.tooltips.titleSpacing = 4;
      Chart.defaults.global.tooltips.bodyFontSize = 14;
      Chart.defaults.global.tooltips.bodySpacing = 4;
      Chart.defaults.global.tooltips.xPadding = 12;
      Chart.defaults.global.tooltips.yPadding = 12;
      Chart.defaults.global.tooltips.caretSize = 6;
      Chart.defaults.global.tooltips.cornerRadius = 6;
      Chart.defaults.global.tooltips.displayColors = true;
  }
}

/**
* Add comparison with previous period
*/
function addPeriodComparison() {
  // Calculate current period length in days
  const fromDate = new Date(document.getElementById('fromDate').value);
  const toDate = new Date(document.getElementById('toDate').value);
  const currentPeriodDays = Math.round((toDate - fromDate) / (1000 * 60 * 60 * 24)) + 1;
  
  // Calculate previous period dates
  const previousFromDate = new Date(fromDate);
  previousFromDate.setDate(previousFromDate.getDate() - currentPeriodDays);
  
  const previousToDate = new Date(toDate);
  previousToDate.setDate(previousToDate.getDate() - currentPeriodDays);
  
  // Format dates for display
  const formatDate = date => {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
  };
  
  const previousPeriodText = `${formatDate(previousFromDate)} to ${formatDate(previousToDate)}`;
  
  // Add comparison info to page
  const comparisonInfo = document.createElement('div');
  comparisonInfo.className = 'alert alert-info mb-4';
  comparisonInfo.innerHTML = `
      <div class="d-flex align-items-center">
          <div class="me-3">
              <i class="fas fa-info-circle fa-2x"></i>
          </div>
          <div>
              <h5 class="alert-heading mb-1">Period Comparison</h5>
              <p class="mb-0">Comparing current period with previous period: ${previousPeriodText}</p>
          </div>
          <button type="button" class="btn btn-primary ms-auto" id="fetchComparisonBtn">
              <i class="fas fa-sync-alt me-1"></i> Get Comparison
          </button>
      </div>
  `;
  
  // Insert after filter form
  const filterForm = document.querySelector('form');
  filterForm.parentNode.parentNode.insertBefore(comparisonInfo, filterForm.parentNode.nextSibling);
  
  // Add click event to fetch comparison data
  document.getElementById('fetchComparisonBtn').addEventListener('click', function() {
      this.disabled = true;
      this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
      
      // Here you would normally make an AJAX request to get previous period data
      // For demonstration, we'll simulate it with setTimeout
      setTimeout(function() {
          displayComparison();
          document.getElementById('fetchComparisonBtn').innerHTML = '<i class="fas fa-check me-1"></i> Comparison Loaded';
      }, 1500);
  });
}
// Add this function to the document ready event
document.addEventListener('DOMContentLoaded', function() {
  // Fix chart container heights
  const chartContainers = document.querySelectorAll('.card-body:has(canvas)');
  chartContainers.forEach(container => {
      container.style.height = '350px';
      container.style.maxHeight = '350px';
  });
});
/**
* Display comparison data (simulated)
*/
function displayComparison() {
  // Update stat cards with comparison data
  const statCards = document.querySelectorAll('.card h4');
  
  // Simulate previous period values (in a real app, these would come from the server)
  const prevValues = [
      Math.floor(parseInt(statCards[0].textContent) * 0.8),   // Total visitors
      Math.floor(parseInt(statCards[1].textContent) * 0.7),   // Active codes
      Math.floor(parseInt(statCards[2].textContent) * 0.9),   // Used codes
      Math.floor(parseInt(statCards[3].textContent) * 0.85)   // Expired/Revoked
  ];
  
  // Add comparison indicators to each card
  statCards.forEach((card, index) => {
      const currentValue = parseInt(card.textContent);
      const prevValue = prevValues[index];
      const percentChange = ((currentValue - prevValue) / prevValue * 100).toFixed(1);
      const isPositive = percentChange >= 0;
      
      // Create comparison element
      const comparisonEl = document.createElement('div');
      comparisonEl.className = 'mt-2 small';
      comparisonEl.innerHTML = `
          <span class="me-1 ${isPositive ? 'text-success' : 'text-danger'}">
              <i class="fas fa-${isPositive ? 'arrow-up' : 'arrow-down'}"></i> ${Math.abs(percentChange)}%
          </span>
          <span class="text-muted">vs previous period</span>
      `;
      
      // Add to card
      card.parentNode.appendChild(comparisonEl);
  });
}