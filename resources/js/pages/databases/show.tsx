import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    Database as DatabaseIcon,
    RefreshCw,
    Download,
    Globe,
    Lock,
} from 'lucide-react';
import CoolifyLayout from '@/layouts/coolify-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatRelativeTime } from '@/lib/utils';
import type { Database } from '@/types';

interface Backup {
    id: number;
    filename: string;
    size: number;
    created_at: string;
}

interface DatabaseShowProps {
    database: Database;
    backups: Backup[];
}

export default function DatabaseShow({ database, backups }: DatabaseShowProps) {
    const [loadingAction, setLoadingAction] = useState<string | null>(null);

    const handleRestart = () => {
        setLoadingAction('restart');
        router.post(
            `/coolify/api/databases/${database.uuid}/restart`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    const handleBackup = () => {
        setLoadingAction('backup');
        router.post(
            `/coolify/api/databases/${database.uuid}/backup`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setLoadingAction(null),
            }
        );
    };

    const formatBytes = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <CoolifyLayout>
            <Head title={database.name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Link
                            href="/coolify/databases"
                            className="mb-2 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <ArrowLeft className="size-4" />
                            Back to Databases
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                {database.name}
                            </h1>
                            <StatusBadge status={database.status} />
                            <Badge variant="info">{database.type}</Badge>
                        </div>
                        {database.description && (
                            <p className="mt-1 text-gray-500 dark:text-gray-400">
                                {database.description}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={handleBackup}
                            disabled={!!loadingAction}
                        >
                            <Download className="size-4" />
                            Create Backup
                        </Button>
                        <Button
                            variant="outline"
                            onClick={handleRestart}
                            disabled={!!loadingAction}
                        >
                            <RefreshCw
                                className={`size-4 ${loadingAction === 'restart' ? 'animate-spin' : ''}`}
                            />
                            Restart
                        </Button>
                    </div>
                </div>

                {/* Info Cards */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Details
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">UUID</dt>
                                <dd className="font-mono text-gray-900 dark:text-white">
                                    {database.uuid.slice(0, 8)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Type</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {database.type}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">Created</dt>
                                <dd className="text-gray-900 dark:text-white">
                                    {formatDate(database.created_at)}
                                </dd>
                            </div>
                        </dl>
                    </Card>

                    <Card>
                        <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                            Access
                        </h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="text-gray-500 dark:text-gray-400">
                                    Public Access
                                </dt>
                                <dd>
                                    {database.is_public ? (
                                        <span className="flex items-center gap-1 text-green-600 dark:text-green-400">
                                            <Globe className="size-4" />
                                            Enabled
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1 text-gray-500 dark:text-gray-400">
                                            <Lock className="size-4" />
                                            Disabled
                                        </span>
                                    )}
                                </dd>
                            </div>
                            {database.is_public && database.public_port && (
                                <div className="flex justify-between">
                                    <dt className="text-gray-500 dark:text-gray-400">
                                        Public Port
                                    </dt>
                                    <dd className="font-mono text-gray-900 dark:text-white">
                                        {database.public_port}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </Card>
                </div>

                {/* Backups */}
                <Card>
                    <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                        Backups
                    </h3>
                    {backups.length === 0 ? (
                        <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                            No backups found
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Filename</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead>Created</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {backups.map((backup) => (
                                    <TableRow key={backup.id}>
                                        <TableCell className="font-mono text-sm">
                                            {backup.filename}
                                        </TableCell>
                                        <TableCell>{formatBytes(backup.size)}</TableCell>
                                        <TableCell>
                                            {formatRelativeTime(backup.created_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Card>
            </div>
        </CoolifyLayout>
    );
}
