import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { ArrowLeft, GitCommit, Clock, XCircle } from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import { LogViewer } from '@/components/log-viewer';
import { formatDate, formatDuration, formatRelativeTime } from '@/lib/utils';
import type { Deployment, Application } from '@/types';

interface DeploymentShowProps {
    deployment: Deployment;
    application: Application;
    logs: string;
    pollingInterval: number;
}

export default function DeploymentShow({
    deployment,
    application,
    logs,
    pollingInterval,
}: DeploymentShowProps) {
    const isInProgress =
        deployment.status === 'queued' || deployment.status === 'in_progress';

    useEffect(() => {
        if (!isInProgress || pollingInterval <= 0) return;

        const interval = setInterval(() => {
            router.reload({ only: ['deployment', 'logs'] });
        }, pollingInterval * 1000);

        return () => clearInterval(interval);
    }, [isInProgress, pollingInterval]);

    const handleCancel = () => {
        router.post(`/coolify/api/deployments/${deployment.uuid}/cancel`, {}, {
            preserveScroll: true,
        });
    };

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
        <CoolifyLayout>
            <Head title={`Deployment ${deploymentUuid.slice(0, 8)}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link
                            href={`/coolify/applications/${application.uuid}`}
                            className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="size-4" />
                            Back to {application.name}
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                Deployment {deploymentUuid.slice(0, 8)}
                            </h1>
                            <StatusBadge status={deployment.status} />
                        </div>
                        <p className="mt-1 text-gray-500 dark:text-gray-400">
                            Deploying{' '}
                            <Link
                                href={`/coolify/applications/${application.uuid}`}
                                className="text-coolify-600 hover:underline dark:text-coolify-400"
                            >
                                {application.name}
                            </Link>
                        </p>
                    </div>

                    {isInProgress && (
                        <Button variant="destructive" onClick={handleCancel}>
                            <XCircle className="size-4" />
                            Cancel Deployment
                        </Button>
                    )}
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Details
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">UUID</dt>
                                <dd className="font-mono text-gray-900 dark:text-white">
                                    {deploymentUuid.slice(0, 8)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Status</dt>
                                <dd>
                                    <StatusBadge status={deployment.status} />
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatRelativeTime(deployment.created_at)}
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Commit
                        </h3>
                        <dl className="space-y-3 text-sm">
                            {deployment.commit_sha ? (
                                <>
                                    <div className="flex items-center gap-2 text-gray-900 dark:text-white">
                                        <GitCommit className="size-4 text-gray-400" />
                                        <span className="font-mono">
                                            {deployment.commit_sha.slice(0, 7)}
                                        </span>
                                    </div>
                                    {deployment.commit_message && (
                                        <p className="text-gray-500 dark:text-gray-400">
                                            {deployment.commit_message}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="text-gray-500 dark:text-gray-400">
                                    No commit information
                                </p>
                            )}
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Timing
                        </h3>
                        <dl className="space-y-3 text-sm">
                            {deployment.started_at && (
                                <div className="flex justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">Started</dt>
                                    <dd className="text-gray-900 dark:text-white">
                                        {formatDate(deployment.started_at)}
                                    </dd>
                                </div>
                            )}
                            {deployment.finished_at && (
                                <div className="flex justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        Finished
                                    </dt>
                                    <dd className="text-gray-900 dark:text-white">
                                        {formatDate(deployment.finished_at)}
                                    </dd>
                                </div>
                            )}
                            {duration !== null && (
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        Duration
                                    </dt>
                                    <dd className="flex items-center gap-1 text-gray-900 dark:text-white">
                                        <Clock className="size-4 text-gray-400" />
                                        {formatDuration(duration)}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </Card>
                </div>

                {/* Logs */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                        Build Logs
                        {isInProgress && (
                            <span className="ml-2 inline-flex items-center gap-1 text-sm font-normal text-yellow-600 dark:text-yellow-400">
                                <span className="size-2 animate-pulse rounded-full bg-yellow-500" />
                                Live
                            </span>
                        )}
                    </h2>
                    <LogViewer logs={logs} maxHeight="600px" autoScroll={isInProgress} />
                </div>
            </div>
        </CoolifyLayout>
    );
}
