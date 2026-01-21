import { useRef } from 'react';

/**
 * PropertyTabs - Tab navigation component for property detail pages
 *
 * @param {Object} props
 * @param {Array} props.tabs - Array of tab objects with { id, label, count?, icon? }
 * @param {string} props.activeTab - Currently active tab id
 * @param {function} props.onTabChange - Callback when tab changes
 * @param {string} props.className - Additional CSS classes
 */
export default function PropertyTabs({ tabs, activeTab, onTabChange, className = '' }) {
    const tabRefs = useRef({});

    // Handle keyboard navigation
    const handleKeyDown = (e, currentIndex) => {
        let newIndex = currentIndex;

        if (e.key === 'ArrowRight') {
            e.preventDefault();
            newIndex = currentIndex === tabs.length - 1 ? 0 : currentIndex + 1;
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            newIndex = currentIndex === 0 ? tabs.length - 1 : currentIndex - 1;
        } else if (e.key === 'Home') {
            e.preventDefault();
            newIndex = 0;
        } else if (e.key === 'End') {
            e.preventDefault();
            newIndex = tabs.length - 1;
        }

        if (newIndex !== currentIndex) {
            const newTab = tabs[newIndex];
            onTabChange(newTab.id);
            // Focus the new tab
            tabRefs.current[newTab.id]?.focus();
        }
    };

    return (
        <div className={`border-b border-gray-200 pt-2 pb-2 ${className}`}>
            <nav
                className="flex space-x-1"
                role="tablist"
                aria-label="Property sections"
            >
                {tabs.map((tab, index) => {
                    const isActive = activeTab === tab.id;
                    const Icon = tab.icon;

                    return (
                        <button
                            key={tab.id}
                            ref={(el) => (tabRefs.current[tab.id] = el)}
                            type="button"
                            role="tab"
                            id={`tab-${tab.id}`}
                            aria-selected={isActive}
                            aria-controls={`panel-${tab.id}`}
                            tabIndex={isActive ? 0 : -1}
                            onClick={() => onTabChange(tab.id)}
                            onKeyDown={(e) => handleKeyDown(e, index)}
                            className={`group px-3 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors ${
                                isActive
                                    ? 'bg-blue-50 text-blue-600'
                                    : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                            }`}
                        >
                            <span className="flex items-center gap-2">
                                {Icon && (
                                    <Icon
                                        className={`w-4 h-4 ${
                                            isActive ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'
                                        }`}
                                        aria-hidden="true"
                                    />
                                )}
                                {tab.label}
                                {tab.count !== undefined && tab.count !== null && (
                                    <span
                                        className={`ml-1 px-1.5 py-0.5 text-xs rounded-full ${
                                            isActive
                                                ? 'bg-blue-100 text-blue-600'
                                                : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200'
                                        }`}
                                    >
                                        {tab.count}
                                    </span>
                                )}
                            </span>
                        </button>
                    );
                })}
            </nav>
        </div>
    );
}

/**
 * PropertyTabPanel - Container for tab content
 *
 * @param {Object} props
 * @param {string} props.id - Tab id this panel belongs to
 * @param {boolean} props.isActive - Whether this panel is currently visible
 * @param {React.ReactNode} props.children - Panel content
 * @param {string} props.className - Additional CSS classes
 */
export function PropertyTabPanel({ id, isActive, children, className = '' }) {
    if (!isActive) return null;

    return (
        <div
            role="tabpanel"
            id={`panel-${id}`}
            aria-labelledby={`tab-${id}`}
            tabIndex={0}
            className={className}
        >
            {children}
        </div>
    );
}
