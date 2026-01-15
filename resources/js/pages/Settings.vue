<script setup>
import { inject, computed } from 'vue';

const stats = inject('stats');

const app = computed(() => stats.value?.application || {});
const deployKey = computed(() => stats.value?.deployKey);

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
}
</script>

<template>
    <div class="p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Settings</h1>
            <p class="mt-1 text-sm text-zinc-400">Application configuration (read-only)</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
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
                            <button
                                v-if="app.uuid"
                                @click="copyToClipboard(app.uuid)"
                                class="text-zinc-500 hover:text-white transition-colors"
                            >
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
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Status</span>
                        <span :class="[
                            app.status === 'running' ? 'text-emerald-400' :
                            app.status === 'stopped' ? 'text-red-400' :
                            'text-zinc-400',
                            'text-sm capitalize'
                        ]">{{ app.status || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between" v-if="app.fqdn">
                        <span class="text-sm text-zinc-400">Domain</span>
                        <a :href="app.fqdn" target="_blank" class="text-sm text-violet-400 hover:text-violet-300 transition-colors">
                            {{ app.fqdn.replace(/^https?:\/\//, '') }}
                        </a>
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
                        <a
                            v-if="app.repository"
                            :href="`https://github.com/${app.repository}`"
                            target="_blank"
                            class="text-sm text-violet-400 hover:text-violet-300 transition-colors"
                        >
                            {{ app.repository }}
                        </a>
                        <span v-else class="text-sm text-zinc-400">-</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Branch</span>
                        <span class="text-sm text-white">{{ app.branch || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Commit SHA</span>
                        <code class="text-sm text-zinc-300">{{ app.commit || '-' }}</code>
                    </div>
                </div>
            </div>

            <!-- Project -->
            <div class="rounded-xl border border-zinc-800 bg-zinc-900">
                <div class="border-b border-zinc-800 px-5 py-4">
                    <h2 class="text-lg font-medium text-white">Project</h2>
                </div>
                <div class="divide-y divide-zinc-800">
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Project</span>
                        <span class="text-sm text-white">{{ app.project_name || '-' }}</span>
                    </div>
                    <div class="px-5 py-4 flex justify-between">
                        <span class="text-sm text-zinc-400">Environment</span>
                        <span class="text-sm text-white">{{ app.environment_name || '-' }}</span>
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
                        <button
                            @click="copyToClipboard(deployKey.public_key)"
                            class="p-2 text-zinc-500 hover:text-white transition-colors"
                        >
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
