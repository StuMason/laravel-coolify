<script setup>
import { ref, onMounted, inject, computed } from 'vue';

const stats = inject('stats');
const api = inject('api');

const envs = ref([]);
const loading = ref(true);
const saving = ref(false);
const showAddForm = ref(false);

const newEnv = ref({ key: '', value: '', is_build_time: false });
const editingEnv = ref(null);

const appUuid = computed(() => stats.value?.application?.uuid);

async function fetchEnvs() {
    if (!appUuid.value) return;
    loading.value = true;
    try {
        envs.value = await api.getEnvs(appUuid.value);
    } catch (e) {
        console.error('Failed to fetch envs:', e);
    } finally {
        loading.value = false;
    }
}

async function addEnv() {
    if (!newEnv.value.key || saving.value) return;
    saving.value = true;
    try {
        await api.createEnv(appUuid.value, newEnv.value);
        newEnv.value = { key: '', value: '', is_build_time: false };
        showAddForm.value = false;
        await fetchEnvs();
    } catch (e) {
        console.error('Failed to add env:', e);
    } finally {
        saving.value = false;
    }
}

async function updateEnv(env) {
    saving.value = true;
    try {
        await api.updateEnv(appUuid.value, env.uuid, {
            key: env.key,
            value: env.value,
            is_build_time: env.is_build_time,
        });
        editingEnv.value = null;
        await fetchEnvs();
    } catch (e) {
        console.error('Failed to update env:', e);
    } finally {
        saving.value = false;
    }
}

async function deleteEnv(env) {
    if (!confirm(`Delete ${env.key}?`)) return;
    try {
        await api.deleteEnv(appUuid.value, env.uuid);
        await fetchEnvs();
    } catch (e) {
        console.error('Failed to delete env:', e);
    }
}

function startEdit(env) {
    editingEnv.value = { ...env };
}

function cancelEdit() {
    editingEnv.value = null;
}

onMounted(fetchEnvs);
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Environment Variables</h1>
                <p class="mt-1 text-sm text-zinc-400">Manage application environment configuration</p>
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
                    <input
                        v-model="newEnv.is_build_time"
                        type="checkbox"
                        class="h-4 w-4 rounded border-zinc-700 bg-zinc-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                    />
                    Build-time variable
                </label>
                <div class="flex items-center gap-2">
                    <button
                        @click="showAddForm = false"
                        class="rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-zinc-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="addEnv"
                        :disabled="!newEnv.key || saving"
                        class="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50 transition-colors"
                    >
                        Add
                    </button>
                </div>
            </div>
        </div>

        <!-- Env list -->
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden">
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-zinc-700 border-t-violet-500"></div>
            </div>
            <div v-else-if="envs.length" class="divide-y divide-zinc-800">
                <div
                    v-for="env in envs"
                    :key="env.uuid"
                    class="px-5 py-4"
                >
                    <!-- Editing mode -->
                    <div v-if="editingEnv?.uuid === env.uuid" class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input
                                v-model="editingEnv.key"
                                type="text"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            />
                            <input
                                v-model="editingEnv.value"
                                type="text"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-zinc-400">
                                <input
                                    v-model="editingEnv.is_build_time"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-zinc-700 bg-zinc-800 text-violet-600 focus:ring-violet-500 focus:ring-offset-zinc-900"
                                />
                                Build-time
                            </label>
                            <div class="flex items-center gap-2">
                                <button @click="cancelEdit" class="text-sm text-zinc-400 hover:text-white">Cancel</button>
                                <button
                                    @click="updateEnv(editingEnv)"
                                    :disabled="saving"
                                    class="rounded-lg bg-violet-600 px-3 py-1 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-50"
                                >
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Display mode -->
                    <div v-else class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <code class="text-sm font-medium text-white">{{ env.key }}</code>
                            <code class="text-sm text-zinc-400 truncate max-w-md">{{ env.value || '(empty)' }}</code>
                            <span
                                v-if="env.is_build_time"
                                class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400"
                            >
                                Build
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="startEdit(env)"
                                class="text-sm text-zinc-400 hover:text-white transition-colors"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteEnv(env)"
                                class="text-sm text-red-400 hover:text-red-300 transition-colors"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-12 text-center text-sm text-zinc-500">
                No environment variables configured
            </div>
        </div>
    </div>
</template>
