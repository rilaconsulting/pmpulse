import {
    BeakerIcon,
    BoltIcon,
    FireIcon,
    TrashIcon,
    SparklesIcon,
    CubeIcon,
    CloudIcon,
    SunIcon,
    MoonIcon,
    HomeIcon,
    BuildingOfficeIcon,
    WrenchIcon,
    CogIcon,
    SignalIcon,
    WifiIcon,
    PhoneIcon,
    TruckIcon,
    ArrowPathIcon,
    ShieldCheckIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

/**
 * Available icons for utility type selection.
 * Each icon has a component reference and a display name.
 */
export const AvailableUtilityIcons = {
    BeakerIcon: { name: 'Beaker', component: BeakerIcon },
    BoltIcon: { name: 'Lightning', component: BoltIcon },
    FireIcon: { name: 'Fire', component: FireIcon },
    TrashIcon: { name: 'Trash', component: TrashIcon },
    SparklesIcon: { name: 'Sparkles', component: SparklesIcon },
    CubeIcon: { name: 'Box', component: CubeIcon },
    CloudIcon: { name: 'Cloud', component: CloudIcon },
    SunIcon: { name: 'Sun', component: SunIcon },
    MoonIcon: { name: 'Moon', component: MoonIcon },
    HomeIcon: { name: 'Home', component: HomeIcon },
    BuildingOfficeIcon: { name: 'Building', component: BuildingOfficeIcon },
    WrenchIcon: { name: 'Wrench', component: WrenchIcon },
    CogIcon: { name: 'Cog', component: CogIcon },
    SignalIcon: { name: 'Signal', component: SignalIcon },
    WifiIcon: { name: 'WiFi', component: WifiIcon },
    PhoneIcon: { name: 'Phone', component: PhoneIcon },
    TruckIcon: { name: 'Truck', component: TruckIcon },
    ArrowPathIcon: { name: 'Recycle', component: ArrowPathIcon },
    ShieldCheckIcon: { name: 'Shield', component: ShieldCheckIcon },
    ExclamationTriangleIcon: { name: 'Warning', component: ExclamationTriangleIcon },
};

/**
 * Get the icon component for a given icon name.
 * Falls back to CubeIcon if not found.
 */
export const getIconComponent = (iconName) => {
    return AvailableUtilityIcons[iconName]?.component || CubeIcon;
};

/**
 * Available color schemes for utility types.
 * Each scheme includes all the Tailwind classes needed for styling.
 */
export const AvailableColorSchemes = {
    blue: {
        name: 'Blue',
        bg: 'bg-blue-50',
        text: 'text-blue-600',
        border: 'border-blue-200',
        bar: 'bg-blue-400',
        badge: 'bg-blue-100 text-blue-700',
        preview: 'bg-blue-500',
    },
    yellow: {
        name: 'Yellow',
        bg: 'bg-yellow-50',
        text: 'text-yellow-600',
        border: 'border-yellow-200',
        bar: 'bg-yellow-400',
        badge: 'bg-yellow-100 text-yellow-700',
        preview: 'bg-yellow-500',
    },
    orange: {
        name: 'Orange',
        bg: 'bg-orange-50',
        text: 'text-orange-600',
        border: 'border-orange-200',
        bar: 'bg-orange-400',
        badge: 'bg-orange-100 text-orange-700',
        preview: 'bg-orange-500',
    },
    red: {
        name: 'Red',
        bg: 'bg-red-50',
        text: 'text-red-600',
        border: 'border-red-200',
        bar: 'bg-red-400',
        badge: 'bg-red-100 text-red-700',
        preview: 'bg-red-500',
    },
    green: {
        name: 'Green',
        bg: 'bg-green-50',
        text: 'text-green-600',
        border: 'border-green-200',
        bar: 'bg-green-400',
        badge: 'bg-green-100 text-green-700',
        preview: 'bg-green-500',
    },
    teal: {
        name: 'Teal',
        bg: 'bg-teal-50',
        text: 'text-teal-600',
        border: 'border-teal-200',
        bar: 'bg-teal-400',
        badge: 'bg-teal-100 text-teal-700',
        preview: 'bg-teal-500',
    },
    cyan: {
        name: 'Cyan',
        bg: 'bg-cyan-50',
        text: 'text-cyan-600',
        border: 'border-cyan-200',
        bar: 'bg-cyan-400',
        badge: 'bg-cyan-100 text-cyan-700',
        preview: 'bg-cyan-500',
    },
    purple: {
        name: 'Purple',
        bg: 'bg-purple-50',
        text: 'text-purple-600',
        border: 'border-purple-200',
        bar: 'bg-purple-400',
        badge: 'bg-purple-100 text-purple-700',
        preview: 'bg-purple-500',
    },
    pink: {
        name: 'Pink',
        bg: 'bg-pink-50',
        text: 'text-pink-600',
        border: 'border-pink-200',
        bar: 'bg-pink-400',
        badge: 'bg-pink-100 text-pink-700',
        preview: 'bg-pink-500',
    },
    indigo: {
        name: 'Indigo',
        bg: 'bg-indigo-50',
        text: 'text-indigo-600',
        border: 'border-indigo-200',
        bar: 'bg-indigo-400',
        badge: 'bg-indigo-100 text-indigo-700',
        preview: 'bg-indigo-500',
    },
    gray: {
        name: 'Gray',
        bg: 'bg-gray-50',
        text: 'text-gray-600',
        border: 'border-gray-200',
        bar: 'bg-gray-400',
        badge: 'bg-gray-100 text-gray-700',
        preview: 'bg-gray-500',
    },
    slate: {
        name: 'Slate',
        bg: 'bg-slate-50',
        text: 'text-slate-600',
        border: 'border-slate-200',
        bar: 'bg-slate-400',
        badge: 'bg-slate-100 text-slate-700',
        preview: 'bg-slate-500',
    },
};

/**
 * Get the color scheme for a given scheme name.
 * Falls back to slate if not found.
 */
export const getColorScheme = (schemeName) => {
    return AvailableColorSchemes[schemeName] || AvailableColorSchemes.slate;
};

/**
 * Default icon for utility types that don't have a configured icon.
 */
export const DEFAULT_ICON = 'CubeIcon';

/**
 * Default color scheme for utility types that don't have a configured color.
 */
export const DEFAULT_COLOR_SCHEME = 'slate';
