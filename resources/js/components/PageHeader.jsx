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
 * @param {boolean} sticky - Whether header should be sticky (default: false)
 * @param {object[]} tabs - Optional tabs array { label, href, icon }
 * @param {string} activeTab - Active tab label for matching
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
    sticky = false,
    tabs = [],
    activeTab,
}) {
    const { url: currentUrl } = usePage();

    // Determine if a tab is active based on href matching or explicit activeTab
    const isTabActive = (tab) => {
        if (activeTab) {
            return tab.label === activeTab;
        }
        // Fallback to URL matching
        return currentUrl.startsWith(tab.href);
    };

    // Card styling classes (matches .card in app.css)
    const cardClasses = 'bg-white rounded-xl shadow-sm border border-gray-200';

    // Sticky wrapper - wraps the card to make it sticky
    // Note: top-16 aligns with Layout.jsx header height (h-16 = 64px)
    // Added pt-2 pb-4 for visual separation when sticky, bg-gray-50 matches page background
    const stickyWrapperClasses = sticky ? 'sticky top-16 z-20 pt-2 pb-4 bg-gray-50' : '';

    const headerContent = (
        <div className={`${cardClasses} px-6 py-4`}>
            <div className="flex items-start justify-between gap-4">
                {/* Left Side: Back + Icon + Title/Info */}
                <div className="flex items-start gap-4 min-w-0">
                    {/* Back Button */}
                    {backHref && (
                        <Link
                            href={backHref}
                            className="flex-shrink-0 p-2 -m-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors mt-0.5"
                            title="Go back"
                            aria-label="Go back"
                        >
                            <ArrowLeftIcon className="w-5 h-5" aria-hidden="true" />
                        </Link>
                    )}

                    {/* Icon (for entity headers like Vendor) */}
                    {Icon && (
                        <div className={`flex-shrink-0 w-12 h-12 ${iconBgColor} rounded-lg flex items-center justify-center`}>
                            <Icon className={`w-6 h-6 ${iconColor}`} />
                        </div>
                    )}

                    {/* Title Section */}
                    <div className="min-w-0 flex-1">
                        {/* Title Row */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <h1 className="text-2xl font-semibold text-gray-900 truncate">
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
                            <div className="flex items-center gap-2 text-sm text-gray-500 mt-1">
                                {typeof secondaryInfo === 'string' ? (
                                    <span className="truncate">{secondaryInfo}</span>
                                ) : (
                                    secondaryInfo
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Side: Badges + Actions */}
                <div className="flex items-center gap-2 flex-shrink-0 flex-wrap justify-end">
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
            </div>

            {/* Tabs Section (if provided) - inside the card */}
            {tabs.length > 0 && (
                <div className="border-t border-gray-200 -mx-6 px-6 mt-4">
                    <nav className="-mb-px flex space-x-8" aria-label="Page navigation">
                        {tabs.map((tab) => {
                            const active = isTabActive(tab);
                            const TabIcon = tab.icon;
                            return (
                                <Link
                                    key={tab.label}
                                    href={tab.href}
                                    className={`group flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                                        active
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    {TabIcon && (
                                        <TabIcon
                                            className={`w-5 h-5 mr-2 transition-colors ${
                                                active ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'
                                            }`}
                                        />
                                    )}
                                    <span>{tab.label}</span>
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
                {headerContent}
            </div>
        );
    }

    return headerContent;
}
