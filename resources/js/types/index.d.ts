export interface Application {
    uuid: string;
    name: string;
    description?: string;
    status: ApplicationStatus;
    fqdn?: string;
    git_repository?: string;
    git_branch?: string;
    build_pack?: string;
    created_at: string;
    updated_at: string;
}

export type ApplicationStatus =
    | 'running'
    | 'stopped'
    | 'starting'
    | 'stopping'
    | 'restarting'
    | 'building'
    | 'error'
    | 'degraded';

export interface Server {
    uuid: string;
    name: string;
    description?: string;
    ip: string;
    user?: string;
    port?: number;
    is_reachable: boolean;
    is_usable: boolean;
    created_at: string;
    updated_at: string;
}

export interface Database {
    uuid: string;
    name: string;
    description?: string;
    type: DatabaseType;
    status: string;
    is_public: boolean;
    public_port?: number;
    created_at: string;
    updated_at: string;
}

export type DatabaseType =
    | 'postgresql'
    | 'mysql'
    | 'mariadb'
    | 'mongodb'
    | 'redis'
    | 'dragonfly'
    | 'keydb'
    | 'clickhouse';

export interface Deployment {
    uuid: string;
    deployment_uuid?: string;
    status: DeploymentStatus;
    commit_sha?: string;
    commit_message?: string;
    started_at?: string;
    finished_at?: string;
    logs?: string;
    created_at: string;
}

export type DeploymentStatus =
    | 'queued'
    | 'in_progress'
    | 'finished'
    | 'failed'
    | 'cancelled';

export interface Service {
    uuid: string;
    name: string;
    description?: string;
    type: string;
    status: string;
    created_at: string;
    updated_at: string;
}

export interface Project {
    uuid: string;
    name: string;
    description?: string;
    environments?: Environment[];
    created_at: string;
    updated_at: string;
}

export interface Environment {
    id: number;
    name: string;
    project_uuid: string;
    created_at: string;
    updated_at: string;
}

export interface Team {
    id: number;
    name: string;
    description?: string;
    created_at: string;
    updated_at: string;
}

export interface TeamMember {
    id: number;
    name: string;
    email: string;
    role?: string;
}

export interface DashboardStats {
    applications: {
        total: number;
        running: number;
        stopped: number;
        error: number;
    };
    servers: {
        total: number;
        reachable: number;
        unreachable: number;
    };
    databases: {
        total: number;
        running: number;
    };
    deployments: {
        total: number;
        recent: Deployment[];
    };
}

export interface SharedData {
    coolify: {
        appName: string;
        path: string;
        polling: number;
    };
}

export interface PageProps<T extends Record<string, unknown> = Record<string, unknown>> {
    coolify: SharedData['coolify'];
    [key: string]: unknown;
}
