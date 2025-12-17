import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

interface StatsCardProps {
    title: string;
    value: number | string;
    subtitle?: string;
    icon: LucideIcon;
    iconClassName?: string;
    trend?: {
        value: number;
        isPositive: boolean;
    };
}

export function StatsCard({
    title,
    value,
    subtitle,
    icon: Icon,
    iconClassName,
    trend,
}: StatsCardProps) {
    return (
        <Card className="rounded-xl">
            <div className="flex items-center gap-4">
                <div
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-full',
                        iconClassName ?? 'bg-coolify-100 dark:bg-coolify-900/30'
                    )}
                >
                    <Icon
                        className={cn(
                            'size-6',
                            iconClassName
                                ? iconClassName.replace('bg-', 'text-').replace('-100', '-600')
                                : 'text-coolify-600 dark:text-coolify-400'
                        )}
                    />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</p>
                    <div className="flex items-baseline gap-2">
                        <p className="text-2xl font-bold text-gray-900 dark:text-white">{value}</p>
                        {trend && (
                            <span
                                className={cn(
                                    'text-sm font-medium',
                                    trend.isPositive
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                )}
                            >
                                {trend.isPositive ? '+' : ''}
                                {trend.value}%
                            </span>
                        )}
                    </div>
                    {subtitle && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>
                    )}
                </div>
            </div>
        </Card>
    );
}
