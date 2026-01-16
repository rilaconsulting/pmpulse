/**
 * Vendor module utility formatters
 */

/**
 * Format a number as currency (USD)
 * @param {number|null|undefined} amount
 * @returns {string}
 */
export const formatCurrency = (amount) => {
    if (amount === null || amount === undefined) return '-';
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
};

/**
 * Format a date string to a readable format
 * @param {string|null|undefined} dateString
 * @returns {string}
 */
export const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

/**
 * Format a number of days with "days" suffix
 * @param {number|null|undefined} days
 * @returns {string}
 */
export const formatDays = (days) => {
    if (days === null || days === undefined) return '-';
    return `${Math.round(days)} days`;
};
