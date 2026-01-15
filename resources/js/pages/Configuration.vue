<script setup>
import { ref, onMounted, inject, computed, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const stats = inject('stats');
const api = inject('api');
const toast = inject('toast');

const activeTab = ref('environment');
const loading = ref(true);
const saving = ref(false);

// Environment variables
const envs = ref([]);
const showAddForm = ref(false);
const newEnv = ref({ key: '', value: '', is_build_time: false });
const editingEnv = ref(null);
const revealedEnvs = ref(new Set());
const searchQuery = ref('');

// Sensitive key patterns
const sensitivePatterns = [
    'PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'CREDENTIAL', 'AUTH',
    'PRIVATE', 'API_KEY', 'ACCESS', 'SESSION', 'HASH', 'SALT',
    'ENCRYPT', 'CERTIFICATE', 'JWT', 'BEARER', 'OAUTH', 'STRIPE',
    'WEBHOOK', 'SIGNING', 'MASTER', 'ROOT', 'ADMIN'
];

function isSensitive(key) {
    const upperKey = key?.toUpperCase() || '';
    return sensitivePatterns.some(pattern => upperKey.includes(pattern));
}

function maskValue(value, key) {
    if (!value) return '(empty)';
    if (!isSensitive(key)) return value;
    if (value.length <= 4) return '*'.repeat(value.length);
    return value.substring(0, 2) + '*'.repeat(Math.min(value.length - 4, 16)) + value.substring(value.length - 2);
}

function toggleReveal(envUuid) {
    if (revealedEnvs.value.has(envUuid)) {
        revealedEnvs.value.delete(envUuid);
    } else {
        revealedEnvs.value.add(envUuid);
    }
}

const filteredEnvs = computed(() => {
    if (!searchQuery.value) return envs.value;
    const q = searchQuery.value.toLowerCase();
    return envs.value.filter(env =>
        env.key.toLowerCase().includes(q) ||
        env.value?.toLowerCase().includes(q)
    );
});

// Backups
const backups = ref([]);
const triggering = ref(false);

const app = computed(() => stats.value?.application || {});
const database = computed(() => stats.value?.databases?.primary);
const deployKey = computed(() => stats.value?.deployKey);

// Set tab from route query
watch(() => route.query.tab, (tab) => {
    if (tab && ['environment', 'backups', 'settings'].includes(tab)) {
        activeTab.value = tab;
    }
}, { immediate: true });

// Environment methods
async function fetchEnvs() {
    if (!app.value?.uuid) return;
    loading.value = true;
    try {
        const result = await api.getEnvs(app.value.uuid);
        envs.value = Array.isArray(result) ? result : [];
    } catch (e) {
        console.error('Failed to fetch envs:', e);
        envs.value = [];
    } finally {
        loading.value = false;
    }
}

async function addEnv() {
    if (!newEnv.value.key || saving.value) return;
    saving.value = true;
    try {
        await api.createEnv(app.value.uuid, newEnv.value);
        toast.value?.success('Variable Added', `${newEnv.value.key} has been created`);
        newEnv.value = { key: '', value: '', is_build_time: false };
        showAddForm.value = false;
        await fetchEnvs();
    } catch (e) {
        toast.value?.error('Failed to Add', e.message);
    } finally {
        saving.value = false;
    }
}

async function updateEnv(env) {
    saving.value = true;
    try {
        await api.updateEnv(app.value.uuid, env.uuid, {
            key: env.key,
            value: env.value,
            is_build_time: env.is_build_time,
        });
        toast.value?.success('Variable Updated', `${env.key} has been updated`);
        editingEnv.value = null;
        await fetchEnvs();
    } catch (e) {
        toast.value?.error('Failed to Update', e.message);
    } finally {
        saving.value = false;
    }
}

async function deleteEnv(env) {
    if (!confirm(`Delete ${env.key}?`)) return;
    try {
        await api.deleteEnv(app.value.uuid, env.uuid);
        toast.value?.success('Variable Deleted', `${env.key} has been removed`);
        await fetchEnvs();
    } catch (e) {
        toast.value?.error('Failed to Delete', e.message);
    }
}

// Backup methods
async function fetchBackups() {
    if (!database.value?.uuid) return;
    loading.value = true;
    try {
        const result = await api.getDatabaseBackups(database.value.uuid);
        backups.value = result.backups || result || [];
    } catch (e) {
        console.error('Failed to fetch backups:', e);
    } finally {
        loading.value = false;
    }
}

async function triggerBackup() {
    if (!database.value?.uuid || triggering.value) return;
    triggering.value = true;
    try {
        await api.triggerDatabaseBackup(database.value.uuid);
        await fetchBackups();
    } catch (e) {
        console.error('Failed to trigger backup:', e);
    } finally {
        triggering.value = false;
    }
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatSize(bytes) {
    if (!bytes) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    return `${size.toFixed(1)} ${units[unitIndex]}`;
}

function statusClass(status) {
    if (status === 'success' || status === 'completed') return 'bg-emerald-500/10 text-emerald-400';
    if (status === 'failed') return 'bg-red-500/10 text-red-400';
    if (status === 'running' || status === 'in_progress') return 'bg-amber-500/10 text-amber-400';
    return 'bg-zinc-500/10 text-zinc-400';
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
}

// Watch for tab changes to load data
watch(activeTab, (tab) => {
    if (tab === 'environment') fetchEnvs();
    else if (tab === 'backups') fetchBackups();
});

onMounted(() => {
    if (activeTab.value === 'environment') fetchEnvs();
    else if (activeTab.value === 'backups') fetchBackups();
});
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Configuration</h1>
            <p class="mt-1 text-sm text-zinc-400">Manage environment variables, backups, and settings</p>
        </div>

        <!-- Tabs -->
        <div class="border-b border-zinc-800">
            <nav class="flex gap-6">
                <button
                    @click="activeTab = 'environment'"
                    :class="[
                        activeTab === 'environment' ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400 hover:text-white',
                        'border-b-2 pb-3 text-sm font-medium transition-colors'
                    ]"
                >
                    Variables
                </button>
                <button
                    @click="activeTab = 'backups'"
                    :class="[
                        activeTab === 'backups' ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400 hover:text-white',
                        'border-b-2 pb-3 text-sm font-medium transition-colors'
                    ]"
                >
                    Backups
                </button>
                <button
                    @click="activeTab = 'settings'"
                    :class="[
                        activeTab === 'settings' ? 'border-violet-500 text-white' : 'border-transparent text-zinc-400 hover:text-white',
                        'border-b-2 pb-3 text-sm font-medium transition-colors'
                    ]"
                >
                    Settings
                </button>
            </nav>
        </div>

        <!-- Environment Tab -->
        <div v-if="activeTab === 'environment'" class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <!-- Search -->
                <div class="relative flex-1 max-w-sm">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search variables..."
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-800 py-2 pl-10 pr-4 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    />
                </div>
                <button
                    @click="showAddForm = true"
                    v-if="!showAddForm"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Variable
                </button>
            </div>

            <!-- Add form -->
            <div v-if="showAddForm" class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-1.5">Key</label>
                        <input
                            v-model="newEnv.key"
                            type="text"
                            placeholder="VARIABLE_NAME"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-300 mb-1.5">Value</label>
                        <input
                            v-model="newEnv.value"
                            type="text"
                            placeholder="value"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-zinc-400">
                        <input v-model="newEnv.is_build_time" type="checkbox" class="h-4 w-4 rounded border-zinc-700 bg-zinc-800 text-violet-600" />
                        Build-time variable
                    </label>
                    <div class="flex items-center gap-2">
                        <button @click="showAddForm = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">Cancel</button>
                        <button @click="addEnv" :disabled="!newEnv.key || saving" class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">Add</button>
                    </div>
                </div>
            </div>

            <!-- Env list -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
                </div>
                <div v-else-if="filteredEnvs.length" class="divide-y divide-zinc-800">
                    <div v-for="env in filteredEnvs" :key="env.uuid" class="px-5 py-4">
                        <!-- Editing mode -->
                        <div v-if="editingEnv?.uuid === env.uuid" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input v-model="editingEnv.key" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white" />
                                <input v-model="editingEnv.value" type="text" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white font-mono" />
                            </div>
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm text-zinc-400">
                                    <input v-model="editingEnv.is_build_time" type="checkbox" class="h-4 w-4 rounded border-zinc-700 bg-zinc-800 text-violet-600" />
                                    Build-time
                                </label>
                                <div class="flex items-center gap-2">
                                    <button @click="editingEnv = null" class="text-sm text-zinc-400 hover:text-white">Cancel</button>
                                    <button @click="updateEnv(editingEnv)" :disabled="saving" class="rounded-lg bg-violet-600 px-3 py-1 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">Save</button>
                                </div>
                            </div>
                        </div>
                        <!-- Display mode -->
                        <div v-else class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                <code class="text-sm font-medium text-white shrink-0">{{ env.key }}</code>
                                <!-- Sensitive indicator -->
                                <span v-if="isSensitive(env.key)" class="inline-flex items-center rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-400 shrink-0">
                                    <svg class="mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    Secret
                                </span>
                                <!-- Value with masking -->
                                <code class="text-sm text-zinc-400 truncate font-mono">
                                    {{ revealedEnvs.has(env.uuid) ? (env.value || '(empty)') : maskValue(env.value, env.key) }}
                                </code>
                                <span v-if="env.is_build_time" class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400 shrink-0">Build</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <!-- Reveal toggle for sensitive values -->
                                <button
                                    v-if="isSensitive(env.key)"
                                    @click="toggleReveal(env.uuid)"
                                    class="p-1 text-zinc-400 hover:text-white transition-colors"
                                    :title="revealedEnvs.has(env.uuid) ? 'Hide value' : 'Show value'"
                                >
                                    <svg v-if="revealedEnvs.has(env.uuid)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                                <!-- Copy button -->
                                <button
                                    @click="copyToClipboard(env.value); toast.value?.success('Copied', `${env.key} value copied to clipboard`)"
                                    class="p-1 text-zinc-400 hover:text-white transition-colors"
                                    title="Copy value"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                    </svg>
                                </button>
                                <button @click="editingEnv = { ...env }" class="text-sm text-zinc-400 hover:text-white">Edit</button>
                                <button @click="deleteEnv(env)" class="text-sm text-red-400 hover:text-red-300">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else-if="searchQuery && envs.length" class="px-5 py-12 text-center text-sm text-zinc-500">
                    No variables matching "{{ searchQuery }}"
                </div>
                <div v-else class="px-5 py-12 text-center text-sm text-zinc-500">No environment variables</div>
            </div>
        </div>

        <!-- Backups Tab -->
        <div v-if="activeTab === 'backups'" class="space-y-4">
            <div class="flex justify-end" v-if="database">
                <button
                    @click="triggerBackup"
                    :disabled="triggering"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                >
                    <span v-if="triggering" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                    </svg>
                    Backup Now
                </button>
            </div>

            <div v-if="!database" class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center">
                <p class="text-sm text-zinc-400">No database configured for backups</p>
            </div>

            <div v-else class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
                </div>
                <table v-else-if="backups.length" class="w-full">
                    <thead>
                        <tr class="border-b border-zinc-800 text-left text-sm text-zinc-400">
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Created</th>
                            <th class="px-5 py-3 font-medium">Size</th>
                            <th class="px-5 py-3 font-medium">Filename</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        <tr v-for="backup in backups" :key="backup.id" class="hover:bg-zinc-800/50">
                            <td class="px-5 py-4">
                                <span :class="[statusClass(backup.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">{{ backup.status }}</span>
                            </td>
                            <td class="px-5 py-4 text-sm text-zinc-300">{{ formatDate(backup.created_at) }}</td>
                            <td class="px-5 py-4 text-sm text-zinc-400">{{ formatSize(backup.size) }}</td>
                            <td class="px-5 py-4 text-sm text-zinc-400 font-mono">{{ backup.filename || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="px-5 py-12 text-center text-sm text-zinc-500">No backups yet</div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div v-if="activeTab === 'settings'" class="grid gap-6 lg:grid-cols-2">
            <!-- Application Details -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4">
                    <h2 class="text-lg font-medium text-white">Application</h2>
                </div>
                <div class="divide-y divide-zinc-800">
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Name</span>
                        <span class="text-sm text-white">{{ app.name || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">UUID</span>
                        <div class="flex items-center gap-2">
                            <code class="text-sm text-zinc-300">{{ app.uuid || '-' }}</code>
                            <button v-if="app.uuid" @click="copyToClipboard(app.uuid)" class="text-zinc-500 hover:text-white">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Build Pack</span>
                        <span class="text-sm text-white capitalize">{{ app.build_pack || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between" v-if="app.fqdn">
                        <span class="text-sm text-zinc-400">Domain</span>
                        <a :href="app.fqdn" target="_blank" class="text-sm text-violet-400 hover:text-violet-300">{{ app.fqdn.replace(/^https?:\/\//, '') }}</a>
                    </div>
                </div>
            </div>

            <!-- Repository -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4">
                    <h2 class="text-lg font-medium text-white">Repository</h2>
                </div>
                <div class="divide-y divide-zinc-800">
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Repository</span>
                        <a v-if="app.repository" :href="`https://github.com/${app.repository}`" target="_blank" class="text-sm text-violet-400 hover:text-violet-300">{{ app.repository }}</a>
                        <span v-else class="text-sm text-zinc-400">-</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Branch</span>
                        <span class="text-sm text-white">{{ app.branch || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Commit</span>
                        <code class="text-sm text-zinc-300">{{ app.commit || '-' }}</code>
                    </div>
                </div>
            </div>

            <!-- Deploy Key -->
            <div v-if="deployKey" class="rounded-xl border border-zinc-800 bg-zinc-900 lg:col-span-2">
                <div class="border-b border-zinc-800 px-5 py-4">
                    <h2 class="text-lg font-medium text-white">Deploy Key</h2>
                </div>
                <div class="p-5">
                    <div class="flex items-start gap-2">
                        <code class="flex-1 text-xs text-zinc-400 bg-zinc-800 p-3 rounded-lg break-all">{{ deployKey.public_key }}</code>
                        <button @click="copyToClipboard(deployKey.public_key)" class="p-2 text-zinc-500 hover:text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
