/**
 * StatsGrid - A responsive grid container for stat cards.
 * Shows 2 columns on mobile, 4 on desktop.
 *
 * @param {Object} props
 * @param {React.ReactNode} props.children - StatCard components
 * @param {number} props.columns - Number of columns on desktop (default: 4)
 * @param {string} props.className - Additional classes
 */
export default function StatsGrid({ children, columns = 4, className = '' }) {
    const columnClasses = {
        2: 'md:grid-cols-2',
        3: 'md:grid-cols-3',
        4: 'md:grid-cols-4',
        5: 'md:grid-cols-5',
        6: 'md:grid-cols-6',
    };

    return (
        <div className={`grid grid-cols-2 ${columnClasses[columns] || 'md:grid-cols-4'} gap-3 md:gap-4 ${className}`}>
            {children}
        </div>
    );
}
