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
    getStats: () => request('GET', '/stats'),

    // Applications
    getApplication: (uuid) => request('GET', `/applications/${uuid}`),
    deployApplication: (uuid) => request('POST', `/applications/${uuid}/deploy`),
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
    startDatabase: (uuid) => request('POST', `/databases/${uuid}/start`),
    stopDatabase: (uuid) => request('POST', `/databases/${uuid}/stop`),
    restartDatabase: (uuid) => request('POST', `/databases/${uuid}/restart`),

    // Backups
    getDatabaseBackups: (uuid) => request('GET', `/databases/${uuid}/backups`),
    triggerDatabaseBackup: (uuid) => request('POST', `/databases/${uuid}/backup`),

    // Servers
    getServers: () => request('GET', '/servers'),
    getServer: (uuid) => request('GET', `/servers/${uuid}`),

    // Services
    getServices: () => request('GET', '/services'),
    getService: (uuid) => request('GET', `/services/${uuid}`),

    // Projects
    getProjects: () => request('GET', '/projects'),
    getProject: (uuid) => request('GET', `/projects/${uuid}`),

    // Environments (configured resources)
    getEnvironments: () => request('GET', '/environments'),
    switchEnvironment: (id) => request('POST', `/environments/${id}/switch`),
};

export default api;
