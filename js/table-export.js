/**
 * Table Export Utility
 * Provides CSV and Excel export functionality for planning tables
 */

/**
 * Export table data to CSV format
 * @param {Array} headers - Array of column headers
 * @param {Array} rows - Array of row data (each row is an array)
 * @param {string} filename - Name of the file to download (without extension)
 */
function exportToCSV(headers, rows, filename) {
    const csv = [];

    // Add headers
    csv.push(headers.join(','));

    // Add data rows
    rows.forEach(row => {
        csv.push(row.map(cell => {
            // Escape quotes and wrap in quotes if contains comma or quotes
            const escaped = String(cell).replace(/"/g, '""');
            return escaped.includes(',') || escaped.includes('"') ? `"${escaped}"` : escaped;
        }).join(','));
    });

    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Export table data to Excel format
 * @param {Array} headers - Array of column headers
 * @param {Array} rows - Array of row data (each row is an array)
 * @param {string} filename - Name of the file to download (without extension)
 */
function exportToExcel(headers, rows, filename) {
    // Create HTML table
    let html = '<html><head><meta charset="utf-8"></head><body><table>';

    // Add headers
    html += '<tr>';
    headers.forEach(header => {
        html += `<th>${escapeHtml(header)}</th>`;
    });
    html += '</tr>';

    // Add data rows
    rows.forEach(row => {
        html += '<tr>';
        row.forEach(cell => {
            html += `<td>${escapeHtml(String(cell))}</td>`;
        });
        html += '</tr>';
    });

    html += '</table></body></html>';

    // Download as Excel
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.xls';
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Helper function to escape HTML
 * @param {string} text - Text to escape
 * @returns {string} Escaped HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
