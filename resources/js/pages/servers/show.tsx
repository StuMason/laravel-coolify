import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    Server as ServerIcon,
    CheckCircle,
    XCircle,
    RefreshCw,
    Globe,
    Box,
    Database,
} from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import type { Server, Application, Database as DatabaseType } from '@/types';

interface ServerShowProps {
    server: Server;
    resources: {
        applications: Application[];
        databases: DatabaseType[];
    };
    domains: string[];
}

export default function ServerShow({ server, resources, domains }: ServerShowProps) {
    const [isValidating, setIsValidating] = useState(false);

    const handleValidate = () => {
        setIsValidating(true);
        router.post(
            `/coolify/api/servers/${server.uuid}/validate`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsValidating(false),
            }
        );
    };

    return (
        <CoolifyLayout>
            <Head title={server.name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link
                            href="/coolify/servers"
                            className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="size-4" />
                            Back to Servers
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {server.name}
                            </h1>
                            {server.is_reachable ? (
                                <Badge variant="success">
                                    <CheckCircle className="mr-1 size-3" />
                                    Reachable
                                </Badge>
                            ) : (
                                <Badge variant="error">
                                    <XCircle className="mr-1 size-3" />
                                    Unreachable
                                </Badge>
                            )}
                        </div>
                        {server.description && (
                            <p className="mt-1 text-gray-500 dark:text-gray-400">
                                {server.description}
                            </p>
                        )}
                    </div>

                    <Button
                        variant="outline"
                        onClick={handleValidate}
                        disabled={isValidating}
                    >
                        <RefreshCw className={`size-4 ${isValidating ? 'animate-spin' : ''}`} />
                        Validate Connection
                    </Button>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Connection Details
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">IP Address</dt>
                                <dd className="font-mono text-gray-900 dark:text-white">
                                    {server.ip}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">SSH Port</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {server.port ?? 22}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">User</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {server.user ?? 'root'}
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Status
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Reachable</dt>
                                <dd>
                                    {server.is_reachable ? (
                                        <CheckCircle className="size-5 text-green-500" />
                                    ) : (
                                        <XCircle className="size-5 text-red-500" />
                                    )}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Usable</dt>
                                <dd>
                                    {server.is_usable ? (
                                        <CheckCircle className="size-5 text-green-500" />
                                    ) : (
                                        <XCircle className="size-5 text-red-500" />
                                    )}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatDate(server.created_at)}
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Resources
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <Box className="size-4" />
                                    Applications
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {resources.applications.length}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <Database className="size-4" />
                                    Databases
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {resources.databases.length}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                    <Globe className="size-4" />
                                    Domains
                                </dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {domains.length}
                                </dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                {/* Domains */}
                {domains.length > 0 && (
                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Domains
                        </h3>
                        <div className="flex flex-wrap gap-2">
                            {domains.map((domain) => (
                                <a
                                    key={domain}
                                    href={`https://${domain}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="rounded-lg bg-gray-100 px-3 py-1 text-sm text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    {domain}
                                </a>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </CoolifyLayout>
    );
}
