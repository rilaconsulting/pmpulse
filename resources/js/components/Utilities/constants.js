import {
    BoltIcon,
    FireIcon,
    BeakerIcon,
    TrashIcon,
    SparklesIcon,
    CubeIcon,
} from '@heroicons/react/24/outline';

export const UtilityIcons = {
    water: BeakerIcon,
    electric: BoltIcon,
    gas: FireIcon,
    garbage: TrashIcon,
    sewer: SparklesIcon,
    other: CubeIcon,
};

export const UtilityColors = {
    water: {
        bg: 'bg-blue-50',
        text: 'text-blue-600',
        border: 'border-blue-200',
        bar: 'bg-blue-400',
    },
    electric: {
        bg: 'bg-yellow-50',
        text: 'text-yellow-600',
        border: 'border-yellow-200',
        bar: 'bg-yellow-400',
    },
    gas: {
        bg: 'bg-orange-50',
        text: 'text-orange-600',
        border: 'border-orange-200',
        bar: 'bg-orange-400',
    },
    garbage: {
        bg: 'bg-gray-50',
        text: 'text-gray-600',
        border: 'border-gray-200',
        bar: 'bg-gray-400',
    },
    sewer: {
        bg: 'bg-green-50',
        text: 'text-green-600',
        border: 'border-green-200',
        bar: 'bg-green-400',
    },
    other: {
        bg: 'bg-purple-50',
        text: 'text-purple-600',
        border: 'border-purple-200',
        bar: 'bg-purple-400',
    },
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
