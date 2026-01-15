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
const newEnv = ref({ key: '', value: '' });
const editingEnv = ref(null);
const revealedEnvs = ref(new Set());
const searchQuery = ref('');
const showBuildTime = ref(false);

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

// Coolify returns both build-time and runtime copies of each env var
// Group them by key so we can show unique vars or all
const envsByKey = computed(() => {
    const grouped = {};
    envs.value.forEach(env => {
        if (!grouped[env.key]) {
            grouped[env.key] = [];
        }
        grouped[env.key].push(env);
    });
    return grouped;
});

// Unique envs (first occurrence of each key - typically runtime)
const uniqueEnvs = computed(() => {
    return Object.values(envsByKey.value).map(copies => copies[0]);
});

// Count of duplicate keys (vars that have both runtime and build-time)
const duplicateCount = computed(() => {
    return Object.values(envsByKey.value).filter(copies => copies.length > 1).length;
});

const displayEnvs = computed(() => {
    // If showing all, return everything; otherwise just unique
    return showBuildTime.value ? envs.value : uniqueEnvs.value;
});

const filteredEnvs = computed(() => {
    const base = displayEnvs.value;
    if (!searchQuery.value) return base;
    const q = searchQuery.value.toLowerCase();
    return base.filter(env =>
        env.key.toLowerCase().includes(q) ||
        env.value?.toLowerCase().includes(q)
    );
});

// Check if an env var is a duplicate (has same key as another)
function isDuplicate(env) {
    const copies = envsByKey.value[env.key];
    return copies && copies.length > 1 && copies.indexOf(env) > 0;
}

// Backups
const backups = ref([]);
const showBackupForm = ref(false);
const newBackup = ref({ frequency: '0 0 * * *', enabled: true, save_s3: false });
const editingBackup = ref(null);

// Settings
const editingSettings = ref(false);
const settingsForm = ref({});

const app = computed(() => stats.value?.application || {});
const database = computed(() => stats.value?.databases?.primary);
const deployKey = computed(() => stats.value?.deployKey);

// Preset cron frequencies
const cronPresets = [
    { label: 'Every hour', value: '0 * * * *' },
    { label: 'Every 6 hours', value: '0 */6 * * *' },
    { label: 'Daily at midnight', value: '0 0 * * *' },
    { label: 'Weekly (Sunday)', value: '0 0 * * 0' },
    { label: 'Custom', value: 'custom' },
];

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
        newEnv.value = { key: '', value: '' };
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
        // Coolify creates both runtime and build-time copies with different UUIDs
        // We need to update ALL copies with the same key
        const copies = envsByKey.value[env.key] || [env];
        const updatePromises = copies.map(copy =>
            api.updateEnv(app.value.uuid, copy.uuid, {
                key: env.key,
                value: env.value,
            })
        );
        await Promise.all(updatePromises);
        toast.value?.success('Variable Updated', `${env.key} has been updated (${copies.length} cop${copies.length === 1 ? 'y' : 'ies'})`);
        editingEnv.value = null;
        await fetchEnvs();
    } catch (e) {
        toast.value?.error('Failed to Update', e.message);
    } finally {
        saving.value = false;
    }
}

async function deleteEnv(env) {
    // Coolify creates both runtime and build-time copies with different UUIDs
    // We need to delete ALL copies with the same key
    const copies = envsByKey.value[env.key] || [env];
    const copyCount = copies.length;

    if (!confirm(`Delete ${env.key}? This will remove ${copyCount} cop${copyCount === 1 ? 'y' : 'ies'}.`)) return;

    try {
        const deletePromises = copies.map(copy =>
            api.deleteEnv(app.value.uuid, copy.uuid)
        );
        await Promise.all(deletePromises);
        toast.value?.success('Variable Deleted', `${env.key} has been removed (${copyCount} cop${copyCount === 1 ? 'y' : 'ies'})`);
        await fetchEnvs();
    } catch (e) {
        toast.value?.error('Failed to Delete', e.message);
    }
}

// Backup methods
async function fetchBackups() {
    console.log('fetchBackups called, database:', database.value?.uuid);
    console.log('Full database object:', database.value);
    if (!database.value?.uuid) {
        console.log('No database UUID, skipping fetch');
        return;
    }
    loading.value = true;
    try {
        const result = await api.getDatabaseBackups(database.value.uuid);
        console.log('Fetched backups raw result:', result);
        console.log('Result is array?', Array.isArray(result));
        console.log('Result length:', result?.length);
        backups.value = result || [];
        console.log('backups.value after assignment:', backups.value);
        console.log('backups.value length:', backups.value.length);
    } catch (e) {
        console.error('Failed to fetch backups:', e);
        backups.value = [];
    } finally {
        loading.value = false;
        console.log('Loading set to false, backups count:', backups.value.length);
    }
}

async function createBackupSchedule() {
    if (saving.value) return;
    saving.value = true;
    try {
        // Filter out null/undefined values but keep booleans - Coolify API rejects nulls
        const cleanData = {};
        for (const [key, value] of Object.entries(newBackup.value)) {
            if (typeof value === 'boolean') {
                cleanData[key] = value;
            } else if (value !== null && value !== undefined && value !== '') {
                cleanData[key] = value;
            }
        }
        console.log('Creating backup schedule:', cleanData);
        await api.createBackupSchedule(database.value.uuid, cleanData);
        toast.value?.success('Schedule Created', 'Backup schedule has been created');
        newBackup.value = { frequency: '0 0 * * *', enabled: true, save_s3: false };
        showBackupForm.value = false;
        await fetchBackups();
    } catch (e) {
        toast.value?.error('Failed to Create', e.message);
    } finally {
        saving.value = false;
    }
}

async function toggleBackupEnabled(item) {
    saving.value = true;
    try {
        await api.updateBackupSchedule(database.value.uuid, item.schedule.uuid, {
            enabled: !item.schedule.enabled,
        });
        toast.value?.success('Schedule Updated', `Backup schedule ${item.schedule.enabled ? 'disabled' : 'enabled'}`);
        await fetchBackups();
    } catch (e) {
        toast.value?.error('Failed to Update', e.message);
    } finally {
        saving.value = false;
    }
}

async function deleteBackupSchedule(item) {
    if (!confirm('Delete this backup schedule? This will not delete existing backups.')) return;
    try {
        await api.deleteBackupSchedule(database.value.uuid, item.schedule.uuid);
        toast.value?.success('Schedule Deleted', 'Backup schedule has been removed');
        await fetchBackups();
    } catch (e) {
        toast.value?.error('Failed to Delete', e.message);
    }
}

// Settings methods
function startEditingSettings() {
    settingsForm.value = {
        health_check_enabled: app.value.health_check_enabled ?? false,
        health_check_path: app.value.health_check_path || '/health',
        health_check_port: app.value.health_check_port || null,
        health_check_interval: app.value.health_check_interval || 30,
        health_check_timeout: app.value.health_check_timeout || 10,
        health_check_retries: app.value.health_check_retries || 3,
    };
    editingSettings.value = true;
}

async function saveSettings() {
    if (saving.value) return;
    saving.value = true;
    try {
        // Fields that are NOT allowed in Coolify update API
        const excludedFields = ['fqdn'];

        // Filter out null/undefined values but keep booleans
        const cleanData = {};
        for (const [key, value] of Object.entries(settingsForm.value)) {
            if (excludedFields.includes(key)) continue;
            if (typeof value === 'boolean') {
                cleanData[key] = value;
            } else if (value !== null && value !== undefined && value !== '') {
                cleanData[key] = value;
            }
        }
        console.log('Saving app settings:', cleanData);
        await api.updateApplication(app.value.uuid, cleanData);
        toast.value?.success('Settings Saved', 'Application settings have been updated');
        editingSettings.value = false;
        // Refresh stats to get updated data
        if (typeof stats.value?.refresh === 'function') {
            await stats.value.refresh();
        }
    } catch (e) {
        toast.value?.error('Failed to Save', e.message);
    } finally {
        saving.value = false;
    }
}

// Get all executions from all schedules, flattened and sorted by date
function getAllExecutions() {
    const allExecutions = [];
    for (const item of backups.value) {
        if (item.executions && Array.isArray(item.executions)) {
            for (const exec of item.executions) {
                allExecutions.push({
                    ...exec,
                    schedule: item.schedule,
                });
            }
        }
    }
    return allExecutions.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
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
    // Use fallback for non-secure contexts (http:// sites)
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        // Fallback: create temp textarea
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

function describeCron(cron) {
    const preset = cronPresets.find(p => p.value === cron);
    if (preset && preset.value !== 'custom') return preset.label;
    return cron;
}

// Watch for tab changes to load data
watch(activeTab, (tab) => {
    console.log('Tab changed to:', tab);
    if (tab === 'environment') fetchEnvs();
    else if (tab === 'backups') fetchBackups();
});

// Watch stats changes in case database becomes available later
watch(() => stats.value?.databases?.primary, (newDb) => {
    console.log('Database changed:', newDb?.uuid);
    if (newDb && activeTab.value === 'backups') {
        fetchBackups();
    }
}, { deep: true });

onMounted(() => {
    console.log('Configuration mounted, activeTab:', activeTab.value);
    console.log('Stats on mount:', stats.value);
    console.log('Database on mount:', database.value);
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
            <!-- Header with stats -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-zinc-400">
                        {{ uniqueEnvs.length }} variable{{ uniqueEnvs.length !== 1 ? 's' : '' }}
                        <span v-if="duplicateCount > 0" class="text-zinc-500">
                            ({{ duplicateCount }} with build-time copies)
                        </span>
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Toggle for showing build-time duplicates -->
                    <label v-if="duplicateCount > 0" class="flex items-center gap-2 cursor-pointer text-sm">
                        <input
                            type="checkbox"
                            v-model="showBuildTime"
                            class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                        />
                        <span class="text-zinc-400">Show build-time copies</span>
                    </label>
                </div>
            </div>

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
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button @click="showAddForm = false; newEnv = { key: '', value: '' }" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">Cancel</button>
                    <button @click="addEnv" :disabled="!newEnv.key || saving" class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">Add</button>
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
                            <div class="flex items-center justify-end gap-2">
                                <button @click="editingEnv = null" class="text-sm text-zinc-400 hover:text-white">Cancel</button>
                                <button @click="updateEnv(editingEnv)" :disabled="saving" class="rounded-lg bg-violet-600 px-3 py-1 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">Save</button>
                            </div>
                        </div>
                        <!-- Display mode -->
                        <div v-else class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                <code class="text-sm font-medium text-white shrink-0">{{ env.key }}</code>
                                <!-- Build-time indicator (shown when viewing duplicates) -->
                                <span v-if="showBuildTime && isDuplicate(env)" class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400 shrink-0">
                                    Build
                                </span>
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
            <div v-if="!database" class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center">
                <p class="text-sm text-zinc-400">No database configured for backups</p>
            </div>

            <div v-else class="space-y-4">
                <!-- Backup Schedules -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-medium text-white">Backup Schedules</h2>
                            <p class="text-sm text-zinc-400 mt-1">Automated backup schedules for {{ database.name }}</p>
                        </div>
                        <button
                            v-if="!showBackupForm"
                            @click="showBackupForm = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 transition-colors"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Schedule
                        </button>
                    </div>

                    <!-- Create backup form -->
                    <div v-if="showBackupForm" class="border-b border-zinc-800 px-5 py-4 bg-zinc-800/50">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Frequency</label>
                                <select
                                    v-model="newBackup.frequency"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                >
                                    <option v-for="preset in cronPresets.filter(p => p.value !== 'custom')" :key="preset.value" :value="preset.value">
                                        {{ preset.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="newBackup.enabled"
                                        class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                                    />
                                    <span class="text-sm text-zinc-300">Enabled</span>
                                </label>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="newBackup.save_s3"
                                        class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                                    />
                                    <span class="text-sm text-zinc-300">Save to S3</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-end gap-2">
                            <button @click="showBackupForm = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700">Cancel</button>
                            <button @click="createBackupSchedule" :disabled="saving" class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">Create Schedule</button>
                        </div>
                    </div>

                    <div v-if="loading" class="flex items-center justify-center py-12">
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
                    </div>
                    <div v-else-if="backups.length" class="divide-y divide-zinc-800">
                        <div v-for="item in backups" :key="item.schedule?.uuid" class="px-5 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <!-- Toggle enabled -->
                                    <button
                                        @click="toggleBackupEnabled(item)"
                                        :disabled="saving"
                                        class="relative h-6 w-11 rounded-full transition-colors duration-200"
                                        :class="item.schedule?.enabled ? 'bg-violet-600' : 'bg-zinc-700'"
                                    >
                                        <span
                                            class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform duration-200"
                                            :class="item.schedule?.enabled ? 'translate-x-5' : 'translate-x-0'"
                                        ></span>
                                    </button>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-white">{{ describeCron(item.schedule?.frequency) }}</span>
                                            <code class="text-xs text-zinc-500">{{ item.schedule?.frequency }}</code>
                                        </div>
                                        <div class="text-xs text-zinc-400 mt-1">
                                            {{ item.schedule?.save_s3 ? 'S3 Storage' : 'Local Storage' }}
                                            <span v-if="item.executions?.length"> · {{ item.executions.length }} backup{{ item.executions.length !== 1 ? 's' : '' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    @click="deleteBackupSchedule(item)"
                                    class="text-sm text-red-400 hover:text-red-300"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-zinc-500">
                        No backup schedules configured
                    </div>
                </div>

                <!-- Backup History -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
                    <div class="border-b border-zinc-800 px-5 py-4">
                        <h2 class="text-lg font-medium text-white">Backup History</h2>
                    </div>
                    <table v-if="getAllExecutions().length" class="w-full">
                        <thead>
                            <tr class="border-b border-zinc-800 text-left text-sm text-zinc-400">
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Created</th>
                                <th class="px-5 py-3 font-medium">Size</th>
                                <th class="px-5 py-3 font-medium">Filename</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            <tr v-for="exec in getAllExecutions()" :key="exec.uuid" class="hover:bg-zinc-800/50">
                                <td class="px-5 py-4">
                                    <span :class="[statusClass(exec.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">{{ exec.status }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-zinc-300">{{ formatDate(exec.created_at) }}</td>
                                <td class="px-5 py-4 text-sm text-zinc-400">{{ formatSize(exec.size) }}</td>
                                <td class="px-5 py-4 text-sm text-zinc-400 font-mono truncate max-w-xs">{{ exec.filename || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="px-5 py-8 text-center text-sm text-zinc-500">
                        No backup executions yet
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div v-if="activeTab === 'settings'" class="space-y-6">
            <!-- Edit mode -->
            <div v-if="editingSettings" class="space-y-6">
                <!-- Domain Settings -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4">
                        <h2 class="text-lg font-medium text-white">Domain Settings</h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-zinc-300 mb-1.5">FQDN / Domain</label>
                            <input
                                :value="app.fqdn || ''"
                                type="text"
                                disabled
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-700/50 px-3 py-2 text-sm text-zinc-400 cursor-not-allowed"
                            />
                            <p class="text-xs text-zinc-500 mt-1">Domain must be changed in Coolify directly</p>
                        </div>
                    </div>
                </div>

                <!-- Health Check Settings -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4">
                        <h2 class="text-lg font-medium text-white">Health Checks</h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="settingsForm.health_check_enabled"
                                class="h-4 w-4 rounded border-zinc-600 bg-zinc-700 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                            />
                            <span class="text-sm text-zinc-300">Enable health checks</span>
                        </label>

                        <div v-if="settingsForm.health_check_enabled" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Health Check Path</label>
                                <input
                                    v-model="settingsForm.health_check_path"
                                    type="text"
                                    placeholder="/health"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Port (optional)</label>
                                <input
                                    v-model.number="settingsForm.health_check_port"
                                    type="number"
                                    placeholder="Auto"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Interval (seconds)</label>
                                <input
                                    v-model.number="settingsForm.health_check_interval"
                                    type="number"
                                    min="5"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Timeout (seconds)</label>
                                <input
                                    v-model.number="settingsForm.health_check_timeout"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-300 mb-1.5">Retries</label>
                                <input
                                    v-model.number="settingsForm.health_check_retries"
                                    type="number"
                                    min="1"
                                    class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save/Cancel buttons -->
                <div class="flex items-center justify-end gap-3">
                    <button @click="editingSettings = false" class="rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">Cancel</button>
                    <button @click="saveSettings" :disabled="saving" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50">
                        {{ saving ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </div>

            <!-- View mode -->
            <div v-else class="grid gap-6 lg:grid-cols-2">
                <!-- Edit button -->
                <div class="lg:col-span-2 flex justify-end">
                    <button
                        @click="startEditingSettings"
                        class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 transition-colors"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit Settings
                    </button>
                </div>

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

                <!-- Health Checks -->
                <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                    <div class="border-b border-zinc-800 px-5 py-4">
                        <h2 class="text-lg font-medium text-white">Health Checks</h2>
                    </div>
                    <div class="divide-y divide-zinc-800">
                        <div class="px-5 py-4 flex justify-between">
                            <span class="text-sm text-zinc-400">Status</span>
                            <span :class="[app.health_check_enabled ? 'text-emerald-400' : 'text-zinc-500', 'text-sm font-medium']">
                                {{ app.health_check_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div v-if="app.health_check_enabled" class="px-5 py-4 flex justify-between">
                            <span class="text-sm text-zinc-400">Path</span>
                            <code class="text-sm text-zinc-300">{{ app.health_check_path || '/health' }}</code>
                        </div>
                        <div v-if="app.health_check_enabled && app.health_check_port" class="px-5 py-4 flex justify-between">
                            <span class="text-sm text-zinc-400">Port</span>
                            <span class="text-sm text-white">{{ app.health_check_port }}</span>
                        </div>
                        <div v-if="app.health_check_enabled" class="px-5 py-4 flex justify-between">
                            <span class="text-sm text-zinc-400">Interval / Timeout / Retries</span>
                            <span class="text-sm text-white">{{ app.health_check_interval || 30 }}s / {{ app.health_check_timeout || 10 }}s / {{ app.health_check_retries || 3 }}</span>
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
                <div v-if="deployKey" class="rounded-xl border border-zinc-800 bg-zinc-900">
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
    </div>
</template>
