/**
 * MobileCard - A reusable card component for displaying table row data on mobile.
 *
 * @param {Object} props
 * @param {React.ReactNode} props.header - Header content (title, name, etc.)
 * @param {React.ReactNode} props.subheader - Optional subheader content
 * @param {Array<{label: string, variant?: string}>} props.badges - Status badges
 * @param {Array<{label: string, value: React.ReactNode}>} props.fields - Field label/value pairs
 * @param {React.ReactNode} props.actions - Action buttons
 * @param {React.ReactNode} props.icon - Optional icon to display on left
 * @param {Function} props.onClick - Click handler for entire card
 * @param {string} props.className - Additional classes
 */
export default function MobileCard({
    header,
    subheader,
    badges = [],
    fields = [],
    actions,
    icon,
    onClick,
    className = '',
}) {
    const CardWrapper = onClick ? 'button' : 'div';

    const badgeVariants = {
        success: 'bg-green-100 text-green-800',
        warning: 'bg-yellow-100 text-yellow-800',
        danger: 'bg-red-100 text-red-800',
        info: 'bg-blue-100 text-blue-800',
        neutral: 'bg-gray-100 text-gray-800',
        default: 'bg-gray-100 text-gray-800',
    };

    return (
        <CardWrapper
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={`card w-full text-left ${onClick ? 'hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors' : ''} ${className}`}
        >
            <div className="p-4">
                {/* Header section */}
                <div className="flex items-start gap-3">
                    {/* Optional icon */}
                    {icon && (
                        <div className="flex-shrink-0">
                            {icon}
                        </div>
                    )}

                    <div className="flex-1 min-w-0">
                        {/* Header and subheader */}
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                {header && (
                                    <div className="font-medium text-gray-900 truncate">
                                        {header}
                                    </div>
                                )}
                                {subheader && (
                                    <div className="text-sm text-gray-500 truncate mt-0.5">
                                        {subheader}
                                    </div>
                                )}
                            </div>

                            {/* Badges - right aligned */}
                            {badges.length > 0 && (
                                <div className="flex flex-wrap gap-1 flex-shrink-0">
                                    {badges.map((badge, index) => {
                                        const label = typeof badge === 'string' ? badge : badge.label;
                                        const variant = typeof badge === 'string' ? 'default' : (badge.variant || 'default');
                                        return (
                                            <span
                                                key={index}
                                                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeVariants[variant]}`}
                                            >
                                                {label}
                                            </span>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Fields */}
                        {fields.length > 0 && (
                            <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                {fields.map((field, index) => (
                                    <div key={index} className={field.fullWidth ? 'col-span-2' : ''}>
                                        <dt className="text-gray-500 text-xs">{field.label}</dt>
                                        <dd className="text-gray-900 mt-0.5 truncate">{field.value ?? '-'}</dd>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Actions */}
                {actions && (
                    <div className="mt-4 pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </CardWrapper>
    );
}

/**
 * Convenience component for a simple field row in cards
 */
export function CardField({ label, value, className = '' }) {
    return (
        <div className={`text-sm ${className}`}>
            <span className="text-gray-500">{label}: </span>
            <span className="text-gray-900">{value ?? '-'}</span>
        </div>
    );
}
