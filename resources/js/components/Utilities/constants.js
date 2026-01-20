import { getIconComponent, getColorScheme, DEFAULT_ICON, DEFAULT_COLOR_SCHEME } from './availableIcons';

/**
 * Re-export functions from availableIcons for convenience.
 */
export { getIconComponent, getColorScheme, DEFAULT_ICON, DEFAULT_COLOR_SCHEME };

/**
 * Create a lookup map from utility types array.
 * @param {Array} utilityTypes - Array of utility type objects from backend
 * @returns {Object} Map of key => utility type object
 */
export const createUtilityTypeMap = (utilityTypes) => {
    if (!utilityTypes || !Array.isArray(utilityTypes)) {
        return {};
    }
    return utilityTypes.reduce((acc, type) => {
        acc[type.key] = type;
        return acc;
    }, {});
};

/**
 * Get a utility type by key from the array.
 * @param {Array} utilityTypes - Array of utility type objects
 * @param {string} key - Utility type key
 * @returns {Object|null} Utility type object or null
 */
export const findUtilityType = (utilityTypes, key) => {
    if (!utilityTypes || !Array.isArray(utilityTypes)) {
        return null;
    }
    return utilityTypes.find((type) => type.key === key) || null;
};

/**
 * Get the label for a utility type key.
 * @param {Array} utilityTypes - Array of utility type objects
 * @param {string} key - Utility type key
 * @returns {string} Label or key if not found
 */
export const getUtilityLabel = (utilityTypes, key) => {
    const type = findUtilityType(utilityTypes, key);
    return type?.label || key;
};

/**
 * Get the line color for charts based on color scheme.
 * Maps color scheme names to hex colors for Recharts.
 * @param {string} colorScheme - Color scheme name (e.g., 'blue', 'yellow')
 * @returns {string} Hex color value
 */
export const getLineColor = (colorScheme) => {
    const colorMap = {
        blue: '#3B82F6',
        yellow: '#EAB308',
        orange: '#F97316',
        red: '#EF4444',
        green: '#22C55E',
        teal: '#14B8A6',
        cyan: '#06B6D4',
        purple: '#A855F7',
        pink: '#EC4899',
        indigo: '#6366F1',
        gray: '#6B7280',
        slate: '#64748B',
    };
    return colorMap[colorScheme] || colorMap.slate;
};

/**
 * Get options for select dropdowns from utility types array.
 * @param {Array} utilityTypes - Array of utility type objects
 * @returns {Object} Map of key => label
 */
export const getUtilityTypeOptions = (utilityTypes) => {
    if (!utilityTypes || !Array.isArray(utilityTypes)) {
        return {};
    }
    return utilityTypes.reduce((acc, type) => {
        acc[type.key] = type.label;
        return acc;
    }, {});
};

export const formatCurrency = (value, options = {}) => {
    const { abbreviated = false, fallback = '-' } = options;

    if (value === null || value === undefined) return fallback;

    if (abbreviated && Math.abs(value) >= 1000) {
        return `$${(value / 1000).toFixed(0)}k`;
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

export const formatPercent = (value, options = {}) => {
    const { showSign = true, fallback = '-' } = options;

    if (value === null || value === undefined) return fallback;

    const sign = showSign && value > 0 ? '+' : '';
    return `${sign}${value.toFixed(1)}%`;
};

/**
 * Calculate heat map style for a value relative to the average.
 * Green = below average (lower costs = better)
 * Red = above average (higher costs = worse)
 * White = near average
 *
 * @param {number} value - The value to style
 * @param {number} average - The average value
 * @param {number} stdDev - Standard deviation
 * @returns {object} Inline style object with backgroundColor
 */
export const getHeatMapStyle = (value, average, stdDev) => {
    if (value === null || value === undefined || average === null || average === undefined) {
        return {};
    }

    // If stdDev is 0 or undefined, no variation - return no style
    if (!stdDev || stdDev === 0) {
        return {};
    }

    // Calculate how many standard deviations from the mean
    const zScore = (value - average) / stdDev;

    // Clamp zScore to [-2, 2] for reasonable color intensity
    const clampedZ = Math.max(-2, Math.min(2, zScore));

    // Calculate opacity - since clampedZ is in [-2, 2], this naturally maxes at 0.4
    const opacity = Math.abs(clampedZ) * 0.2;

    if (opacity < 0.05) {
        // Near average - no background
        return {};
    }

    // Green for below average (negative z-score = lower costs = good)
    // Red for above average (positive z-score = higher costs = bad)
    const color = clampedZ < 0
        ? `rgba(34, 197, 94, ${opacity})`  // green-500
        : `rgba(239, 68, 68, ${opacity})`; // red-500

    return { backgroundColor: color };
};

/**
 * Calculate statistics for heat map coloring
 * @param {Array} values - Array of numeric values
 * @returns {object} { average, stdDev, min, max }
 */
export const calculateHeatMapStats = (values) => {
    const validValues = values.filter((v) => v !== null && v !== undefined && !Number.isNaN(v));

    if (validValues.length === 0) {
        return { average: null, stdDev: null, min: null, max: null };
    }

    const sum = validValues.reduce((acc, v) => acc + v, 0);
    const average = sum / validValues.length;

    const squaredDiffs = validValues.map((v) => Math.pow(v - average, 2));
    const avgSquaredDiff = squaredDiffs.reduce((acc, v) => acc + v, 0) / validValues.length;
    // Note: When there's only one value, stdDev will be 0, which means getHeatMapStyle
    // will return no styling. This is intentional - a single property has no comparison basis.
    const stdDev = Math.sqrt(avgSquaredDiff);

    return {
        average,
        stdDev,
        min: Math.min(...validValues),
        max: Math.max(...validValues),
    };
};
