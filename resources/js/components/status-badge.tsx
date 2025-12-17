import { Badge, type BadgeProps } from '@/components/ui/badge';
import type { ApplicationStatus, DeploymentStatus } from '@/types';

interface StatusBadgeProps extends Omit<BadgeProps, 'variant'> {
    status: ApplicationStatus | DeploymentStatus | string;
}

function getStatusVariant(
    status: string
): 'success' | 'warning' | 'error' | 'info' | 'default' {
    const statusLower = status.toLowerCase();

    if (['running', 'finished', 'success', 'healthy'].includes(statusLower)) {
        return 'success';
    }
    if (['starting', 'stopping', 'restarting', 'building', 'queued', 'in_progress', 'pending'].includes(statusLower)) {
        return 'warning';
    }
    if (['stopped', 'failed', 'error', 'cancelled', 'degraded', 'unhealthy'].includes(statusLower)) {
        return 'error';
    }
    return 'default';
}

function formatStatus(status: string): string {
    return status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function StatusBadge({ status, className, ...props }: StatusBadgeProps) {
    return (
        <Badge variant={getStatusVariant(status)} className={className} {...props}>
            {formatStatus(status)}
        </Badge>
    );
}
