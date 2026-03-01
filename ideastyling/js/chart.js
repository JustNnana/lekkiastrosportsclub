/**
 * Gate Wey Access Management System
 * Chart Initialization
 * 
 * This file contains functions to initialize and configure charts used in dashboards
 */

// Default Chart.js Configuration
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6c757d';
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
Chart.defaults.plugins.legend.position = 'bottom';

/**
 * Initialize a bar chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {string} label - Dataset label
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initBarChart(elementId, labels, data, label, options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: 'rgba(55, 66, 250, 0.6)',
                borderColor: 'rgba(55, 66, 250, 1)',
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Initialize a line chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {string} label - Dataset label
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initLineChart(elementId, labels, data, label, options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        elements: {
            line: {
                tension: 0.4
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: 'rgba(55, 66, 250, 0.2)',
                borderColor: 'rgba(55, 66, 250, 1)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: chartOptions
    });
}

/**
 * Initialize a pie chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {array} colors - Background colors for each segment
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initPieChart(elementId, labels, data, colors = [], options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default colors if none provided
    if (!colors || colors.length === 0) {
        colors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)'
        ];
    }
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Initialize a doughnut chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {array} colors - Background colors for each segment
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initDoughnutChart(elementId, labels, data, colors = [], options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default colors if none provided
    if (!colors || colors.length === 0) {
        colors = [
            'rgba(255, 99, 132, 0.7)',
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)'
        ];
    }
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Initialize a horizontal bar chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {string} label - Dataset label
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initHorizontalBarChart(elementId, labels, data, label, options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            },
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: 'rgba(55, 66, 250, 0.6)',
                borderColor: 'rgba(55, 66, 250, 1)',
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
}

/**
 * Initialize a stacked bar chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} datasets - Chart datasets
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initStackedBarChart(elementId, labels, datasets, options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                mode: 'index',
                intersect: false
            },
        },
        scales: {
            x: {
                stacked: true,
            },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: chartOptions
    });
}

/**
 * Initialize a radar chart
 * 
 * @param {string} elementId - Canvas element ID
 * @param {array} labels - Chart labels
 * @param {array} data - Chart data
 * @param {string} label - Dataset label
 * @param {object} options - Additional options
 * @return {Chart} Chart instance
 */
function initRadarChart(elementId, labels, data, label, options = {}) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    // Default options
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        elements: {
            line: {
                tension: 0.2
            }
        }
    };
    
    // Merge options
    const chartOptions = { ...defaultOptions, ...options };
    
    // Create and return chart
    return new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: 'rgba(55, 66, 250, 0.2)',
                borderColor: 'rgba(55, 66, 250, 1)',
                pointBackgroundColor: 'rgba(55, 66, 250, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(55, 66, 250, 1)'
            }]
        },
        options: chartOptions
    });
}

/**
 * Update chart data
 * 
 * @param {Chart} chart - Chart instance
 * @param {array} labels - New labels
 * @param {array} data - New data
 */
function updateChartData(chart, labels, data) {
    chart.data.labels = labels;
    chart.data.datasets[0].data = data;
    chart.update();
}

/**
 * Set up dashboard charts
 * Called from dashboard pages to initialize charts
 */
function setupDashboardCharts() {
    // Check if chart elements exist before initializing
    
    // Access Code Usage chart (line chart)
    if (document.getElementById('codeUsageChart')) {
        // Get data from data attributes or API
        const labels = JSON.parse(document.getElementById('codeUsageChart').getAttribute('data-labels') || '[]');
        const data = JSON.parse(document.getElementById('codeUsageChart').getAttribute('data-values') || '[]');
        
        initLineChart('codeUsageChart', labels, data, 'Access Codes');
    }
    
    // Purpose Distribution chart (pie chart)
    if (document.getElementById('purposeDistributionChart')) {
        // Get data from data attributes or API
        const labels = JSON.parse(document.getElementById('purposeDistributionChart').getAttribute('data-labels') || '[]');
        const data = JSON.parse(document.getElementById('purposeDistributionChart').getAttribute('data-values') || '[]');
        
        initPieChart('purposeDistributionChart', labels, data);
    }
    
    // Daily Verifications chart (bar chart)
    if (document.getElementById('dailyVerificationsChart')) {
        // Get data from data attributes or API
        const labels = JSON.parse(document.getElementById('dailyVerificationsChart').getAttribute('data-labels') || '[]');
        const data = JSON.parse(document.getElementById('dailyVerificationsChart').getAttribute('data-values') || '[]');
        
        initBarChart('dailyVerificationsChart', labels, data, 'Verifications');
    }
    
    // Verification Status chart (doughnut chart)
    if (document.getElementById('verificationStatusChart')) {
        // Get data from data attributes or API
        const labels = JSON.parse(document.getElementById('verificationStatusChart').getAttribute('data-labels') || '[]');
        const data = JSON.parse(document.getElementById('verificationStatusChart').getAttribute('data-values') || '[]');
        const colors = [
            'rgba(40, 167, 69, 0.7)',  // Success/Entry
            'rgba(220, 53, 69, 0.7)',  // Danger/Denied
            'rgba(255, 193, 7, 0.7)'   // Warning/Exit
        ];
        
        initDoughnutChart('verificationStatusChart', labels, data, colors);
    }
    
    // Revenue Chart (bar chart)
    if (document.getElementById('revenueChart')) {
        // Get data from data attributes or API
        const labels = JSON.parse(document.getElementById('revenueChart').getAttribute('data-labels') || '[]');
        const data = JSON.parse(document.getElementById('revenueChart').getAttribute('data-values') || '[]');
        
        initBarChart('revenueChart', labels, data, 'Revenue', {
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            }
        });
    }
    
    // Clan Distribution Chart (doughnut chart)
    if (document.getElementById('clanDistributionChart')) {
        // Get data from data attributes or API
        const labels = ['Active', 'Inactive', 'Free'];
        const data = JSON.parse(document.getElementById('clanDistributionChart').getAttribute('data-values') || '[]');
        const colors = [
            'rgba(40, 167, 69, 0.7)',  // Success/Active
            'rgba(220, 53, 69, 0.7)',  // Danger/Inactive
            'rgba(23, 162, 184, 0.7)'  // Info/Free
        ];
        
        initDoughnutChart('clanDistributionChart', labels, data, colors);
    }
}

// Initialize charts when document is ready
document.addEventListener('DOMContentLoaded', function() {
    setupDashboardCharts();
});