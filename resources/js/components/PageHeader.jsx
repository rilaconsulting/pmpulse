import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import Badge from './Badge';

/**
 * Unified page header component for consistent styling across all pages
 * Uses card styling to match other components in the application
 *
 * @param {string|ReactNode} title - Page title (required, can be string or React node)
 * @param {string} subtitle - Optional subtitle for list/dashboard pages
 * @param {Component} icon - Optional icon component (for entity headers like Vendor)
 * @param {string} iconBgColor - Icon background color class (default: bg-blue-100)
 * @param {string} iconColor - Icon color class (default: text-blue-600)
 * @param {string} backHref - Optional back navigation URL
 * @param {object} statusBadge - Optional status badge { label, variant }
 * @param {string[]} tags - Optional array of tag strings (like vendor trades)
 * @param {string} tagVariant - Tag color variant (default: blue)
 * @param {object[]} badges - Optional array of badges { label, variant, onRemove }
 * @param {string|ReactNode} secondaryInfo - Optional secondary info line
 * @param {ReactNode} actions - Optional right-side actions
 * @param {boolean} sticky - Whether header should be sticky (default: true)
 * @param {object[]} tabs - Optional tabs array { label, href, icon, id, count }
 * @param {string} activeTab - Active tab label or id for matching
 * @param {function} onTabChange - Optional callback for in-page tab switching (renders buttons instead of links)
 */
export default function PageHeader({
    title,
    subtitle,
    icon: Icon,
    iconBgColor = 'bg-blue-100',
    iconColor = 'text-blue-600',
    backHref,
    statusBadge,
    tags = [],
    tagVariant = 'blue',
    badges = [],
    secondaryInfo,
    actions,
    sticky = true,
    tabs = [],
    activeTab,
    onTabChange,
}) {
    const { url: currentUrl } = usePage();

    // Determine if a tab is active based on id, label, or href matching
    const isTabActive = (tab) => {
        if (activeTab) {
            // Match by id first (for in-page tabs), then by label
            return tab.id === activeTab || tab.label === activeTab;
        }
        // Fallback to URL matching (for navigation tabs)
        return tab.href && currentUrl.startsWith(tab.href);
    };

    // Card styling classes (matches .card in app.css)
    const cardClasses = 'bg-white rounded-xl shadow-sm border border-gray-200';

    // Sticky wrapper - wraps the card to make it sticky
    // Note: top-16 aligns with Layout.jsx header height (h-16 = 64px)
    const stickyWrapperClasses = sticky ? 'sticky top-16 z-20 pt-2 pb-4 relative' : '';

    const headerContent = (
        <div className={`${cardClasses} px-4 sm:px-6 py-4`}>
            {/* Mobile: Stack vertically. Desktop: Side by side */}
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
                {/* Left Side: Back + Icon + Title/Info */}
                <div className="flex items-start gap-3 sm:gap-4 min-w-0">
                    {/* Back Button */}
                    {backHref && (
                        <Link
                            href={backHref}
                            className="flex-shrink-0 p-2 -m-2 min-w-[44px] min-h-[44px] flex items-center justify-center text-gray-400 hover:text-gray-600 active:text-gray-800 rounded-lg hover:bg-gray-100 active:bg-gray-200 transition-colors"
                            title="Go back"
                            aria-label="Go back"
                        >
                            <ArrowLeftIcon className="w-5 h-5" aria-hidden="true" />
                        </Link>
                    )}

                    {/* Icon (for entity headers like Vendor) */}
                    {Icon && (
                        <div className={`flex-shrink-0 w-10 h-10 sm:w-12 sm:h-12 ${iconBgColor} rounded-lg flex items-center justify-center`}>
                            <Icon className={`w-5 h-5 sm:w-6 sm:h-6 ${iconColor}`} />
                        </div>
                    )}

                    {/* Title Section */}
                    <div className="min-w-0 flex-1">
                        {/* Title Row */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <h1 className="text-xl sm:text-2xl font-semibold text-gray-900">
                                {title}
                            </h1>
                            {statusBadge && (
                                <Badge
                                    label={statusBadge.label}
                                    variant={statusBadge.variant}
                                    size="sm"
                                    className="flex-shrink-0"
                                />
                            )}
                        </div>

                        {/* Subtitle (for list pages) */}
                        {subtitle && (
                            <p className="mt-1 text-sm text-gray-500">
                                {subtitle}
                            </p>
                        )}

                        {/* Tags (for vendor trades, etc.) */}
                        {tags.length > 0 && (
                            <div className="flex flex-wrap gap-2 mt-2">
                                {tags.map((tag) => (
                                    <Badge
                                        key={tag}
                                        label={tag}
                                        variant={tagVariant}
                                        size="sm"
                                    />
                                ))}
                            </div>
                        )}

                        {/* Secondary Info Line */}
                        {secondaryInfo && (
                            <div className="flex items-center flex-wrap gap-x-2 gap-y-1 text-sm text-gray-500 mt-1">
                                {typeof secondaryInfo === 'string' ? (
                                    <span>{secondaryInfo}</span>
                                ) : (
                                    secondaryInfo
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Side: Badges + Actions - stacks below title on mobile */}
                {(badges.length > 0 || actions) && (
                    <div className="flex items-center gap-2 flex-wrap sm:flex-shrink-0 sm:justify-end">
                        {/* Removable Badges (like property flags) */}
                        {badges.map((badge) => (
                            <Badge
                                key={badge.label}
                                label={badge.label}
                                variant={badge.variant}
                                size="sm"
                                onRemove={badge.onRemove}
                            />
                        ))}

                        {/* Actions */}
                        {actions}
                    </div>
                )}
            </div>

            {/* Tabs Section (if provided) - inside the card */}
            {tabs.length > 0 && (
                <div className="border-t border-gray-200 -mx-6 px-6 mt-4">
                    <nav
                        className="-mb-px flex space-x-2 sm:space-x-4 md:space-x-8 overflow-x-auto scrollbar-hide"
                        aria-label="Page navigation"
                        role={onTabChange ? 'tablist' : undefined}
                    >
                        {tabs.map((tab) => {
                            const active = isTabActive(tab);
                            const TabIcon = tab.icon;
                            const tabContent = (
                                <>
                                    {TabIcon && (
                                        <TabIcon
                                            className={`w-5 h-5 flex-shrink-0 sm:mr-2 transition-colors ${
                                                active ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'
                                            }`}
                                            aria-hidden="true"
                                        />
                                    )}
                                    <span className="hidden sm:inline">{tab.label}</span>
                                    <span className="sm:hidden sr-only">{tab.label}</span>
                                    {tab.count !== undefined && tab.count !== null && (
                                        <span
                                            className={`ml-1 sm:ml-2 px-1.5 py-0.5 text-xs rounded-full ${
                                                active
                                                    ? 'bg-blue-100 text-blue-600'
                                                    : 'bg-gray-100 text-gray-600 group-hover:bg-gray-200'
                                            }`}
                                        >
                                            {tab.count}
                                        </span>
                                    )}
                                </>
                            );

                            const tabClasses = `group flex items-center py-4 px-2 sm:px-1 border-b-2 font-medium text-sm transition-colors whitespace-nowrap flex-shrink-0 ${
                                active
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`;

                            // Render button for in-page tabs, Link for navigation tabs
                            if (onTabChange) {
                                return (
                                    <button
                                        key={tab.id || tab.label}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        aria-controls={tab.id ? `panel-${tab.id}` : undefined}
                                        onClick={() => onTabChange(tab.id || tab.label)}
                                        className={tabClasses}
                                    >
                                        {tabContent}
                                    </button>
                                );
                            }

                            return (
                                <Link
                                    key={tab.label}
                                    href={tab.href}
                                    className={tabClasses}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    {tabContent}
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            )}
        </div>
    );

    // Wrap in sticky container if needed
    if (sticky) {
        return (
            <div className={stickyWrapperClasses}>
                {/* Background layer that covers content scrolling underneath */}
                <div className="absolute inset-x-0 top-0 h-full bg-gray-50 -z-10" />
                {/* Fade effect at bottom to hide scroll edge */}
                <div className="absolute inset-x-0 bottom-0 h-4 bg-gradient-to-b from-gray-50 to-transparent -z-10 translate-y-full" />
                {headerContent}
            </div>
        );
    }

    return headerContent;
}
