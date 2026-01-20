import { XMarkIcon } from '@heroicons/react/20/solid';

const variantStyles = {
    success: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    danger: 'bg-red-100 text-red-800',
    neutral: 'bg-gray-100 text-gray-600',
    blue: 'bg-blue-100 text-blue-800',
};

const sizeStyles = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-sm',
};

/**
 * Reusable badge component with variants and optional remove button
 */
export default function Badge({
    label,
    variant = 'neutral',
    size = 'sm',
    onRemove,
    className = '',
}) {
    const baseClasses = 'inline-flex items-center rounded-full font-medium';
    const variantClasses = variantStyles[variant] || variantStyles.neutral;
    const sizeClasses = sizeStyles[size] || sizeStyles.sm;

    return (
        <span className={`${baseClasses} ${variantClasses} ${sizeClasses} ${className}`}>
            {label}
            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    className="ml-1 -mr-0.5 inline-flex items-center justify-center rounded-full p-0.5 hover:bg-black/10 focus:outline-none focus:ring-2 focus:ring-offset-1"
                    aria-label={`Remove ${label}`}
                >
                    <XMarkIcon className="h-3 w-3" />
                </button>
            )}
        </span>
    );
}
