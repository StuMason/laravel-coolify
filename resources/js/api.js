const basePath = window.Coolify?.path || '/coolify';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content;

const headers = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
};

async function request(method, endpoint, data = null) {
    const url = `${basePath}/api${endpoint}`;
    const options = {
        method,
        headers,
        ...(data && { body: JSON.stringify(data) }),
    };

    const response = await fetch(url, options);

    if (!response.ok) {
        const error = await response.json().catch(() => ({}));
        throw new Error(error.message || `Request failed: ${response.status}`);
    }

    return response.json();
}

export const api = {
    // Stats
    getStats: (environment = 'production') => request('GET', `/stats?environment=${encodeURIComponent(environment)}`),

    // Applications
    getApplication: (uuid) => request('GET', `/applications/${uuid}`),
    updateApplication: (uuid, data) => request('PATCH', `/applications/${uuid}`, data),
    deployApplication: (uuid, options = {}) => request('POST', `/applications/${uuid}/deploy`, options),
    restartApplication: (uuid) => request('POST', `/applications/${uuid}/restart`),
    startApplication: (uuid) => request('POST', `/applications/${uuid}/start`),
    stopApplication: (uuid) => request('POST', `/applications/${uuid}/stop`),
    getApplicationLogs: (uuid) => request('GET', `/applications/${uuid}/logs`),

    // Environment Variables
    getEnvs: (uuid) => request('GET', `/applications/${uuid}/envs`),
    createEnv: (uuid, data) => request('POST', `/applications/${uuid}/envs`, data),
    updateEnv: (uuid, envUuid, data) => request('PATCH', `/applications/${uuid}/envs/${envUuid}`, data),
    deleteEnv: (uuid, envUuid) => request('DELETE', `/applications/${uuid}/envs/${envUuid}`),

    // Deployments
    getDeployments: (uuid) => request('GET', `/applications/${uuid}/deployments`),
    getDeployment: (uuid) => request('GET', `/deployments/${uuid}`),
    getDeploymentLogs: (uuid) => request('GET', `/deployments/${uuid}/logs`),

    // Databases
    getDatabases: () => request('GET', '/databases'),
    getDatabase: (uuid) => request('GET', `/databases/${uuid}`),
    updateDatabase: (uuid, data) => request('PATCH', `/databases/${uuid}`, data),
    startDatabase: (uuid) => request('POST', `/databases/${uuid}/start`),
    stopDatabase: (uuid) => request('POST', `/databases/${uuid}/stop`),
    restartDatabase: (uuid) => request('POST', `/databases/${uuid}/restart`),

    // Backups (schedules and executions)
    getDatabaseBackups: (uuid) => request('GET', `/databases/${uuid}/backups`),
    createBackupSchedule: (uuid, data) => request('POST', `/databases/${uuid}/backups`, data),
    updateBackupSchedule: (uuid, backupUuid, data) => request('PATCH', `/databases/${uuid}/backups/${backupUuid}`, data),
    deleteBackupSchedule: (uuid, backupUuid) => request('DELETE', `/databases/${uuid}/backups/${backupUuid}`),

    // Servers
    getServers: () => request('GET', '/servers'),
    getServer: (uuid) => request('GET', `/servers/${uuid}`),

    // Services
    getServices: () => request('GET', '/services'),
    getService: (uuid) => request('GET', `/services/${uuid}`),
    startService: (uuid) => request('POST', `/services/${uuid}/start`),
    stopService: (uuid) => request('POST', `/services/${uuid}/stop`),
    restartService: (uuid) => request('POST', `/services/${uuid}/restart`),

    // Projects
    getProjects: () => request('GET', '/projects'),
    getProject: (uuid) => request('GET', `/projects/${uuid}`),

    // Environments
    getEnvironments: () => request('GET', '/environments'),

    // Kick integration
    getKickStatus: (appUuid) => request('GET', `/kick/${appUuid}/status`),
    getKickHealth: (appUuid) => request('GET', `/kick/${appUuid}/health`),
    getKickStats: (appUuid) => request('GET', `/kick/${appUuid}/stats`),
    getKickLogFiles: (appUuid) => request('GET', `/kick/${appUuid}/logs`),
    postKickLogsTest: (appUuid) => request('POST', `/kick/${appUuid}/logs/test`),
    getKickLogs: (appUuid, file, params = {}) => {
        const query = new URLSearchParams();
        if (params.level) query.set('level', params.level);
        if (params.search) query.set('search', params.search);
        if (params.lines) query.set('lines', params.lines);
        const queryStr = query.toString();
        return request('GET', `/kick/${appUuid}/logs/${file}${queryStr ? '?' + queryStr : ''}`);
    },
    getKickQueueStatus: (appUuid) => request('GET', `/kick/${appUuid}/queue`),
    getKickQueueFailed: (appUuid, limit = 20) => request('GET', `/kick/${appUuid}/queue/failed?limit=${limit}`),
    getKickArtisanList: (appUuid) => request('GET', `/kick/${appUuid}/artisan`),
    postKickArtisanRun: (appUuid, command, args = {}) => request('POST', `/kick/${appUuid}/artisan`, { command, arguments: args }),
};

export default api;
