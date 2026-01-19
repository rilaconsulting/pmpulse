import { getIconComponent, getColorScheme, DEFAULT_ICON, DEFAULT_COLOR_SCHEME } from './availableIcons';

/**
 * Size configurations for the icon
 */
const sizeClasses = {
    xs: 'h-4 w-4',
    sm: 'h-5 w-5',
    md: 'h-6 w-6',
    lg: 'h-8 w-8',
    xl: 'h-10 w-10',
};

const badgeSizeClasses = {
    xs: 'p-1',
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-2.5',
    xl: 'p-3',
};

/**
 * UtilityTypeIcon - Renders a utility type icon with optional badge styling.
 *
 * @param {Object} props
 * @param {Object} props.utilityType - The utility type object with icon and color_scheme
 * @param {string} props.utilityType.icon - Icon name (e.g., 'BeakerIcon')
 * @param {string} props.utilityType.color_scheme - Color scheme name (e.g., 'blue')
 * @param {string} [props.size='md'] - Size: 'xs', 'sm', 'md', 'lg', 'xl'
 * @param {boolean} [props.showBadge=true] - Whether to show colored badge background
 * @param {string} [props.className=''] - Additional CSS classes
 */
export default function UtilityTypeIcon({
    utilityType,
    size = 'md',
    showBadge = true,
    className = '',
}) {
    const iconName = utilityType?.icon || DEFAULT_ICON;
    const colorSchemeName = utilityType?.color_scheme || DEFAULT_COLOR_SCHEME;

    const IconComponent = getIconComponent(iconName);
    const colorScheme = getColorScheme(colorSchemeName);

    const iconSizeClass = sizeClasses[size] || sizeClasses.md;
    const badgeSizeClass = badgeSizeClasses[size] || badgeSizeClasses.md;

    if (showBadge) {
        return (
            <span
                className={`inline-flex items-center justify-center rounded-lg ${colorScheme.bg} ${badgeSizeClass} ${className}`}
            >
                <IconComponent className={`${iconSizeClass} ${colorScheme.text}`} aria-hidden="true" />
            </span>
        );
    }

    return (
        <IconComponent
            className={`${iconSizeClass} ${colorScheme.text} ${className}`}
            aria-hidden="true"
        />
    );
}

/**
 * UtilityTypeIconInline - Renders a utility type icon inline with text.
 * Useful for labels and table cells.
 *
 * @param {Object} props
 * @param {Object} props.utilityType - The utility type object
 * @param {string} [props.size='sm'] - Size of the icon
 * @param {string} [props.className=''] - Additional CSS classes
 */
export function UtilityTypeIconInline({ utilityType, size = 'sm', className = '' }) {
    return <UtilityTypeIcon utilityType={utilityType} size={size} showBadge={false} className={className} />;
}

/**
 * UtilityTypeBadge - Renders a utility type with icon and label in a badge.
 *
 * @param {Object} props
 * @param {Object} props.utilityType - The utility type object
 * @param {string} props.utilityType.label - Display label for the utility type
 * @param {string} [props.size='sm'] - Size of the badge
 * @param {string} [props.className=''] - Additional CSS classes
 */
export function UtilityTypeBadge({ utilityType, size = 'sm', className = '' }) {
    const colorSchemeName = utilityType?.color_scheme || DEFAULT_COLOR_SCHEME;
    const colorScheme = getColorScheme(colorSchemeName);
    const IconComponent = getIconComponent(utilityType?.icon || DEFAULT_ICON);
    const iconSizeClass = sizeClasses[size] || sizeClasses.sm;

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${colorScheme.badge} ${className}`}
        >
            <IconComponent className={iconSizeClass} aria-hidden="true" />
            {utilityType?.label || 'Unknown'}
        </span>
    );
}
