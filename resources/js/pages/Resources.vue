<script setup>
import { inject, computed, ref } from 'vue';

const stats = inject('stats');
const api = inject('api');
const refreshStats = inject('refreshStats');

const database = computed(() => stats.value?.databases?.primary);
const redis = computed(() => stats.value?.databases?.redis);

const dbLoading = ref(false);
const redisLoading = ref(false);

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
        await refreshStats();
    } catch (e) {
        console.error(`Database ${action} failed:`, e);
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
        await refreshStats();
    } catch (e) {
        console.error(`Redis ${action} failed:`, e);
    } finally {
        redisLoading.value = false;
    }
}

function statusClass(status) {
    const s = status?.toLowerCase();
    // Running/healthy states - green
    if (s === 'running' || s === 'running:healthy' || s === 'healthy') {
        return 'bg-emerald-500/10 text-emerald-400';
    }
    // Unhealthy - amber/warning
    if (s === 'running:unhealthy' || s === 'unhealthy') {
        return 'bg-amber-500/10 text-amber-400';
    }
    // Stopped/error states - red
    if (s === 'stopped' || s === 'exited' || s === 'error' || s === 'failed') {
        return 'bg-red-500/10 text-red-400';
    }
    // Transitioning states - blue
    if (s === 'starting' || s === 'stopping' || s === 'restarting' || s === 'building') {
        return 'bg-blue-500/10 text-blue-400';
    }
    // Unknown/other
    return 'bg-zinc-500/10 text-zinc-400';
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    // Clean up status display
    return status.replace('running:', '').replace('_', ' ');
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
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-zinc-400">Internal Host</div>
                            <code class="text-zinc-300 text-xs break-all">{{ database.internal_db_url || '-' }}</code>
                        </div>
                        <div>
                            <div class="text-zinc-400">Public Port</div>
                            <code class="text-zinc-300">{{ database.public_port || 'Not exposed' }}</code>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
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
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-zinc-400">Internal Host</div>
                            <code class="text-zinc-300 text-xs break-all">{{ redis.internal_db_url || '-' }}</code>
                        </div>
                        <div>
                            <div class="text-zinc-400">Public Port</div>
                            <code class="text-zinc-300">{{ redis.public_port || 'Not exposed' }}</code>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pt-2">
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
    </div>
</template>
