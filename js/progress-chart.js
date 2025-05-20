/**
 * Progress Chart Script
 * Handles the creation and rendering of budget vs. spent time visualization
 * with three-state display: normal, overspent (but within budget), over budget
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
    // Calculate maximum realised for activity scaling
    const maxSpent = Math.max(...activities.map(a => a.SpentHours || 0));
    // Get largest value in the chart
    const largestValue = Math.max(maxBudget, maxSpent);

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
     * Creates a progress bar row with three possible states:
     * 1. spent <= plan: normal display
     * 2. plan < spent <= budget: overspent but within budget
     * 3. spent > budget: over budget visualization
     * 
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
        const planRatio = budget > 0 ? plan / budget : 0;
        const spentRatio = budget > 0 ? spent / budget : 0;
        const budgetRatio = 1; // Budget is always 100% of itself
        
        // Scale the ratios
        const planWidth = (planRatio / scaleDivisor) * scaleWidth;
        const spentWidth = (spentRatio / scaleDivisor) * scaleWidth;
        const budgetWidth = (budgetRatio / scaleDivisor) * scaleWidth;
        
        // Format the text for display
        const labelText = budget > 0 ? `${spent} / ${plan} / ${budget}` : spent;
        
        const row = document.createElement('div');
        row.classList.add('progress-row');
        
        // Create the HTML structure
        const label_div = document.createElement('div');
        label_div.classList.add('progress-label');
        label_div.innerHTML = `${label}<br>(${labelText})`;
        
        const container = document.createElement('div');
        container.classList.add('progress-bar-container');
        
        // Create background bar first (always at the bottom)
        const backgroundBar = document.createElement('div');
        backgroundBar.classList.add('progress-bar', 'background-bar');
        backgroundBar.style.width = `${scaleWidth}%`;
        container.appendChild(backgroundBar);
        
        // Plan bar is always present
        const planBar = document.createElement('div');
        planBar.classList.add('progress-bar', 'plan-bar');
        planBar.style.width = `${planWidth}%`;
        
        // Case 1: spent <= plan
        if (spent <= plan) {
            const spentBar = document.createElement('div');
            spentBar.classList.add('progress-bar', 'spent-bar');
            spentBar.style.width = `${spentWidth}%`;
            
            container.appendChild(spentBar); // Spent bar at bottom
            container.appendChild(planBar);  // Plan bar on top
        } 
        // Case 2: plan < spent <= budget
        else if (spent <= budget) {
            const normalSpentBar = document.createElement('div');
            normalSpentBar.classList.add('progress-bar', 'spent-bar');
            normalSpentBar.style.width = `${planWidth}%`;
            
            const overspentBar = document.createElement('div');
            overspentBar.classList.add('progress-bar', 'overspent-bar');
            overspentBar.style.width = `${spentWidth - planWidth}%`;
            overspentBar.style.left = `${planWidth}%`;
            overspentBar.style.position = 'absolute';
            
            container.appendChild(normalSpentBar);
            container.appendChild(overspentBar);
            container.appendChild(planBar);
        } 
        // Case 3: spent > budget
        else {
            const normalSpentBar = document.createElement('div');
            normalSpentBar.classList.add('progress-bar', 'spent-bar');
            normalSpentBar.style.width = `${planWidth}%`;
            
            const overspentBar = document.createElement('div');
            overspentBar.classList.add('progress-bar', 'overspent-bar');
            overspentBar.style.width = `${budgetWidth - planWidth}%`;
            overspentBar.style.left = `${planWidth}%`;
            overspentBar.style.position = 'absolute';
            
            const overBudgetBar = document.createElement('div');
            overBudgetBar.classList.add('progress-bar', 'over-budget-bar');
            overBudgetBar.style.width = `${spentWidth - budgetWidth}%`;
            overBudgetBar.style.left = `${budgetWidth}%`;
            overBudgetBar.style.position = 'absolute';
            
            container.appendChild(normalSpentBar);
            container.appendChild(overspentBar);
            container.appendChild(overBudgetBar);
            container.appendChild(planBar);
        }
        
        row.appendChild(label_div);
        row.appendChild(container);
        
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
    totalRow.classList.add('total-row'); // Add a class to style the total row differently if needed
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
        (i) => Math.round(i * (largestValue / 4)),
        'hour-marker',
        'hour-line'
    );
    
    progressChart.appendChild(labelRow);
    
    // Create activity bars (scaled by largestValue)
    activities.forEach(activity => {
        // Handle potential null/undefined values
        const budgetHours = activity.BudgetHours || 0;
        const spentHours = activity.SpentHours || 0;
        const planHours = activity.PlanHours || 0;
        
        const scale = budgetHours > 0 ? budgetHours / largestValue : 1;
        const scaleWidth = scale * 100;
        
        const activityRow = createProgressBar(
            activity.name || 'Unnamed Activity', 
            planHours, 
            spentHours, 
            budgetHours, 
            scaleWidth, 
            1 // For activities, we use the actual ratio
        );
        
        progressChart.appendChild(activityRow);
    });

});