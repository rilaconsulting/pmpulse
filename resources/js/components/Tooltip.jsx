import { useState, useRef, useEffect } from 'react';

/**
 * Tooltip Component
 *
 * A reusable tooltip that appears on hover with smart positioning.
 *
 * @param {Object} props
 * @param {React.ReactNode} props.children - The element that triggers the tooltip
 * @param {string|React.ReactNode} props.content - The tooltip content
 * @param {'top' | 'bottom' | 'auto'} props.position - Tooltip position (default: auto)
 * @param {string} props.className - Additional classes for the wrapper
 */
export default function Tooltip({
    children,
    content,
    position = 'auto',
    className = ''
}) {
    const [showTooltip, setShowTooltip] = useState(false);
    const [tooltipPosition, setTooltipPosition] = useState('bottom');
    const containerRef = useRef(null);
    const tooltipRef = useRef(null);

    // Adjust tooltip position based on viewport
    useEffect(() => {
        if (showTooltip && containerRef.current && tooltipRef.current && position === 'auto') {
            const containerRect = containerRef.current.getBoundingClientRect();
            const tooltipRect = tooltipRef.current.getBoundingClientRect();

            // Check if tooltip would go off the bottom of the screen
            if (containerRect.bottom + tooltipRect.height + 8 > window.innerHeight) {
                setTooltipPosition('top');
            } else {
                setTooltipPosition('bottom');
            }
        } else if (position !== 'auto') {
            setTooltipPosition(position);
        }
    }, [showTooltip, position]);

    if (!content) {
        return children;
    }

    return (
        <span
            ref={containerRef}
            className={`relative inline-flex ${className}`}
            onMouseEnter={() => setShowTooltip(true)}
            onMouseLeave={() => setShowTooltip(false)}
            onFocus={() => setShowTooltip(true)}
            onBlur={() => setShowTooltip(false)}
        >
            {children}

            {/* Tooltip */}
            {showTooltip && (
                <div
                    ref={tooltipRef}
                    className={`absolute z-50 ${
                        tooltipPosition === 'top'
                            ? 'bottom-full mb-2'
                            : 'top-full mt-2'
                    } left-1/2 -translate-x-1/2 w-max max-w-xs pointer-events-none`}
                    role="tooltip"
                >
                    <div className="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                        {content}
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
