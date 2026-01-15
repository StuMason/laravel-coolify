<script setup>
import { ref, onMounted, inject, computed } from 'vue';

const stats = inject('stats');
const api = inject('api');

const backups = ref([]);
const loading = ref(true);
const triggering = ref(false);

const database = computed(() => stats.value?.databases?.primary);

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

onMounted(fetchBackups);
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Backups</h1>
                <p class="mt-1 text-sm text-zinc-400">Database backup history</p>
            </div>
            <button
                v-if="database"
                @click="triggerBackup"
                :disabled="triggering"
                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
            >
                <svg v-if="triggering" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                </svg>
                Backup Now
            </button>
        </div>

        <div v-if="!database" class="rounded-xl border border-zinc-800 bg-zinc-900 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
            </svg>
            <p class="mt-4 text-sm text-zinc-400">No database configured for backups</p>
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
                    <tr v-for="backup in backups" :key="backup.id" class="hover:bg-zinc-800/50 transition-colors">
                        <td class="px-5 py-4">
                            <span :class="[statusClass(backup.status), 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize']">
                                {{ backup.status }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-sm text-zinc-300">{{ formatDate(backup.created_at) }}</td>
                        <td class="px-5 py-4 text-sm text-zinc-400">{{ formatSize(backup.size) }}</td>
                        <td class="px-5 py-4 text-sm text-zinc-400 font-mono">{{ backup.filename || '-' }}</td>
                    </tr>
                </tbody>
            </table>
            <div v-else class="px-5 py-12 text-center text-sm text-zinc-500">
                No backups yet
            </div>
        </div>
    </div>
</template>
