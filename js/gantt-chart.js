/**
 * Gantt Chart Script
 * Handles the creation and rendering of a timeline visualization
 */
document.addEventListener('DOMContentLoaded', function () {
    const barHeight   = 30;  // each activity row is 30 px tall
    const rowSpacing  = 10;  // 10 px gap between rows
    const labelOffset = 10;  // reserve x px at the top for the date labels

    // The data will be passed from PHP via a global variable
    if (typeof ganttChartData === 'undefined') {
        console.error('Gantt chart data not found. Make sure ganttChartData is defined.');
        return;
    }
    
    const activities = ganttChartData.activities;
    const ganttChart = document.getElementById('ganttChart');
    
    if (!ganttChart) {
        console.error('Gantt chart container not found. Make sure an element with id "ganttChart" exists.');
        return;
    }
    
    const currentDate = new Date();

    // 1) Convert activity dates to Date objects
    activities.forEach(a => {
        a.startDate = new Date(a.startDate);
        a.endDate   = new Date(a.endDate);
    });

    // 2) Compute earliest & latest
    const earliestDate = new Date(Math.min(...activities.map(a => a.startDate)));
    const latestDate   = new Date(Math.max(...activities.map(a => a.endDate)));
    const totalDays    = Math.ceil((latestDate - earliestDate) / (1000*60*60*24));

    // 3) Create at most 10 date-labels
    const maxLabels = 10;
    const step = Math.max(1, Math.floor(totalDays / (maxLabels - 1)));
    const dateLabels = document.createElement('div');
    dateLabels.classList.add('date-labels');
    for (let i = 0; i <= totalDays; i += step) {
        const d = new Date(earliestDate);
        d.setDate(d.getDate() + i);
        const lbl = document.createElement('div');
        lbl.textContent = d.toISOString().slice(0,10);
        dateLabels.appendChild(lbl);
    }
    ganttChart.appendChild(dateLabels);

    // 4) Assign each activity to the lowest free "track" (row) to avoid overlap
    activities.sort((a, b) => a.startDate - b.startDate);
    const tracks = []; // will hold the endDate of last activity in each track
    activities.forEach(a => {
        let t = tracks.findIndex(endDate => a.startDate > endDate);
        if (t === -1) {
            t = tracks.length;
            tracks.push(a.endDate);
        } else {
            tracks[t] = a.endDate;
        }
        a.track = t;
    });

    // 5) Render bars
    activities.forEach(a => {
        const bar = document.createElement('div');
        const leftPct   = (a.startDate - earliestDate) / (latestDate - earliestDate) * 100;
        const widthPct  = (a.endDate   - a.startDate)   / (latestDate - earliestDate) * 100;

        bar.classList.add('activity-bar');
        bar.style.left = `${leftPct}%`;
        bar.style.width = `${widthPct}%`;
        bar.style.top   = `${a.track * 40 + labelOffset}px`; // 40px per track + padding for labels
        bar.textContent = a.name;
        ganttChart.appendChild(bar);
    });

    // 6) Draw current-date line
    const currentOffset = (currentDate - earliestDate) / (latestDate - earliestDate) * 100;
    const line = document.createElement('div');
    line.classList.add('current-date-line');
    line.style.left = `${currentOffset}%`;
    ganttChart.appendChild(line);

    // 7) Finally, set container height to fit all tracks + label area
    const totalHeight = labelOffset + tracks.length * (barHeight + rowSpacing);
    ganttChart.style.height = `${totalHeight}px`;
});