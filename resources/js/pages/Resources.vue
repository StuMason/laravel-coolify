<script setup>
import { inject, computed, ref, reactive } from 'vue';

const stats = inject('stats');
const api = inject('api');
const refreshStats = inject('refreshStats');
const toast = inject('toast');

const database = computed(() => stats.value?.databases?.primary);
const redis = computed(() => stats.value?.databases?.redis);

const dbLoading = ref(false);
const redisLoading = ref(false);

// Settings modal state
const showDbSettings = ref(false);
const showRedisSettings = ref(false);
const settingsLoading = ref(false);
const dbSettingsForm = reactive({
    name: '',
    description: '',
    image: '',
    is_public: false,
    public_port: '',
    limits_memory: '',
    limits_cpus: '',
    limits_cpu_shares: '',
});
const redisSettingsForm = reactive({
    name: '',
    description: '',
    image: '',
    is_public: false,
    public_port: '',
    limits_memory: '',
    limits_cpus: '',
    limits_cpu_shares: '',
});

// Connection string copy
const copiedDb = ref(false);
const copiedRedis = ref(false);

// Check if resource is in a running state (including healthy)
function isRunning(status) {
    return ['running', 'running:healthy', 'healthy'].includes(status?.toLowerCase());
}

// Check if resource is in a stopped/error state
function isStopped(status) {
    return ['stopped', 'exited', 'error', 'failed'].includes(status?.toLowerCase());
}

// Check if resource is transitioning
function isTransitioning(status) {
    return ['starting', 'stopping', 'restarting', 'building'].includes(status?.toLowerCase());
}

async function controlDatabase(action) {
    if (!database.value || dbLoading.value) return;
    dbLoading.value = true;
    try {
        if (action === 'start') await api.startDatabase(database.value.uuid);
        else if (action === 'stop') await api.stopDatabase(database.value.uuid);
        else if (action === 'restart') await api.restartDatabase(database.value.uuid);
        toast.value?.success('Database ' + action, `${database.value.name} ${action} initiated`);
        await refreshStats();
    } catch (e) {
        toast.value?.error(`Database ${action} failed`, e.message);
    } finally {
        dbLoading.value = false;
    }
}

async function controlRedis(action) {
    if (!redis.value || redisLoading.value) return;
    redisLoading.value = true;
    try {
        if (action === 'start') await api.startDatabase(redis.value.uuid);
        else if (action === 'stop') await api.stopDatabase(redis.value.uuid);
        else if (action === 'restart') await api.restartDatabase(redis.value.uuid);
        toast.value?.success('Cache ' + action, `${redis.value.name} ${action} initiated`);
        await refreshStats();
    } catch (e) {
        toast.value?.error(`Cache ${action} failed`, e.message);
    } finally {
        redisLoading.value = false;
    }
}

// Flush cache (restart for in-memory stores)
async function flushCache() {
    if (!redis.value || redisLoading.value) return;
    if (!confirm(`Flush ${redis.value.name}? This will restart the service and clear all cached data.`)) return;
    redisLoading.value = true;
    try {
        await api.restartDatabase(redis.value.uuid);
        toast.value?.success('Cache Flushed', `${redis.value.name} has been restarted - cache cleared`);
        await refreshStats();
    } catch (e) {
        toast.value?.error('Flush Failed', e.message);
    } finally {
        redisLoading.value = false;
    }
}

// Open settings modals
function openDbSettings() {
    Object.assign(dbSettingsForm, {
        name: database.value.name || '',
        description: database.value.description || '',
        image: database.value.image || '',
        is_public: Boolean(database.value.is_public),
        public_port: database.value.public_port || '',
        limits_memory: database.value.limits_memory || '',
        limits_cpus: database.value.limits_cpus || '',
        limits_cpu_shares: database.value.limits_cpu_shares || '',
    });
    showDbSettings.value = true;
}

function openRedisSettings() {
    Object.assign(redisSettingsForm, {
        name: redis.value.name || '',
        description: redis.value.description || '',
        image: redis.value.image || '',
        is_public: Boolean(redis.value.is_public),
        public_port: redis.value.public_port || '',
        limits_memory: redis.value.limits_memory || '',
        limits_cpus: redis.value.limits_cpus || '',
        limits_cpu_shares: redis.value.limits_cpu_shares || '',
    });
    showRedisSettings.value = true;
}

async function saveDbSettings() {
    if (settingsLoading.value) return;
    settingsLoading.value = true;
    try {
        // Filter out empty/null values but keep booleans
        const cleanData = {};
        for (const [key, value] of Object.entries(dbSettingsForm)) {
            if (typeof value === 'boolean') {
                cleanData[key] = value;
            } else if (value !== null && value !== undefined && value !== '') {
                cleanData[key] = value;
            }
        }
        console.log('Saving database settings:', cleanData);
        await api.updateDatabase(database.value.uuid, cleanData);
        toast.value?.success('Settings Saved', 'Restart required for changes to take effect');
        showDbSettings.value = false;
        await refreshStats();
    } catch (e) {
        toast.value?.error('Save Failed', e.message);
    } finally {
        settingsLoading.value = false;
    }
}

async function saveRedisSettings() {
    if (settingsLoading.value) return;
    settingsLoading.value = true;
    try {
        // Filter out empty/null values but keep booleans
        const cleanData = {};
        for (const [key, value] of Object.entries(redisSettingsForm)) {
            if (typeof value === 'boolean') {
                cleanData[key] = value;
            } else if (value !== null && value !== undefined && value !== '') {
                cleanData[key] = value;
            }
        }
        console.log('Saving cache settings:', cleanData);
        await api.updateDatabase(redis.value.uuid, cleanData);
        toast.value?.success('Settings Saved', 'Restart required for changes to take effect');
        showRedisSettings.value = false;
        await refreshStats();
    } catch (e) {
        toast.value?.error('Save Failed', e.message);
    } finally {
        settingsLoading.value = false;
    }
}

// Copy to clipboard helper (works in non-secure contexts)
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
    }
    // Fallback for non-secure contexts (http:// sites)
    return new Promise((resolve, reject) => {
        try {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            resolve();
        } catch (e) {
            reject(e);
        }
    });
}

// Copy connection string
async function copyConnectionString(resource, type) {
    const url = resource.internal_db_url || resource.external_db_url;
    if (!url) {
        toast.value?.error('No Connection URL', 'Connection string not available');
        return;
    }
    try {
        await copyToClipboard(url);
        if (type === 'db') {
            copiedDb.value = true;
            setTimeout(() => copiedDb.value = false, 2000);
        } else {
            copiedRedis.value = true;
            setTimeout(() => copiedRedis.value = false, 2000);
        }
        toast.value?.success('Copied', 'Connection string copied to clipboard');
    } catch (e) {
        toast.value?.error('Copy Failed', e.message);
    }
}

function statusClass(status) {
    const s = status?.toLowerCase();
    if (s === 'running' || s === 'running:healthy' || s === 'healthy') {
        return 'bg-emerald-500/10 text-emerald-400';
    }
    if (s === 'running:unhealthy' || s === 'unhealthy') {
        return 'bg-amber-500/10 text-amber-400';
    }
    if (s === 'stopped' || s === 'exited' || s === 'error' || s === 'failed') {
        return 'bg-red-500/10 text-red-400';
    }
    if (s === 'starting' || s === 'stopping' || s === 'restarting' || s === 'building') {
        return 'bg-blue-500/10 text-blue-400';
    }
    return 'bg-zinc-500/10 text-zinc-400';
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    return status.replace('running:', '').replace('_', ' ');
}

// Parse image to get version
function parseVersion(image) {
    if (!image) return null;
    const parts = image.split(':');
    return parts.length > 1 ? parts[1] : 'latest';
}

// Build external connection URL
function getExternalUrl(resource) {
    if (!resource?.is_public || !resource?.public_port) return null;
    const serverIp = resource?.destination?.server?.ip;
    if (!serverIp) return null;

    // Build URL based on database type
    const dbType = resource.database_type || '';
    const port = resource.public_port;

    if (dbType.includes('postgresql')) {
        const user = resource.postgres_user || 'postgres';
        const db = resource.postgres_db || 'postgres';
        return `postgresql://${user}:***@${serverIp}:${port}/${db}`;
    } else if (dbType.includes('mysql') || dbType.includes('mariadb')) {
        const user = resource.mysql_user || 'root';
        const db = resource.mysql_database || '';
        return `mysql://${user}:***@${serverIp}:${port}/${db}`;
    } else if (dbType.includes('redis') || dbType.includes('dragonfly') || dbType.includes('keydb')) {
        return `redis://${serverIp}:${port}`;
    } else if (dbType.includes('mongodb')) {
        return `mongodb://${serverIp}:${port}`;
    }
    return `${serverIp}:${port}`;
}

// Get server IP for display
function getServerIp(resource) {
    return resource?.destination?.server?.ip || null;
}
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Resources</h1>
            <p class="mt-1 text-sm text-zinc-400">Manage databases and caches</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Database -->
            <div v-if="database" class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                            <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-white">{{ database.name }}</div>
                            <div class="text-sm text-zinc-400">{{ database.type }}</div>
                        </div>
                    </div>
                    <span :class="[statusClass(database.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                        {{ formatStatus(database.status) }}
                    </span>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Version</div>
                            <div class="text-zinc-200 font-mono text-sm">{{ parseVersion(database.image) || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Public Access</div>
                            <div v-if="database.is_public && database.public_port" class="text-emerald-400 font-mono text-sm">
                                {{ getServerIp(database) }}:{{ database.public_port }}
                            </div>
                            <div v-else-if="database.is_public && !database.public_port" class="text-amber-400 text-sm">
                                Enabled (no port set)
                            </div>
                            <div v-else class="text-zinc-500 text-sm">Not exposed</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Internal URL</div>
                            <div class="flex items-center gap-2">
                                <code class="text-zinc-300 text-xs break-all flex-1 bg-zinc-800 px-2 py-1 rounded">{{ database.internal_db_url || '-' }}</code>
                                <button
                                    v-if="database.internal_db_url"
                                    @click="copyConnectionString(database, 'db')"
                                    class="flex-shrink-0 p-1.5 rounded hover:bg-zinc-700 transition-colors"
                                    :class="copiedDb ? 'text-emerald-400' : 'text-zinc-400'"
                                >
                                    <svg v-if="!copiedDb" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- External URL when publicly exposed -->
                        <div v-if="database.is_public && database.public_port" class="col-span-2">
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">External URL</div>
                            <div class="flex items-center gap-2">
                                <code class="text-emerald-300 text-xs break-all flex-1 bg-emerald-900/20 border border-emerald-800/30 px-2 py-1 rounded">{{ getExternalUrl(database) }}</code>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">Connect from outside the Docker network using this URL</p>
                        </div>
                    </div>

                    <!-- Resource Limits (if set) -->
                    <div v-if="database.limits_memory || database.limits_cpus" class="border-t border-zinc-800 pt-4">
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-2">Resource Limits</div>
                        <div class="flex gap-4 text-sm">
                            <div v-if="database.limits_memory">
                                <span class="text-zinc-400">Memory:</span>
                                <span class="text-zinc-200 ml-1">{{ database.limits_memory }}</span>
                            </div>
                            <div v-if="database.limits_cpus">
                                <span class="text-zinc-400">CPUs:</span>
                                <span class="text-zinc-200 ml-1">{{ database.limits_cpus }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-2 border-t border-zinc-800">
                        <button
                            v-if="isStopped(database.status)"
                            @click="controlDatabase('start')"
                            :disabled="dbLoading || isTransitioning(database.status)"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(database.status)"
                            @click="controlDatabase('stop')"
                            :disabled="dbLoading || isTransitioning(database.status)"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(database.status) || isStopped(database.status)"
                            @click="controlDatabase('restart')"
                            :disabled="dbLoading || isTransitioning(database.status)"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="dbLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <button
                            @click="openDbSettings"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 transition-colors ml-auto"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </button>
                        <span v-if="isTransitioning(database.status)" class="text-sm text-zinc-400 italic">
                            {{ database.status }}...
                        </span>
                    </div>
                </div>
            </div>

            <!-- Cache (Redis/Dragonfly/KeyDB) -->
            <div v-if="redis" class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                            <svg class="h-5 w-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <div>
                            <div class="font-medium text-white">{{ redis.name }}</div>
                            <div class="text-sm text-zinc-400">{{ redis.type }}</div>
                        </div>
                    </div>
                    <span :class="[statusClass(redis.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                        {{ formatStatus(redis.status) }}
                    </span>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Info Grid -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Version</div>
                            <div class="text-zinc-200 font-mono text-sm">{{ parseVersion(redis.image) || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Public Access</div>
                            <div v-if="redis.is_public && redis.public_port" class="text-emerald-400 font-mono text-sm">
                                {{ getServerIp(redis) }}:{{ redis.public_port }}
                            </div>
                            <div v-else-if="redis.is_public && !redis.public_port" class="text-amber-400 text-sm">
                                Enabled (no port set)
                            </div>
                            <div v-else class="text-zinc-500 text-sm">Not exposed</div>
                        </div>
                        <div class="col-span-2">
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">Internal URL</div>
                            <div class="flex items-center gap-2">
                                <code class="text-zinc-300 text-xs break-all flex-1 bg-zinc-800 px-2 py-1 rounded">{{ redis.internal_db_url || '-' }}</code>
                                <button
                                    v-if="redis.internal_db_url"
                                    @click="copyConnectionString(redis, 'redis')"
                                    class="flex-shrink-0 p-1.5 rounded hover:bg-zinc-700 transition-colors"
                                    :class="copiedRedis ? 'text-emerald-400' : 'text-zinc-400'"
                                >
                                    <svg v-if="!copiedRedis" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- External URL when publicly exposed -->
                        <div v-if="redis.is_public && redis.public_port" class="col-span-2">
                            <div class="text-zinc-500 text-xs uppercase tracking-wide mb-1">External URL</div>
                            <div class="flex items-center gap-2">
                                <code class="text-emerald-300 text-xs break-all flex-1 bg-emerald-900/20 border border-emerald-800/30 px-2 py-1 rounded">{{ getExternalUrl(redis) }}</code>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">Connect from outside the Docker network using this URL</p>
                        </div>
                    </div>

                    <!-- Resource Limits (if set) -->
                    <div v-if="redis.limits_memory || redis.limits_cpus" class="border-t border-zinc-800 pt-4">
                        <div class="text-zinc-500 text-xs uppercase tracking-wide mb-2">Resource Limits</div>
                        <div class="flex gap-4 text-sm">
                            <div v-if="redis.limits_memory">
                                <span class="text-zinc-400">Memory:</span>
                                <span class="text-zinc-200 ml-1">{{ redis.limits_memory }}</span>
                            </div>
                            <div v-if="redis.limits_cpus">
                                <span class="text-zinc-400">CPUs:</span>
                                <span class="text-zinc-200 ml-1">{{ redis.limits_cpus }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 pt-2 border-t border-zinc-800">
                        <button
                            v-if="isStopped(redis.status)"
                            @click="controlRedis('start')"
                            :disabled="redisLoading || isTransitioning(redis.status)"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="redisLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Start
                        </button>
                        <button
                            v-if="isRunning(redis.status)"
                            @click="controlRedis('stop')"
                            :disabled="redisLoading || isTransitioning(redis.status)"
                            class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="redisLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Stop
                        </button>
                        <button
                            v-if="isRunning(redis.status) || isStopped(redis.status)"
                            @click="controlRedis('restart')"
                            :disabled="redisLoading || isTransitioning(redis.status)"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 disabled:opacity-50 transition-colors"
                        >
                            <span v-if="redisLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Restart
                        </button>
                        <button
                            v-if="isRunning(redis.status)"
                            @click="flushCache"
                            :disabled="redisLoading || isTransitioning(redis.status)"
                            class="inline-flex items-center gap-2 rounded-lg border border-amber-600 bg-amber-600/10 px-3 py-1.5 text-sm font-medium text-amber-400 hover:bg-amber-600/20 disabled:opacity-50 transition-colors"
                            title="Restart to flush all cached data"
                        >
                            <span v-if="redisLoading" class="h-3 w-3 animate-spin rounded-full border-2 border-amber-400/30 border-t-amber-400"></span>
                            Flush Cache
                        </button>
                        <button
                            @click="openRedisSettings"
                            class="inline-flex items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 transition-colors ml-auto"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Settings
                        </button>
                        <span v-if="isTransitioning(redis.status)" class="text-sm text-zinc-400 italic">
                            {{ redis.status }}...
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!database && !redis" class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
            <p class="mt-4 text-sm text-zinc-400">No resources configured</p>
        </div>

        <!-- Database Settings Modal -->
        <Teleport to="body">
            <div v-if="showDbSettings" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">Database Settings</h3>
                        <button @click="showDbSettings = false" class="text-zinc-400 hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Name</label>
                            <input v-model="dbSettingsForm.name" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Description</label>
                            <input v-model="dbSettingsForm.description" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Image (e.g., postgres:16-alpine)</label>
                            <input v-model="dbSettingsForm.image" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono text-sm" placeholder="postgres:16-alpine" />
                            <p class="mt-1 text-xs text-zinc-500">Change image to upgrade/downgrade version</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input v-model="dbSettingsForm.is_public" type="checkbox" id="db-is-public" class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-zinc-900" />
                            <label for="db-is-public" class="text-sm font-medium text-zinc-300">Expose publicly</label>
                        </div>
                        <div v-if="dbSettingsForm.is_public">
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Public Port</label>
                            <input v-model="dbSettingsForm.public_port" type="number" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="5432" />
                        </div>
                        <div class="border-t border-zinc-800 pt-4">
                            <h4 class="text-sm font-medium text-zinc-300 mb-3">Resource Limits</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-zinc-400 mb-1">Memory Limit</label>
                                    <input v-model="dbSettingsForm.limits_memory" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white text-sm placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="512m" />
                                </div>
                                <div>
                                    <label class="block text-sm text-zinc-400 mb-1">CPU Limit</label>
                                    <input v-model="dbSettingsForm.limits_cpus" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white text-sm placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="1" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                        <button @click="showDbSettings = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition-colors">
                            Cancel
                        </button>
                        <button @click="saveDbSettings" :disabled="settingsLoading" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50 transition-colors inline-flex items-center gap-2">
                            <span v-if="settingsLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Redis Settings Modal -->
        <Teleport to="body">
            <div v-if="showRedisSettings" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">Cache Settings</h3>
                        <button @click="showRedisSettings = false" class="text-zinc-400 hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Name</label>
                            <input v-model="redisSettingsForm.name" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Description</label>
                            <input v-model="redisSettingsForm.description" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Image (e.g., redis:7-alpine, docker.dragonflydb.io/dragonflydb/dragonfly)</label>
                            <input v-model="redisSettingsForm.image" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono text-sm" placeholder="redis:7-alpine" />
                            <p class="mt-1 text-xs text-zinc-500">Change image to upgrade/downgrade version</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input v-model="redisSettingsForm.is_public" type="checkbox" id="redis-is-public" class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-zinc-900" />
                            <label for="redis-is-public" class="text-sm font-medium text-zinc-300">Expose publicly</label>
                        </div>
                        <div v-if="redisSettingsForm.is_public">
                            <label class="block text-sm font-medium text-zinc-300 mb-1">Public Port</label>
                            <input v-model="redisSettingsForm.public_port" type="number" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="6379" />
                        </div>
                        <div class="border-t border-zinc-800 pt-4">
                            <h4 class="text-sm font-medium text-zinc-300 mb-3">Resource Limits</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm text-zinc-400 mb-1">Memory Limit</label>
                                    <input v-model="redisSettingsForm.limits_memory" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white text-sm placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="256m" />
                                </div>
                                <div>
                                    <label class="block text-sm text-zinc-400 mb-1">CPU Limit</label>
                                    <input v-model="redisSettingsForm.limits_cpus" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-white text-sm placeholder:text-zinc-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="0.5" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-zinc-800 px-6 py-4">
                        <button @click="showRedisSettings = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 transition-colors">
                            Cancel
                        </button>
                        <button @click="saveRedisSettings" :disabled="settingsLoading" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50 transition-colors inline-flex items-center gap-2">
                            <span v-if="settingsLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                            Save Settings
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
