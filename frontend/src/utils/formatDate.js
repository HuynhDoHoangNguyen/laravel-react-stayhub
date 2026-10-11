/**
 * Format date string or Date object to dd/MM/yyyy.
 * e.g., "2026-10-25" -> "25/10/2026"
 */
export function formatDate(dateInput) {
    if (!dateInput) return '';

    const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(date.getTime())) return String(dateInput);

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}
