
/**
 * Progress Chart Script
 * Handles the creation and rendering of budget vs. spent time visualization
 */
document.addEventListener('DOMContentLoaded', function () {
    // The data will be passed from PHP via a global variable
    if (typeof progressChartData === 'undefined') {
        console.error('Progress chart data not found. Make sure progressChartData is defined.');
        return;
    }
    
    const activities = progressChartData.activities;
    const totalBudget = progressChartData.totalBudget;
    const totalSpent = progressChartData.totalSpent;
    const totalPlan = progressChartData.totalPlan;

    const progressChart = document.getElementById('progressChart');
    if (!progressChart) {
        console.error('Progress chart container not found. Make sure an element with id "progressChart" exists.');
        return;
    }

        // Find the maximum ratio of spent/budget across all activities and overall total
        let maxRatio = 0;
        
        // Check the total project ratio
        if (totalBudget > 0) {
            maxRatio = Math.max(maxRatio, totalSpent / totalBudget);
        }
        
        // Check each activity ratio
        activities.forEach(a => {
            if (a.BudgetHours > 0) {
                maxRatio = Math.max(maxRatio, a.SpentHours / a.BudgetHours);
            }
        });
        
        // If no overruns, default max is 1 (100%)
        maxRatio = Math.max(1, maxRatio);

        // Create the scale header with markers
        const scaleHeader = document.createElement('div');
        scaleHeader.classList.add('scale-header');
        
        // Create 5 evenly spaced markers based on maxRatio
        for (let i = 0; i <= 4; i++) {
            const percent = i * 25;
            const ratioValue = (percent / 100) * maxRatio;
            const label = Math.round(ratioValue * 100) + '%';
            
            const marker = document.createElement('div');
            marker.classList.add('scale-marker');
            marker.style.left = `${percent}%`;
            marker.textContent = label;
            
            // Add a small vertical line under each marker
            const line = document.createElement('div');
            line.classList.add('scale-line');
            line.style.left = `${percent}%`;
            
            scaleHeader.appendChild(marker);
            scaleHeader.appendChild(line);
        }
        
        progressChart.appendChild(scaleHeader);

        const createBar = (label, plan, spent, budget) => {
            // Calculate the actual ratio
            const actualRatio = budget > 0 ? spent / budget : 0;
            const planRatio = budget > 0 ? plan / budget : 0;
        
            // Scale the ratio based on maxRatio (if over 100%)
            const spentWidth = (actualRatio / maxRatio) * 100;
            const planWidth = (planRatio / maxRatio) * 100;
        
            // Format the percentage for display
            const percentText = budget > 0 
                ? Math.round((spent / budget) * 100) + '%' 
                : 'N/A';
        
            const row = document.createElement('div');
            row.classList.add('progress-row');
        
            row.innerHTML = `
                <div class="progress-label">${label} (${percentText})</div>
                <div class="progress-bar-container">
                    <div class="progress-bar spent-bar" style="width:${spentWidth}%"></div>
                    <div class="progress-bar plan-bar" style="width:${planWidth}%"></div>
                </div>
            `;
            progressChart.appendChild(row);
        };

        createBar("Total Project", totalPlan, totalSpent, totalBudget);
        activities.forEach(a => {
            createBar(a.name, a.PlanHours, a.SpentHours, a.BudgetHours);
        });
    });
