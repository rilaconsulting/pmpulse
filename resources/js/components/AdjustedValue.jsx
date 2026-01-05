import { useState, useRef, useEffect } from 'react';
import { AdjustmentsHorizontalIcon } from '@heroicons/react/24/solid';

/**
 * AdjustedValue Component
 *
 * Displays a value with a visual indicator when it has been adjusted from its original.
 * Shows a tooltip with original and adjusted values on hover.
 *
 * @param {Object} props
 * @param {any} props.value - The value to display (effective value)
 * @param {boolean} props.isAdjusted - Whether this value has an active adjustment
 * @param {any} props.original - The original (unadjusted) value
 * @param {string} props.label - The field label (e.g., "Unit Count")
 * @param {Function} props.formatter - Optional formatter function for the value
 * @param {string} props.className - Additional CSS classes for the value
 * @param {'sm' | 'md' | 'lg'} props.size - Size variant for the indicator icon
 * @param {Object} props.adjustment - The full adjustment object (optional, for details)
 * @param {Function} props.onClick - Optional click handler for the indicator
 */
export default function AdjustedValue({
    value,
    isAdjusted,
    original,
    label,
    formatter,
    className = '',
    size = 'sm',
    adjustment,
    onClick,
}) {
    const [showTooltip, setShowTooltip] = useState(false);
    const [tooltipPosition, setTooltipPosition] = useState('bottom');
    const containerRef = useRef(null);
    const tooltipRef = useRef(null);

    // Format values for display
    const formatValue = (val) => {
        if (val === null || val === undefined) return '-';
        if (formatter) return formatter(val);
        if (typeof val === 'number') return val.toLocaleString();
        return String(val);
    };

    const displayValue = formatValue(value);
    const originalValue = formatValue(original);

    // Icon size classes
    const iconSizeClasses = {
        sm: 'w-3.5 h-3.5',
        md: 'w-4 h-4',
        lg: 'w-5 h-5',
    };

    // Adjust tooltip position based on viewport
    useEffect(() => {
        if (showTooltip && containerRef.current && tooltipRef.current) {
            const containerRect = containerRef.current.getBoundingClientRect();
            const tooltipRect = tooltipRef.current.getBoundingClientRect();

            // Check if tooltip would go off the bottom of the screen
            if (containerRect.bottom + tooltipRect.height > window.innerHeight) {
                setTooltipPosition('top');
            } else {
                setTooltipPosition('bottom');
            }
        }
    }, [showTooltip]);

    if (!isAdjusted) {
        return <span className={className}>{displayValue}</span>;
    }

    return (
        <span
            ref={containerRef}
            className={`inline-flex items-center gap-1 relative ${className}`}
            onMouseEnter={() => setShowTooltip(true)}
            onMouseLeave={() => setShowTooltip(false)}
        >
            {displayValue}
            <span
                className={`inline-flex items-center ${onClick ? 'cursor-pointer' : ''}`}
                onClick={onClick}
                title={onClick ? 'View adjustment details' : undefined}
            >
                <AdjustmentsHorizontalIcon
                    className={`${iconSizeClasses[size]} text-blue-500`}
                    aria-hidden="true"
                />
            </span>

            {/* Tooltip */}
            {showTooltip && (
                <div
                    ref={tooltipRef}
                    className={`absolute z-50 ${
                        tooltipPosition === 'top'
                            ? 'bottom-full mb-2'
                            : 'top-full mt-2'
                    } left-1/2 -translate-x-1/2 w-max max-w-xs`}
                    role="tooltip"
                >
                    <div className="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                        <div className="font-medium mb-1">{label} (Adjusted)</div>
                        <div className="space-y-1">
                            <div className="flex justify-between gap-4">
                                <span className="text-gray-400">Original:</span>
                                <span className="text-gray-300 line-through">{originalValue}</span>
                            </div>
                            <div className="flex justify-between gap-4">
                                <span className="text-gray-400">Adjusted:</span>
                                <span className="text-white font-medium">{displayValue}</span>
                            </div>
                            {adjustment?.reason && (
                                <div className="mt-2 pt-2 border-t border-gray-700 text-gray-400">
                                    <span className="italic">"{adjustment.reason}"</span>
                                </div>
                            )}
                        </div>
                        {/* Arrow */}
                        <div
                            className={`absolute left-1/2 -translate-x-1/2 ${
                                tooltipPosition === 'top'
                                    ? 'top-full border-t-gray-900 border-t-8 border-x-8 border-x-transparent'
                                    : 'bottom-full border-b-gray-900 border-b-8 border-x-8 border-x-transparent'
                            }`}
                        />
                    </div>
                </div>
            )}
        </span>
    );
}

/**
 * Simplified wrapper for common use cases.
 * Pass effectiveValue object from getEffectiveValuesWithMetadata.
 */
export function AdjustedValueFromMeta({ effectiveValue, formatter, className, size, onClick }) {
    if (!effectiveValue) return null;

    return (
        <AdjustedValue
            value={effectiveValue.value}
            isAdjusted={effectiveValue.is_adjusted}
            original={effectiveValue.original}
            label={effectiveValue.label}
            adjustment={effectiveValue.adjustment}
            formatter={formatter}
            className={className}
            size={size}
            onClick={onClick}
        />
    );
}
