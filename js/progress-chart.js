/**
 * Progress Chart Script
 * Handles the creation and rendering of budget vs. spent time visualization
 */
document.addEventListener('DOMContentLoaded', function() {
    // The data will be passed from PHP via a global variable
    if (typeof progressChartData === 'undefined') {
        console.error('Progress chart data not found. Make sure progressChartData is defined.');
        return;
    }
    
    const { activities, totalBudget, totalSpent, totalPlan } = progressChartData;
    
    const progressChart = document.getElementById('progressChart');
    if (!progressChart) {
        console.error('Progress chart container not found. Make sure an element with id "progressChart" exists.');
        return;
    }

    // Find the maximum ratio of spent/budget across all activities
    // Default max is 1 (100%) if no overruns
    const maxRatio = Math.max(1, totalBudget > 0 ? totalSpent / totalBudget : 0);
    
    // Calculate maximum budget for activity scaling
    const maxBudget = Math.max(...activities.map(a => a.BudgetHours || 0));
    
    /**
     * Creates scale markers based on a percentage
     * @param {HTMLElement} container - Container to append markers to
     * @param {number} count - Number of markers to create
     * @param {Function} labelFormatter - Function to format the label text
     * @param {string} markerClass - CSS class for markers
     * @param {string} lineClass - CSS class for marker lines
     */
    const createScaleMarkers = (container, count, labelFormatter, markerClass, lineClass) => {
        for (let i = 0; i <= count; i++) {
            const percent = (i / count) * 100;
            const label = labelFormatter(i, percent);
            
            const marker = document.createElement('div');
            marker.classList.add(markerClass);
            marker.style.left = `${percent}%`;
            marker.textContent = label;
            
            const line = document.createElement('div');
            line.classList.add(lineClass);
            line.style.left = `${percent}%`;
            
            container.appendChild(marker);
            container.appendChild(line);
        }
    };
    
    /**
     * Creates a progress bar row
     * @param {string} label - Label for the progress bar
     * @param {number} plan - Planned hours
     * @param {number} spent - Spent hours
     * @param {number} budget - Budget hours
     * @param {number} scaleWidth - Width of the bar as percentage
     * @param {number} scaleDivisor - Value to divide ratios by (maxRatio for total, 1 for activities)
     * @returns {HTMLElement} - Created row element
     */
    const createProgressBar = (label, plan, spent, budget, scaleWidth, scaleDivisor) => {
        // Calculate the actual ratios
        const actualRatio = budget > 0 ? spent / budget : 0;
        const planRatio = budget > 0 ? plan / budget : 0;
        
        // Scale the ratios
        const spentWidth = (actualRatio / scaleDivisor) * scaleWidth;
        const planWidth = (planRatio / scaleDivisor) * scaleWidth;
        
        // Format the text for display
        const labelText = budget > 0 ? `${spent} / ${budget}` : spent;
        
        const row = document.createElement('div');
        row.classList.add('progress-row');
        
        row.innerHTML = `
            <div class="progress-label">${label} (${labelText})</div>
            <div class="progress-bar-container">
                <div class="progress-bar spent-bar" style="width:${spentWidth}%"></div>
                <div class="progress-bar plan-bar" style="width:${planWidth}%"></div>
                <div class="progress-bar background-bar" style="width:${scaleWidth}%"></div>
            </div>
        `;
        
        return row;
    };
    
    // Create the scale header with percentage markers
    const scaleHeader = document.createElement('div');
    scaleHeader.classList.add('scale-header');
    
    createScaleMarkers(
        scaleHeader, 
        4, 
        (i, percent) => Math.round((percent / 100) * maxRatio * 100) + '%',
        'scale-marker',
        'scale-line'
    );
    
    progressChart.appendChild(scaleHeader);
    
    // Create the total bar (scaled by maxRatio)
    const totalRow = createProgressBar('Total', totalPlan, totalSpent, totalBudget, 100, maxRatio);
    progressChart.appendChild(totalRow);
    
    // Create the hour header with hour markers
    const labelRow = document.createElement('div');
    labelRow.classList.add('label-row');
    
    const hourHeader = document.createElement('div');
    hourHeader.classList.add('hour-header');
    labelRow.appendChild(hourHeader);
    
    createScaleMarkers(
        hourHeader,
        4,
        (i) => Math.round(i * (maxBudget / 4)),
        'hour-marker',
        'hour-line'
    );
    
    progressChart.appendChild(labelRow);
    
    // Create activity bars (scaled by maxBudget)
    activities.forEach(activity => {
        const scale = activity.BudgetHours > 0 ? activity.BudgetHours / maxBudget : 1;
        const scaleWidth = scale * 100;
        
        const activityRow = createProgressBar(
            activity.name, 
            activity.PlanHours, 
            activity.SpentHours, 
            activity.BudgetHours, 
            scaleWidth, 
            1 // For activities, we use the actual ratio
        );
        
        progressChart.appendChild(activityRow);
    });
});