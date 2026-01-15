<script setup>
import { inject, computed, ref, provide } from 'vue';
import { useRoute, RouterLink, RouterView } from 'vue-router';
import CommandPalette from './CommandPalette.vue';
import Toast from './Toast.vue';
import EnvironmentSwitcher from './EnvironmentSwitcher.vue';

const route = useRoute();
const stats = inject('stats');
const loading = inject('loading');

const commandPalette = ref(null);
const toast = ref(null);

// Provide toast to all children
provide('toast', toast);

function handleAction(action) {
    if (action.success) {
        toast.value?.success(action.message);
    } else {
        toast.value?.error('Action Failed', action.message);
    }
}

const coolifyUrl = computed(() => {
    const baseUrl = window.Coolify?.coolifyUrl || stats.value?.coolify_url;
    if (!baseUrl) return null;
    return baseUrl.replace(/\/$/, '');
});

const appCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !stats.value?.application?.uuid) return null;
    const projectUuid = stats.value?.project?.uuid;
    const envName = stats.value?.environment?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/application/${stats.value.application.uuid}`;
    }
    return `${coolifyUrl.value}/application/${stats.value.application.uuid}`;
});

const navigation = [
    { name: 'Dashboard', path: '/dashboard', icon: 'home' },
    { name: 'Deployments', path: '/deployments', icon: 'rocket' },
    { name: 'Configuration', path: '/configuration', icon: 'cog' },
    { name: 'Logs', path: '/logs', icon: 'terminal' },
];

const appName = computed(() => stats.value?.application?.name || 'Loading...');
const appStatus = computed(() => stats.value?.application?.status || 'unknown');

const statusColor = computed(() => {
    const status = appStatus.value?.toLowerCase();
    if (status === 'running' || status === 'running:healthy' || status === 'healthy') return 'bg-emerald-500';
    if (status === 'running:unhealthy' || status === 'unhealthy') return 'bg-amber-500';
    if (status === 'stopped' || status === 'exited' || status === 'error' || status === 'failed' || status === 'exited:unhealthy') return 'bg-red-500';
    if (status === 'building' || status === 'starting' || status === 'stopping' || status === 'restarting') return 'bg-blue-500';
    return 'bg-zinc-500';
});

function isActive(path) {
    if (path === '/dashboard') {
        return route.path === '/dashboard' || route.path === '/';
    }
    return route.path.startsWith(path);
}
</script>

<template>
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside class="flex w-64 flex-col border-r border-zinc-800 bg-zinc-900">
            <!-- App header -->
            <div class="flex h-16 items-center border-b border-zinc-800 px-4">
                <div class="flex-1 truncate">
                    <div class="truncate text-sm font-semibold text-white">{{ appName }}</div>
                    <div class="flex items-center gap-1.5">
                        <span :class="[statusColor, 'h-2 w-2 rounded-full']"></span>
                        <span class="text-xs text-zinc-400 capitalize">{{ appStatus }}</span>
                    </div>
                </div>
            </div>

            <!-- Environment Switcher -->
            <div class="border-b border-zinc-800 p-2">
                <EnvironmentSwitcher />
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-1 p-3">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.path"
                    :to="item.path"
                    :class="[
                        isActive(item.path)
                            ? 'bg-zinc-800 text-white'
                            : 'text-zinc-400 hover:bg-zinc-800/50 hover:text-white',
                        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    ]"
                >
                    <!-- Icons -->
                    <svg v-if="item.icon === 'home'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <svg v-else-if="item.icon === 'rocket'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                    </svg>
                    <svg v-else-if="item.icon === 'database'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    <svg v-else-if="item.icon === 'key'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                    <svg v-else-if="item.icon === 'archive'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <svg v-else-if="item.icon === 'terminal'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <svg v-else-if="item.icon === 'cog'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    {{ item.name }}
                </RouterLink>
            </nav>

            <!-- Footer -->
            <div class="border-t border-zinc-800 p-4 space-y-3">
                <!-- Live Site Link -->
                <a
                    :href="stats?.application?.fqdn"
                    target="_blank"
                    class="flex items-center gap-2 text-sm text-zinc-400 hover:text-white transition-colors"
                    v-if="stats?.application?.fqdn"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                    </svg>
                    <span class="truncate">{{ stats.application.fqdn.replace(/^https?:\/\//, '') }}</span>
                </a>

                <!-- Coolify Dashboard Link -->
                <a
                    v-if="appCoolifyUrl"
                    :href="appCoolifyUrl"
                    target="_blank"
                    class="flex items-center gap-2 text-sm text-zinc-400 hover:text-violet-400 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    <span>Open in Coolify</span>
                </a>

                <!-- Command Palette hint -->
                <button
                    @click="commandPalette?.open()"
                    class="flex w-full items-center gap-2 rounded-lg border border-zinc-700 bg-zinc-800/50 px-3 py-2 text-sm text-zinc-400 hover:bg-zinc-800 hover:text-white transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <span class="flex-1 text-left">Quick actions...</span>
                    <kbd class="rounded border border-zinc-600 px-1.5 py-0.5 text-xs">⌘K</kbd>
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-auto bg-zinc-950">
            <div v-if="loading" class="flex h-full items-center justify-center">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
            </div>
            <RouterView v-else />
        </main>
    </div>

    <!-- Command Palette -->
    <CommandPalette ref="commandPalette" @action="handleAction" />

    <!-- Toast Notifications -->
    <Toast ref="toast" />
</template>
