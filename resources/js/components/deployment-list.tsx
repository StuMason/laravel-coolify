import { Link } from '@inertiajs/react';
import { GitCommit, Clock } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/status-badge';
import { formatRelativeTime, formatDuration } from '@/lib/utils';
import type { Deployment } from '@/types';

interface DeploymentListProps {
    deployments: Deployment[];
    applicationUuid?: string;
    showApplication?: boolean;
}

export function DeploymentList({
    deployments,
    showApplication = false,
}: DeploymentListProps) {
    if (deployments.length === 0) {
        return (
            <Card>
                <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                    No deployments found
                </div>
            </Card>
        );
    }

    return (
        <div className="space-y-3">
            {deployments.map((deployment) => {
                const deploymentUuid = deployment.deployment_uuid ?? deployment.uuid;
                const duration =
                    deployment.started_at && deployment.finished_at
                        ? Math.floor(
                              (new Date(deployment.finished_at).getTime() -
                                  new Date(deployment.started_at).getTime()) /
                                  1000
                          )
                        : null;

                return (
                    <Card key={deploymentUuid} className="p-4">
                        <div className="flex items-center justify-between">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-3">
                                    <Link
                                        href={`/coolify/deployments/${deploymentUuid}`}
                                        className="font-medium text-gray-900 hover:text-coolify-600 dark:text-white dark:hover:text-coolify-400"
                                    >
                                        {deploymentUuid.slice(0, 8)}
                                    </Link>
                                    <StatusBadge status={deployment.status} />
                                </div>

                                <div className="mt-2 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    {deployment.commit_sha && (
                                        <span className="flex items-center gap-1">
                                            <GitCommit className="size-4" />
                                            <span className="font-mono">
                                                {deployment.commit_sha.slice(0, 7)}
                                            </span>
                                        </span>
                                    )}
                                    {deployment.commit_message && (
                                        <span className="truncate max-w-[300px]">
                                            {deployment.commit_message}
                                        </span>
                                    )}
                                </div>
                            </div>

                            <div className="ml-4 flex shrink-0 flex-col items-end gap-1 text-sm text-gray-500 dark:text-gray-400">
                                {deployment.started_at && (
                                    <span>{formatRelativeTime(deployment.started_at)}</span>
                                )}
                                {duration !== null && (
                                    <span className="flex items-center gap-1">
                                        <Clock className="size-3" />
                                        {formatDuration(duration)}
                                    </span>
                                )}
                            </div>
                        </div>
                    </Card>
                );
            })}
        </div>
    );
}
