/**
 * GateWey - Dasher UI Chart Configuration
 * Chart.js integration with Dasher design system and theme support
 */

class DasherChartConfig {
    constructor() {
        this.isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        this.init();
    }

    init() {
        // Wait for Chart.js to be available
        if (typeof Chart !== 'undefined') {
            this.setupChartDefaults();
            this.setupThemeListener();
        } else {
            // Wait for Chart.js to load
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Chart !== 'undefined') {
                    this.setupChartDefaults();
                    this.setupThemeListener();
                }
            });
        }
    }

    /**
     * Get CSS variable value
     */
    getCSSVariable(variable) {
        return getComputedStyle(document.documentElement)
            .getPropertyValue(variable)
            .trim();
    }

    /**
     * Get current theme colors
     */
    getThemeColors() {
        return {
            primary: this.getCSSVariable('--primary'),
            secondary: this.getCSSVariable('--secondary'),
            success: this.getCSSVariable('--success'),
            danger: this.getCSSVariable('--danger'),
            warning: this.getCSSVariable('--warning'),
            info: this.getCSSVariable('--info'),
            textPrimary: this.getCSSVariable('--text-primary'),
            textSecondary: this.getCSSVariable('--text-secondary'),
            textMuted: this.getCSSVariable('--text-muted'),
            borderColor: this.getCSSVariable('--border-color'),
            bgCard: this.getCSSVariable('--bg-card'),
            bgSecondary: this.getCSSVariable('--bg-secondary')
        };
    }

    /**
     * Get color palette for charts
     */
    getColorPalette() {
        const colors = this.getThemeColors();
        return [
            colors.primary,
            colors.success,
            colors.warning,
            colors.danger,
            colors.info,
            colors.secondary,
            '#8b5cf6', // purple
            '#f59e0b', // amber
            '#06b6d4', // cyan
            '#84cc16', // lime
            '#f97316', // orange
            '#ec4899'  // pink
        ];
    }

    /**
     * Setup Chart.js global defaults
     */
    setupChartDefaults() {
        const colors = this.getThemeColors();
        
        Chart.defaults.font.family = this.getCSSVariable('--font-family-base') || 'Public Sans, sans-serif';
        Chart.defaults.font.size = 13;
        Chart.defaults.font.weight = '400';
        Chart.defaults.color = colors.textSecondary;
        Chart.defaults.borderColor = colors.borderColor;
        Chart.defaults.backgroundColor = colors.bgCard;

        // Plugin defaults
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 16;
        Chart.defaults.plugins.legend.labels.font = {
            size: 13,
            weight: '500'
        };

        Chart.defaults.plugins.tooltip.backgroundColor = colors.bgCard;
        Chart.defaults.plugins.tooltip.titleColor = colors.textPrimary;
        Chart.defaults.plugins.tooltip.bodyColor = colors.textSecondary;
        Chart.defaults.plugins.tooltip.borderColor = colors.borderColor;
        Chart.defaults.plugins.tooltip.borderWidth = 1;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.padding = 12;

        // Scale defaults
        Chart.defaults.scales.linear.grid.color = colors.borderColor;
        Chart.defaults.scales.linear.ticks.color = colors.textMuted;
        Chart.defaults.scales.category.grid.color = colors.borderColor;
        Chart.defaults.scales.category.ticks.color = colors.textMuted;
    }

    /**
     * Listen for theme changes
     */
    setupThemeListener() {
        document.addEventListener('themeChanged', (event) => {
            this.isDarkMode = event.detail.theme === 'dark';
            this.updateAllCharts();
        });
    }

    /**
     * Update all existing charts with new theme
     */
    updateAllCharts() {
        Chart.instances.forEach(chart => {
            this.updateChartTheme(chart);
        });
    }

    /**
     * Update individual chart theme
     */
    updateChartTheme(chart) {
        const colors = this.getThemeColors();
        
        // Update chart options
        if (chart.options.plugins.legend) {
            chart.options.plugins.legend.labels.color = colors.textSecondary;
        }
        
        if (chart.options.plugins.tooltip) {
            chart.options.plugins.tooltip.backgroundColor = colors.bgCard;
            chart.options.plugins.tooltip.titleColor = colors.textPrimary;
            chart.options.plugins.tooltip.bodyColor = colors.textSecondary;
            chart.options.plugins.tooltip.borderColor = colors.borderColor;
        }

        // Update scales
        if (chart.options.scales) {
            Object.keys(chart.options.scales).forEach(scaleKey => {
                const scale = chart.options.scales[scaleKey];
                if (scale.grid) {
                    scale.grid.color = colors.borderColor;
                }
                if (scale.ticks) {
                    scale.ticks.color = colors.textMuted;
                }
            });
        }

        chart.update();
    }

    /**
     * Create line chart configuration
     */
    getLineChartConfig(data, options = {}) {
        const colors = this.getThemeColors();
        const palette = this.getColorPalette();
        
        return {
            type: 'line',
            data: {
                ...data,
                datasets: data.datasets.map((dataset, index) => ({
                    ...dataset,
                    borderColor: dataset.borderColor || palette[index % palette.length],
                    backgroundColor: dataset.backgroundColor || this.addAlpha(palette[index % palette.length], 0.1),
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: dataset.borderColor || palette[index % palette.length],
                    pointBorderColor: colors.bgCard,
                    pointBorderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        align: 'center'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        }
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create bar chart configuration
     */
    getBarChartConfig(data, options = {}) {
        const palette = this.getColorPalette();
        
        return {
            type: 'bar',
            data: {
                ...data,
                datasets: data.datasets.map((dataset, index) => ({
                    ...dataset,
                    backgroundColor: dataset.backgroundColor || this.addAlpha(palette[index % palette.length], 0.8),
                    borderColor: dataset.borderColor || palette[index % palette.length],
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        }
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create doughnut chart configuration
     */
    getDoughnutChartConfig(data, options = {}) {
        const palette = this.getColorPalette();
        
        return {
            type: 'doughnut',
            data: {
                ...data,
                datasets: data.datasets.map(dataset => ({
                    ...dataset,
                    backgroundColor: dataset.backgroundColor || palette.slice(0, data.labels.length),
                    borderColor: this.getCSSVariable('--bg-card'),
                    borderWidth: 3,
                    hoverBorderWidth: 4,
                    cutout: '70%'
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create pie chart configuration
     */
    getPieChartConfig(data, options = {}) {
        const palette = this.getColorPalette();
        
        return {
            type: 'pie',
            data: {
                ...data,
                datasets: data.datasets.map(dataset => ({
                    ...dataset,
                    backgroundColor: dataset.backgroundColor || palette.slice(0, data.labels.length),
                    borderColor: this.getCSSVariable('--bg-card'),
                    borderWidth: 2,
                    hoverBorderWidth: 3
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create area chart configuration
     */
    getAreaChartConfig(data, options = {}) {
        const colors = this.getThemeColors();
        const palette = this.getColorPalette();
        
        return {
            type: 'line',
            data: {
                ...data,
                datasets: data.datasets.map((dataset, index) => ({
                    ...dataset,
                    borderColor: dataset.borderColor || palette[index % palette.length],
                    backgroundColor: dataset.backgroundColor || this.addAlpha(palette[index % palette.length], 0.2),
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointBackgroundColor: dataset.borderColor || palette[index % palette.length],
                    pointBorderColor: colors.bgCard,
                    pointBorderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2]
                        },
                        stacked: options.stacked || false
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create polar area chart configuration
     */
    getPolarAreaChartConfig(data, options = {}) {
        const palette = this.getColorPalette();
        
        return {
            type: 'polarArea',
            data: {
                ...data,
                datasets: data.datasets.map(dataset => ({
                    ...dataset,
                    backgroundColor: dataset.backgroundColor || palette.slice(0, data.labels.length).map(color => this.addAlpha(color, 0.7)),
                    borderColor: dataset.borderColor || palette.slice(0, data.labels.length),
                    borderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        grid: {
                            color: this.getCSSVariable('--border-color')
                        },
                        pointLabels: {
                            color: this.getCSSVariable('--text-muted')
                        },
                        ticks: {
                            color: this.getCSSVariable('--text-muted')
                        }
                    }
                },
                ...options
            }
        };
    }

    /**
     * Create radar chart configuration
     */
    getRadarChartConfig(data, options = {}) {
        const palette = this.getColorPalette();
        
        return {
            type: 'radar',
            data: {
                ...data,
                datasets: data.datasets.map((dataset, index) => ({
                    ...dataset,
                    borderColor: dataset.borderColor || palette[index % palette.length],
                    backgroundColor: dataset.backgroundColor || this.addAlpha(palette[index % palette.length], 0.2),
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: dataset.borderColor || palette[index % palette.length],
                    pointBorderColor: this.getCSSVariable('--bg-card'),
                    pointBorderWidth: 2
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        grid: {
                            color: this.getCSSVariable('--border-color')
                        },
                        pointLabels: {
                            color: this.getCSSVariable('--text-muted')
                        },
                        ticks: {
                            color: this.getCSSVariable('--text-muted')
                        }
                    }
                },
                ...options
            }
        };
    }

    /**
     * Add alpha transparency to hex color
     */
    addAlpha(color, alpha) {
        if (color.startsWith('#')) {
            const hex = color.slice(1);
            const r = parseInt(hex.slice(0, 2), 16);
            const g = parseInt(hex.slice(2, 4), 16);
            const b = parseInt(hex.slice(4, 6), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        return color;
    }

    /**
     * Create a simple chart with automatic theming
     */
    createChart(canvasId, type, data, options = {}) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.warn(`Canvas with ID '${canvasId}' not found`);
            return null;
        }

        let config;
        switch (type) {
            case 'line':
                config = this.getLineChartConfig(data, options);
                break;
            case 'bar':
                config = this.getBarChartConfig(data, options);
                break;
            case 'doughnut':
                config = this.getDoughnutChartConfig(data, options);
                break;
            case 'pie':
                config = this.getPieChartConfig(data, options);
                break;
            case 'area':
                config = this.getAreaChartConfig(data, options);
                break;
            case 'polarArea':
                config = this.getPolarAreaChartConfig(data, options);
                break;
            case 'radar':
                config = this.getRadarChartConfig(data, options);
                break;
            default:
                console.warn(`Chart type '${type}' not supported`);
                return null;
        }

        return new Chart(canvas, config);
    }

    /**
     * Update chart data
     */
    updateChartData(chart, newData) {
        chart.data = { ...chart.data, ...newData };
        chart.update();
    }

    /**
     * Animate chart on creation
     */
    animateChart(chart, duration = 1000) {
        chart.options.animation = {
            duration: duration,
            easing: 'easeInOutQuart'
        };
        chart.update();
    }
}

// Initialize chart configuration
const dasherCharts = new DasherChartConfig();

// Helper functions for easy chart creation
window.DasherCharts = {
    // Create charts
    createLineChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'line', data, options),
    createBarChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'bar', data, options),
    createDoughnutChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'doughnut', data, options),
    createPieChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'pie', data, options),
    createAreaChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'area', data, options),
    createPolarChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'polarArea', data, options),
    createRadarChart: (canvasId, data, options) => dasherCharts.createChart(canvasId, 'radar', data, options),
    
    // Utility functions
    updateData: (chart, newData) => dasherCharts.updateChartData(chart, newData),
    animate: (chart, duration) => dasherCharts.animateChart(chart, duration),
    getColorPalette: () => dasherCharts.getColorPalette(),
    getThemeColors: () => dasherCharts.getThemeColors()
};

// Example usage functions
window.DasherChartExamples = {
    /**
     * Create a sample dashboard stats chart
     */
    createStatsChart(canvasId) {
        const data = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [12000, 19000, 15000, 25000, 22000, 30000],
                tension: 0.4
            }]
        };
        
        return DasherCharts.createAreaChart(canvasId, data, {
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            }
        });
    },

    /**
     * Create a sample visitor analytics chart
     */
    createVisitorChart(canvasId) {
        const data = {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [
                {
                    label: 'Visitors',
                    data: [65, 59, 80, 81, 56, 55, 40]
                },
                {
                    label: 'Page Views',
                    data: [120, 140, 180, 190, 130, 125, 90]
                }
            ]
        };
        
        return DasherCharts.createLineChart(canvasId, data);
    },

    /**
     * Create a sample status distribution chart
     */
    createStatusChart(canvasId) {
        const data = {
            labels: ['Active', 'Inactive', 'Pending', 'Expired'],
            datasets: [{
                data: [45, 15, 20, 20]
            }]
        };
        
        return DasherCharts.createDoughnutChart(canvasId, data);
    },

    /**
     * Create a sample performance comparison chart
     */
    createPerformanceChart(canvasId) {
        const data = {
            labels: ['Q1', 'Q2', 'Q3', 'Q4'],
            datasets: [
                {
                    label: '2023',
                    data: [85, 90, 78, 95]
                },
                {
                    label: '2024',
                    data: [92, 88, 85, 98]
                }
            ]
        };
        
        return DasherCharts.createBarChart(canvasId, data);
    }
};

// Auto-initialize charts with data attributes
document.addEventListener('DOMContentLoaded', function() {
    // Find all canvas elements with data-chart attributes
    const chartCanvases = document.querySelectorAll('canvas[data-chart-type]');
    
    chartCanvases.forEach(canvas => {
        const type = canvas.getAttribute('data-chart-type');
        const dataSource = canvas.getAttribute('data-chart-data');
        
        if (dataSource && window[dataSource]) {
            const data = window[dataSource];
            dasherCharts.createChart(canvas.id, type, data);
        }
    });
});