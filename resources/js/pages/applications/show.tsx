import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    ArrowLeft,
    ExternalLink,
    GitBranch,
    Play,
    RefreshCw,
    Square,
    Rocket,
    FileText,
} from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { StatusBadge } from '@/components/status-badge';
import { DeploymentList } from '@/components/deployment-list';
import { LogViewer } from '@/components/log-viewer';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate } from '@/lib/utils';
import type { Application, Deployment } from '@/types';

interface ApplicationShowProps {
    application: Application;
    deployments: Deployment[];
    logs: string;
    pollingInterval: number;
}

export default function ApplicationShow({
    application,
    deployments,
    logs,
    pollingInterval,
}: ApplicationShowProps) {
    const [loadingAction, setLoadingAction] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState('deployments');

    useEffect(() => {
        if (pollingInterval <= 0) return;

        const interval = setInterval(() => {
            router.reload({ only: ['application', 'deployments', 'logs'] });
        }, pollingInterval * 1000);

        return () => clearInterval(interval);
    }, [pollingInterval]);

    const handleAction = (action: 'deploy' | 'restart' | 'stop' | 'start') => {
        setLoadingAction(action);
        router.post(
            `/coolify/api/applications/${application.uuid}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    const isRunning = application.status === 'running';
    const isStopped = application.status === 'stopped';

    return (
        <CoolifyLayout>
            <Head title={application.name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link
                            href="/coolify/applications"
                            className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="size-4" />
                            Back to Applications
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {application.name}
                            </h1>
                            <StatusBadge status={application.status} />
                        </div>
                        {application.description && (
                            <p className="mt-1 text-gray-500 dark:text-gray-400">
                                {application.description}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        {isStopped && (
                            <Button
                                variant="outline"
                                onClick={() => handleAction('start')}
                                disabled={!!loadingAction}
                            >
                                <Play className="size-4" />
                                Start
                            </Button>
                        )}
                        {isRunning && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => handleAction('stop')}
                                    disabled={!!loadingAction}
                                >
                                    <Square className="size-4" />
                                    Stop
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => handleAction('restart')}
                                    disabled={!!loadingAction}
                                >
                                    <RefreshCw className="size-4" />
                                    Restart
                                </Button>
                            </>
                        )}
                        <Button
                            onClick={() => handleAction('deploy')}
                            disabled={!!loadingAction}
                        >
                            <Rocket className="size-4" />
                            Deploy
                        </Button>
                    </div>
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
                                    {application.uuid.slice(0, 8)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Build Pack</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {application.build_pack ?? 'N/A'}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatDate(application.created_at)}
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Repository
                        </h3>
                        <dl className="space-y-3 text-sm">
                            {application.git_repository ? (
                                <>
                                    <div className="flex items-center gap-2 text-gray-900 dark:text-white">
                                        <ExternalLink className="size-4 text-gray-400" />
                                        <a
                                            href={application.git_repository}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="truncate hover:text-coolify-600"
                                        >
                                            {application.git_repository}
                                        </a>
                                    </div>
                                    {application.git_branch && (
                                        <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                            <GitBranch className="size-4" />
                                            {application.git_branch}
                                        </div>
                                    )}
                                </>
                            ) : (
                                <p className="text-gray-500 dark:text-gray-400">
                                    No repository configured
                                </p>
                            )}
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Domain
                        </h3>
                        {application.fqdn ? (
                            <a
                                href={application.fqdn}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-2 text-coolify-600 hover:text-coolify-700 dark:text-coolify-400"
                            >
                                <ExternalLink className="size-4" />
                                {application.fqdn.replace(/^https?:\/\//, '')}
                            </a>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                No domain configured
                            </p>
                        )}
                    </Card>
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="deployments">
                            <Rocket className="mr-2 size-4" />
                            Deployments
                        </TabsTrigger>
                        <TabsTrigger value="logs">
                            <FileText className="mr-2 size-4" />
                            Logs
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="deployments">
                        <DeploymentList
                            deployments={deployments}
                            applicationUuid={application.uuid}
                        />
                    </TabsContent>

                    <TabsContent value="logs">
                        <LogViewer logs={logs} maxHeight="600px" />
                    </TabsContent>
                </Tabs>
            </div>
        </CoolifyLayout>
    );
}
