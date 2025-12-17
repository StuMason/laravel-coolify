import { Link } from '@inertiajs/react';
import { ExternalLink, GitBranch, Play, RefreshCw, Square } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import type { Application } from '@/types';

interface ApplicationCardProps {
    application: Application;
    onDeploy?: (uuid: string) => void;
    onRestart?: (uuid: string) => void;
    onStop?: (uuid: string) => void;
    onStart?: (uuid: string) => void;
    isLoading?: boolean;
}

export function ApplicationCard({
    application,
    onDeploy,
    onRestart,
    onStop,
    onStart,
    isLoading,
}: ApplicationCardProps) {
    const isRunning = application.status === 'running';
    const isStopped = application.status === 'stopped';

    return (
        <Card>
            <div className="flex items-start justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3">
                        <Link
                            href={`/coolify/applications/${application.uuid}`}
                            className="truncate text-lg font-semibold text-gray-900 hover:text-coolify-600 dark:text-white dark:hover:text-coolify-400"
                        >
                            {application.name}
                        </Link>
                        <StatusBadge status={application.status} />
                    </div>

                    {application.description && (
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {application.description}
                        </p>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        {application.fqdn && (
                            <a
                                href={application.fqdn}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1 hover:text-coolify-600 dark:hover:text-coolify-400"
                            >
                                <ExternalLink className="size-4" />
                                <span className="truncate max-w-[200px]">
                                    {application.fqdn.replace(/^https?:\/\//, '')}
                                </span>
                            </a>
                        )}
                        {application.git_branch && (
                            <span className="flex items-center gap-1">
                                <GitBranch className="size-4" />
                                {application.git_branch}
                            </span>
                        )}
                    </div>
                </div>

                <div className="ml-4 flex shrink-0 items-center gap-2">
                    {isStopped && onStart && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onStart(application.uuid)}
                            disabled={isLoading}
                        >
                            <Play className="size-4" />
                            Start
                        </Button>
                    )}
                    {isRunning && onStop && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onStop(application.uuid)}
                            disabled={isLoading}
                        >
                            <Square className="size-4" />
                            Stop
                        </Button>
                    )}
                    {isRunning && onRestart && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onRestart(application.uuid)}
                            disabled={isLoading}
                        >
                            <RefreshCw className="size-4" />
                            Restart
                        </Button>
                    )}
                    {onDeploy && (
                        <Button
                            size="sm"
                            onClick={() => onDeploy(application.uuid)}
                            disabled={isLoading}
                        >
                            Deploy
                        </Button>
                    )}
                </div>
            </div>
        </Card>
    );
}
