import { useState, useEffect } from 'react';

/**
 * ResponsiveTable - A wrapper component that handles switching between
 * table and card views based on screen size.
 *
 * @param {Object} props
 * @param {React.ReactNode} props.children - Table content for desktop view
 * @param {Array} props.data - Data array for rendering cards
 * @param {Function} props.renderCard - Function to render each card (receives item, index)
 * @param {boolean} props.cardView - Use card view on mobile (default: true)
 * @param {boolean} props.stickyColumn - Use sticky first column instead of cards (default: false)
 * @param {string} props.breakpoint - Switch point: 'sm'|'md'|'lg' (default: 'md')
 * @param {string} props.className - Additional classes for container
 * @param {string} props.emptyMessage - Message when data is empty
 * @param {React.ReactNode} props.emptyIcon - Icon component for empty state
 */
export default function ResponsiveTable({
    children,
    data = [],
    renderCard,
    cardView = true,
    stickyColumn = false,
    breakpoint = 'md',
    className = '',
    emptyMessage = 'No data available',
    emptyIcon,
}) {
    const [isMobile, setIsMobile] = useState(false);

    // Breakpoint pixel values matching Tailwind
    const breakpoints = {
        sm: 640,
        md: 768,
        lg: 1024,
    };

    useEffect(() => {
        const checkMobile = () => {
            setIsMobile(window.innerWidth < breakpoints[breakpoint]);
        };

        // Check on mount
        checkMobile();

        // Listen for resize
        window.addEventListener('resize', checkMobile);
        return () => window.removeEventListener('resize', checkMobile);
    }, [breakpoint]);

    // Empty state
    if (data.length === 0) {
        return (
            <div className={`card ${className}`}>
                <div className="px-6 py-12 text-center">
                    {emptyIcon && (
                        <div className="w-12 h-12 text-gray-300 mx-auto mb-3">
                            {emptyIcon}
                        </div>
                    )}
                    <p className="text-gray-500">{emptyMessage}</p>
                </div>
            </div>
        );
    }

    // Mobile view
    if (isMobile) {
        // Card view mode
        if (cardView && renderCard) {
            return (
                <div className={`space-y-4 ${className}`}>
                    {data.map((item, index) => renderCard(item, index))}
                </div>
            );
        }

        // Sticky column mode - horizontal scroll with sticky first column
        if (stickyColumn) {
            return (
                <div className={`card overflow-hidden ${className}`}>
                    <div className="overflow-x-auto">
                        <div className="min-w-max">
                            {children}
                        </div>
                    </div>
                </div>
            );
        }

        // Default: just horizontal scroll
        return (
            <div className={`card overflow-hidden ${className}`}>
                <div className="overflow-x-auto scrollbar-hide">
                    {children}
                </div>
            </div>
        );
    }

    // Desktop view - render table as-is
    return (
        <div className={`card overflow-hidden ${className}`}>
            {children}
        </div>
    );
}

/**
 * Helper component for sticky column tables
 * Wrap the first <td> or <th> in each row with this
 */
export function StickyColumn({ children, isHeader = false, className = '' }) {
    const baseClasses = 'sticky left-0 z-10 bg-white';
    const headerClasses = isHeader ? 'bg-gray-50' : '';

    return (
        <div className={`${baseClasses} ${headerClasses} ${className}`}>
            {children}
        </div>
    );
}
