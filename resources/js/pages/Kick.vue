<script setup>
import { ref, onMounted, onBeforeUnmount, inject, computed, watch } from 'vue';

const stats = inject('stats');
const api = inject('api');

// State
const activeTab = ref('overview');
const loading = ref(true);
const error = ref(null);
const serviceUnavailable = ref(false);

// Data
const health = ref(null);
const systemStats = ref(null);
const logFiles = ref([]);
const selectedLogFile = ref(null);
const logEntries = ref([]);
const logLevel = ref(null);
const logSearch = ref('');
const logLines = ref(100);
const queueStatus = ref(null);
const failedJobs = ref([]);
const artisanCommands = ref([]);
const selectedCommand = ref(null);
const artisanOutput = ref(null);
const artisanRunning = ref(false);

const appUuid = computed(() => stats.value?.application?.uuid);
const appName = computed(() => stats.value?.application?.name || 'Unknown App');
const envName = computed(() => stats.value?.environment?.name || 'unknown');
const appFqdn = computed(() => {
    const fqdn = stats.value?.application?.fqdn;
    if (!fqdn) return null;
    try {
        return new URL(fqdn).hostname;
    } catch {
        return fqdn;
    }
});

const tabs = [
    { id: 'overview', name: 'Overview', icon: 'dashboard' },
    { id: 'logs', name: 'Logs', icon: 'terminal' },
    { id: 'queue', name: 'Queue', icon: 'queue' },
    { id: 'artisan', name: 'Artisan', icon: 'command' },
];

const levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

// Fetch functions
async function fetchHealth() {
    if (!appUuid.value) return;
    try {
        health.value = await api.getKickHealth(appUuid.value);
        serviceUnavailable.value = false;
    } catch (e) {
        console.error('Failed to fetch health:', e);
        if (e.message?.includes('503') || e.message?.includes('404')) {
            serviceUnavailable.value = true;
        }
    }
}

async function fetchStats() {
    if (!appUuid.value) return;
    try {
        systemStats.value = await api.getKickStats(appUuid.value);
    } catch (e) {
        console.error('Failed to fetch stats:', e);
    }
}

async function fetchLogFiles() {
    if (!appUuid.value) return;
    try {
        const result = await api.getKickLogFiles(appUuid.value);
        logFiles.value = result.files || [];

        // If no logs exist, create test entries
        if (logFiles.value.length === 0) {
            await api.postKickLogsTest(appUuid.value);
            // Refetch after creating test logs
            const retryResult = await api.getKickLogFiles(appUuid.value);
            logFiles.value = retryResult.files || [];
        }

        if (logFiles.value.length > 0 && !selectedLogFile.value) {
            selectedLogFile.value = logFiles.value[0].name;
        }
    } catch (e) {
        console.error('Failed to fetch log files:', e);
    }
}

async function fetchLogs() {
    if (!appUuid.value || !selectedLogFile.value) return;
    try {
        const result = await api.getKickLogs(appUuid.value, selectedLogFile.value, {
            level: logLevel.value,
            search: logSearch.value || undefined,
            lines: logLines.value,
        });
        logEntries.value = result.entries || [];
    } catch (e) {
        console.error('Failed to fetch logs:', e);
    }
}

async function fetchQueue() {
    if (!appUuid.value) return;
    try {
        queueStatus.value = await api.getKickQueueStatus(appUuid.value);
        const failed = await api.getKickQueueFailed(appUuid.value);
        failedJobs.value = failed.failed_jobs || [];
    } catch (e) {
        console.error('Failed to fetch queue:', e);
    }
}

async function fetchArtisan() {
    if (!appUuid.value) return;
    try {
        const result = await api.getKickArtisanList(appUuid.value);
        artisanCommands.value = result.commands || [];
        if (artisanCommands.value.length > 0 && !selectedCommand.value) {
            selectedCommand.value = artisanCommands.value[0].name;
        }
    } catch (e) {
        console.error('Failed to fetch artisan commands:', e);
    }
}

async function runArtisan() {
    if (!appUuid.value || !selectedCommand.value || artisanRunning.value) return;
    artisanRunning.value = true;
    artisanOutput.value = null;
    try {
        const result = await api.postKickArtisanRun(appUuid.value, selectedCommand.value);
        artisanOutput.value = result;
    } catch (e) {
        artisanOutput.value = { success: false, output: e.message, exit_code: 1 };
    } finally {
        artisanRunning.value = false;
    }
}

async function fetchAll() {
    loading.value = true;
    error.value = null;
    try {
        await Promise.all([fetchHealth(), fetchStats(), fetchLogFiles(), fetchQueue(), fetchArtisan()]);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

// Watch for log file/filter changes
watch([selectedLogFile, logLevel, logLines], () => {
    if (activeTab.value === 'logs') {
        fetchLogs();
    }
});

// Search debounce
let searchTimeout = null;
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (activeTab.value === 'logs') {
            fetchLogs();
        }
    }, 300);
}

// Format helpers
function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatPercent(value) {
    return value ? value.toFixed(1) + '%' : '0%';
}

function formatUptime(seconds) {
    if (!seconds) return 'Unknown';
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
}

function healthStatusColor(status) {
    if (status === 'healthy' || status === true) return 'text-emerald-400';
    if (status === 'degraded') return 'text-amber-400';
    return 'text-red-400';
}

function logLevelColor(content) {
    if (content.includes('.ERROR:') || content.includes('.CRITICAL:') || content.includes('.ALERT:') || content.includes('.EMERGENCY:')) return 'text-red-400';
    if (content.includes('.WARNING:') || content.includes('.NOTICE:')) return 'text-amber-400';
    if (content.includes('.INFO:')) return 'text-blue-400';
    return 'text-zinc-400';
}

let pollInterval = null;

onMounted(() => {
    fetchAll();
    pollInterval = setInterval(() => {
        if (activeTab.value === 'overview') {
            fetchHealth();
            fetchStats();
        } else if (activeTab.value === 'logs') {
            fetchLogs();
        } else if (activeTab.value === 'queue') {
            fetchQueue();
        }
    }, 10000);
});

onBeforeUnmount(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (searchTimeout) clearTimeout(searchTimeout);
});
</script>

<template>
    <div class="p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold text-white">Laravel Kick</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-500/10 px-3 py-1 text-sm font-medium text-violet-400 ring-1 ring-inset ring-violet-500/20">
                        {{ appName }}
                        <span class="text-violet-500/60">·</span>
                        {{ envName }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-zinc-400">
                    Introspecting <span class="text-zinc-300">{{ appFqdn || 'remote application' }}</span>
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
        </div>

        <!-- Service Unavailable -->
        <div v-else-if="serviceUnavailable" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-6">
            <div class="flex items-start gap-4">
                <svg class="h-6 w-6 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div>
                    <h3 class="text-lg font-medium text-amber-400">Kick Service Unavailable</h3>
                    <p class="mt-1 text-sm text-zinc-400">
                        The Laravel Kick endpoints are not responding (503/404). This usually happens when:
                    </p>
                    <ul class="mt-2 text-sm text-zinc-400 list-disc list-inside space-y-1">
                        <li>The route cache was cleared and needs to be rebuilt</li>
                        <li>The application is restarting or deploying</li>
                        <li>The kick package is not properly installed</li>
                    </ul>
                    <p class="mt-3 text-sm text-zinc-400">
                        Try redeploying the application or running <code class="px-1 py-0.5 bg-zinc-800 rounded text-amber-300">php artisan route:cache</code> on the server.
                    </p>
                    <button
                        @click="fetchAll"
                        class="mt-4 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        Retry Connection
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Tabs -->
            <div class="flex space-x-1 border-b border-zinc-800">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        activeTab === tab.id
                            ? 'border-violet-500 text-white'
                            : 'border-transparent text-zinc-400 hover:text-white',
                        'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors -mb-px',
                    ]"
                >
                    <svg v-if="tab.icon === 'dashboard'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'terminal'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'queue'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                    </svg>
                    <svg v-else-if="tab.icon === 'command'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    {{ tab.name }}
                </button>
            </div>

            <!-- Overview Tab (Combined Health + Stats) -->
            <div v-if="activeTab === 'overview'" class="mt-6 space-y-6">
                <!-- Health Checks Section -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4 flex items-center gap-2">
                        <span :class="health?.status === 'healthy' ? 'text-emerald-400' : 'text-red-400'">
                            {{ health?.status === 'healthy' ? 'All Systems Healthy' : 'Issues Detected' }}
                        </span>
                    </h3>
                    <div v-if="health" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div
                            v-for="(check, name) in health.checks"
                            :key="name"
                            class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-zinc-300 capitalize">{{ name }}</span>
                                <span :class="[healthStatusColor(check.status), 'text-sm font-medium']">
                                    {{ check.status === 'healthy' ? 'OK' : 'Fail' }}
                                </span>
                            </div>
                            <div v-if="check.latency_ms" class="mt-2 text-xs text-zinc-500">
                                {{ check.latency_ms.toFixed(1) }}ms
                            </div>
                            <div v-if="check.message && check.status !== 'healthy'" class="mt-2 text-xs text-red-400">
                                {{ check.message }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Stats Section -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4">System Stats</h3>
                    <div v-if="systemStats?.stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- CPU Load -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">CPU Load (1m)</div>
                            <div class="mt-2 text-2xl font-semibold text-white">
                                {{ systemStats.stats.cpu?.load_average?.['1m']?.toFixed(2) || '0.00' }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                5m: {{ systemStats.stats.cpu?.load_average?.['5m']?.toFixed(2) || '0' }} |
                                15m: {{ systemStats.stats.cpu?.load_average?.['15m']?.toFixed(2) || '0' }}
                            </div>
                        </div>

                        <!-- Memory -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">Memory Used</div>
                            <div class="mt-2 text-2xl font-semibold text-white">
                                {{ formatBytes(systemStats.stats.memory?.used_bytes) }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">Container memory</div>
                        </div>

                        <!-- Disk -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">Disk Usage</div>
                            <div class="mt-2 text-2xl font-semibold text-white">
                                {{ formatPercent(systemStats.stats.disk?.used_percent) }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ formatBytes(systemStats.stats.disk?.used_bytes) }} / {{ formatBytes(systemStats.stats.disk?.total_bytes) }}
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-zinc-700 overflow-hidden">
                                <div
                                    class="h-full bg-amber-500 transition-all"
                                    :style="{ width: formatPercent(systemStats.stats.disk?.used_percent) }"
                                ></div>
                            </div>
                        </div>

                        <!-- Uptime -->
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">Uptime</div>
                            <div class="mt-2 text-2xl font-semibold text-white">
                                {{ formatUptime(systemStats.stats.uptime?.system_uptime_seconds) }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">System uptime</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Queue Stats -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4">Queue Status</h3>
                    <div v-if="queueStatus" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">Connection</div>
                            <div class="mt-2 text-xl font-semibold text-white capitalize">{{ queueStatus.connection }}</div>
                        </div>
                        <div
                            v-for="(queue, name) in queueStatus.queues"
                            :key="name"
                            class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                        >
                            <div class="text-sm font-medium text-zinc-400 capitalize">{{ name }} Queue</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ queue.size }}</div>
                            <div class="text-xs text-zinc-500">pending jobs</div>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                            <div class="text-sm font-medium text-zinc-400">Failed Jobs</div>
                            <div class="mt-2 text-2xl font-semibold" :class="queueStatus.failed_count > 0 ? 'text-red-400' : 'text-white'">
                                {{ queueStatus.failed_count }}
                            </div>
                            <div class="text-xs text-zinc-500">total failed</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Tab -->
            <div v-if="activeTab === 'logs'" class="mt-6 space-y-4">
                <!-- Filters -->
                <div class="flex flex-wrap gap-4">
                    <select
                        v-model="selectedLogFile"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none"
                    >
                        <option v-for="file in logFiles" :key="file.name" :value="file.name">
                            {{ file.name }} ({{ formatBytes(file.size) }})
                        </option>
                    </select>

                    <select
                        v-model="logLevel"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none"
                    >
                        <option :value="null">All Levels</option>
                        <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
                    </select>

                    <select
                        v-model="logLines"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none"
                    >
                        <option :value="25">25 lines</option>
                        <option :value="50">50 lines</option>
                        <option :value="100">100 lines</option>
                        <option :value="200">200 lines</option>
                        <option :value="500">500 lines</option>
                        <option :value="1000">1000 lines</option>
                    </select>

                    <input
                        v-model="logSearch"
                        @input="onSearchInput"
                        type="text"
                        placeholder="Search logs..."
                        class="flex-1 min-w-[200px] rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none"
                    />
                </div>

                <!-- Log entries -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                    <div class="max-h-[500px] overflow-auto p-4">
                        <div v-if="logEntries.length === 0" class="text-center py-8 text-zinc-400">
                            No log entries found
                        </div>
                        <div v-else class="space-y-1">
                            <div
                                v-for="entry in logEntries"
                                :key="entry.line"
                                class="font-mono text-xs leading-relaxed"
                            >
                                <span class="text-zinc-600 select-none">{{ entry.line.toString().padStart(4, ' ') }} </span>
                                <span :class="logLevelColor(entry.content)">{{ entry.content }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queue Tab -->
            <div v-if="activeTab === 'queue'" class="mt-6 space-y-6">
                <!-- Queue stats -->
                <div v-if="queueStatus" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <div class="text-sm font-medium text-zinc-400">Connection</div>
                        <div class="mt-2 text-xl font-semibold text-white capitalize">{{ queueStatus.connection }}</div>
                    </div>
                    <div
                        v-for="(queue, name) in queueStatus.queues"
                        :key="name"
                        class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                    >
                        <div class="text-sm font-medium text-zinc-400 capitalize">{{ name }} Queue</div>
                        <div class="mt-2 text-2xl font-semibold text-white">{{ queue.size }}</div>
                        <div class="text-xs text-zinc-500">pending jobs</div>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                        <div class="text-sm font-medium text-zinc-400">Failed Jobs</div>
                        <div class="mt-2 text-2xl font-semibold" :class="queueStatus.failed_count > 0 ? 'text-red-400' : 'text-white'">
                            {{ queueStatus.failed_count }}
                        </div>
                        <div class="text-xs text-zinc-500">total failed</div>
                    </div>
                </div>

                <!-- Failed jobs list -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4">Failed Jobs</h3>
                    <div v-if="failedJobs.length === 0" class="text-center py-8 text-zinc-400 rounded-xl border border-zinc-800 bg-zinc-900">
                        No failed jobs
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="job in failedJobs"
                            :key="job.id"
                            class="rounded-xl border border-zinc-800 bg-zinc-900 p-4"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="text-sm font-medium text-white">{{ job.name || job.payload?.displayName || 'Unknown Job' }}</div>
                                    <div class="text-xs text-zinc-500 mt-1">
                                        Queue: {{ job.queue }} | Failed at: {{ job.failed_at }}
                                    </div>
                                </div>
                            </div>
                            <div v-if="job.exception" class="mt-3 text-xs text-red-400 font-mono bg-zinc-950 p-2 rounded overflow-auto max-h-24">
                                {{ job.exception }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Artisan Tab -->
            <div v-if="activeTab === 'artisan'" class="mt-6 space-y-6">
                <!-- Command selector -->
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[300px]">
                        <label class="block text-sm font-medium text-zinc-400 mb-2">Available Commands</label>
                        <select
                            v-model="selectedCommand"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none"
                        >
                            <option v-for="cmd in artisanCommands" :key="cmd.name" :value="cmd.name">
                                {{ cmd.name }}
                            </option>
                        </select>
                    </div>
                    <button
                        @click="runArtisan"
                        :disabled="!selectedCommand || artisanRunning"
                        class="px-4 py-2 bg-violet-600 hover:bg-violet-700 disabled:bg-zinc-700 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2"
                    >
                        <svg v-if="artisanRunning" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ artisanRunning ? 'Running...' : 'Run Command' }}</span>
                    </button>
                </div>

                <!-- Command description -->
                <div v-if="selectedCommand" class="text-sm text-zinc-400">
                    {{ artisanCommands.find(c => c.name === selectedCommand)?.description || 'No description available' }}
                </div>

                <!-- Command output -->
                <div v-if="artisanOutput" class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 border-b border-zinc-800 bg-zinc-950">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-300">Output</span>
                            <span
                                :class="artisanOutput.success ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                                class="px-2 py-0.5 rounded text-xs font-medium"
                            >
                                Exit code: {{ artisanOutput.exit_code }}
                            </span>
                        </div>
                        <span class="text-xs text-zinc-500 font-mono">{{ artisanOutput.command }}</span>
                    </div>
                    <div class="p-4 max-h-[400px] overflow-auto">
                        <pre class="font-mono text-xs text-zinc-300 whitespace-pre-wrap">{{ artisanOutput.output || '(no output)' }}</pre>
                    </div>
                </div>

                <!-- Command list -->
                <div>
                    <h3 class="text-lg font-medium text-white mb-4">Available Commands ({{ artisanCommands.length }})</h3>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 divide-y divide-zinc-800">
                        <div
                            v-for="cmd in artisanCommands"
                            :key="cmd.name"
                            class="px-4 py-3 hover:bg-zinc-800/50 cursor-pointer transition-colors"
                            @click="selectedCommand = cmd.name"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-white font-mono">{{ cmd.name }}</span>
                                <span v-if="selectedCommand === cmd.name" class="text-xs text-violet-400">Selected</span>
                            </div>
                            <div class="text-xs text-zinc-500 mt-1">{{ cmd.description }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
