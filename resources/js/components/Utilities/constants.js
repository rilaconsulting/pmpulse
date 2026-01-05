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
