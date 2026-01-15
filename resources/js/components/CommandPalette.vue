<script setup>
import { ref, computed, onMounted, onUnmounted, watch, inject } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const api = inject('api');
const stats = inject('stats');
const refreshStats = inject('refreshStats');

const isOpen = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const loading = ref(false);

const emit = defineEmits(['action']);

const app = computed(() => stats.value?.application);
const database = computed(() => stats.value?.databases?.primary);
const cache = computed(() => stats.value?.databases?.redis);

// Coolify URL computation
const coolifyUrl = computed(() => {
    const baseUrl = window.Coolify?.coolifyUrl || stats.value?.coolify_url;
    if (!baseUrl) return null;
    return baseUrl.replace(/\/$/, '');
});

const appCoolifyUrl = computed(() => {
    if (!coolifyUrl.value || !app.value?.uuid) return null;
    const projectUuid = stats.value?.project?.uuid;
    const envName = stats.value?.environment?.name || 'production';
    if (projectUuid) {
        return `${coolifyUrl.value}/project/${projectUuid}/${envName}/application/${app.value.uuid}`;
    }
    return `${coolifyUrl.value}/application/${app.value.uuid}`;
});

const commands = computed(() => {
    const cmds = [
        { id: 'deploy', name: 'Deploy', description: 'Trigger a new deployment', icon: 'rocket', action: 'deploy', shortcut: 'D' },
        { id: 'restart', name: 'Restart Application', description: 'Restart the application container', icon: 'refresh', action: 'restart', shortcut: 'R' },
        { id: 'logs', name: 'View Logs', description: 'Open application logs', icon: 'terminal', action: () => router.push('/logs') },
        { id: 'coolify', name: 'Open in Coolify', description: 'Open Coolify dashboard', icon: 'external', action: () => appCoolifyUrl.value && window.open(appCoolifyUrl.value, '_blank') },
        { id: 'site', name: 'Open Live Site', description: app.value?.fqdn, icon: 'globe', action: () => app.value?.fqdn && window.open(app.value.fqdn, '_blank') },
        { id: 'github', name: 'Open GitHub', description: app.value?.repository, icon: 'github', action: () => app.value?.repository && window.open(`https://github.com/${app.value.repository}`, '_blank') },
    ];

    if (database.value) {
        cmds.push({ id: 'restart-db', name: 'Restart Database', description: database.value.name, icon: 'database', action: 'restart-db' });
    }
    if (cache.value) {
        cmds.push({ id: 'restart-cache', name: 'Restart Cache', description: cache.value.name, icon: 'bolt', action: 'restart-cache' });
    }

    cmds.push(
        { id: 'vars', name: 'Variables', description: 'Manage environment variables', icon: 'key', action: () => router.push('/configuration?tab=environment') },
        { id: 'backups', name: 'Backups', description: 'View database backups', icon: 'archive', action: () => router.push('/configuration?tab=backups') },
        { id: 'settings', name: 'Settings', description: 'View application settings', icon: 'cog', action: () => router.push('/configuration?tab=settings') },
    );

    return cmds;
});

const filteredCommands = computed(() => {
    if (!query.value) return commands.value;
    const q = query.value.toLowerCase();
    return commands.value.filter(cmd =>
        cmd.name.toLowerCase().includes(q) ||
        cmd.description?.toLowerCase().includes(q)
    );
});

watch(query, () => {
    selectedIndex.value = 0;
});

async function executeCommand(cmd) {
    if (typeof cmd.action === 'function') {
        cmd.action();
        close();
        return;
    }

    loading.value = true;
    try {
        switch (cmd.action) {
            case 'deploy':
                await api.deployApplication(app.value.uuid);
                emit('action', { type: 'deploy', success: true, message: 'Deployment started' });
                break;
            case 'restart':
                await api.restartApplication(app.value.uuid);
                emit('action', { type: 'restart', success: true, message: 'Application restarting' });
                break;
            case 'restart-db':
                await api.restartDatabase(database.value.uuid);
                emit('action', { type: 'restart-db', success: true, message: 'Database restarting' });
                break;
            case 'restart-cache':
                await api.restartDatabase(cache.value.uuid);
                emit('action', { type: 'restart-cache', success: true, message: 'Cache restarting' });
                break;
        }
        await refreshStats();
    } catch (e) {
        emit('action', { type: cmd.action, success: false, message: e.message });
    } finally {
        loading.value = false;
        close();
    }
}

function open() {
    isOpen.value = true;
    query.value = '';
    selectedIndex.value = 0;
}

function close() {
    isOpen.value = false;
}

function handleKeydown(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        isOpen.value ? close() : open();
    }

    if (!isOpen.value) return;

    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(selectedIndex.value + 1, filteredCommands.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredCommands.value[selectedIndex.value]) {
            executeCommand(filteredCommands.value[selectedIndex.value]);
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});

defineExpose({ open, close });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 md:p-20" @click.self="close">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-zinc-900/80 backdrop-blur-sm" @click="close"></div>

                <!-- Dialog -->
                <Transition
                    enter-active-class="duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="isOpen" class="relative mx-auto max-w-xl transform rounded-xl bg-zinc-800 shadow-2xl ring-1 ring-zinc-700 transition-all">
                        <!-- Search input -->
                        <div class="flex items-center border-b border-zinc-700 px-4">
                            <svg class="h-5 w-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input
                                v-model="query"
                                class="h-14 w-full border-0 bg-transparent px-4 text-white placeholder-zinc-400 focus:outline-none focus:ring-0"
                                placeholder="Search commands..."
                                autofocus
                            />
                            <kbd class="hidden sm:inline-flex items-center rounded border border-zinc-600 px-2 py-1 text-xs text-zinc-400">esc</kbd>
                        </div>

                        <!-- Loading overlay -->
                        <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-zinc-800/80 rounded-xl z-10">
                            <div class="h-8 w-8 animate-spin rounded-full border-2 border-zinc-600 border-t-violet-500"></div>
                        </div>

                        <!-- Results -->
                        <ul class="max-h-80 overflow-y-auto py-2">
                            <li
                                v-for="(cmd, idx) in filteredCommands"
                                :key="cmd.id"
                                @click="executeCommand(cmd)"
                                @mouseenter="selectedIndex = idx"
                                :class="[
                                    idx === selectedIndex ? 'bg-zinc-700/50' : '',
                                    'flex cursor-pointer items-center gap-3 px-4 py-3 transition-colors'
                                ]"
                            >
                                <!-- Icon -->
                                <div :class="[idx === selectedIndex ? 'bg-violet-500/20 text-violet-400' : 'bg-zinc-700 text-zinc-400', 'flex h-10 w-10 items-center justify-center rounded-lg transition-colors']">
                                    <svg v-if="cmd.icon === 'rocket'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'refresh'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'terminal'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'external'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'globe'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'github'" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'database'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'bolt'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'key'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                    </svg>
                                    <svg v-else-if="cmd.icon === 'archive'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    </svg>
                                </div>

                                <!-- Text -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-white">{{ cmd.name }}</span>
                                        <kbd v-if="cmd.shortcut" class="hidden sm:inline-flex items-center rounded border border-zinc-600 px-1.5 py-0.5 text-xs text-zinc-400">{{ cmd.shortcut }}</kbd>
                                    </div>
                                    <div class="text-xs text-zinc-400 truncate">{{ cmd.description }}</div>
                                </div>

                                <!-- Arrow -->
                                <svg class="h-4 w-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </li>

                            <li v-if="!filteredCommands.length" class="px-4 py-8 text-center text-sm text-zinc-400">
                                No commands found
                            </li>
                        </ul>

                        <!-- Footer -->
                        <div class="flex items-center justify-between border-t border-zinc-700 px-4 py-3 text-xs text-zinc-400">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1">
                                    <kbd class="rounded border border-zinc-600 px-1.5 py-0.5">↑</kbd>
                                    <kbd class="rounded border border-zinc-600 px-1.5 py-0.5">↓</kbd>
                                    to navigate
                                </span>
                                <span class="flex items-center gap-1">
                                    <kbd class="rounded border border-zinc-600 px-1.5 py-0.5">↵</kbd>
                                    to select
                                </span>
                            </div>
                            <span class="flex items-center gap-1">
                                <kbd class="rounded border border-zinc-600 px-1.5 py-0.5">⌘</kbd>
                                <kbd class="rounded border border-zinc-600 px-1.5 py-0.5">K</kbd>
                                to toggle
                            </span>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
