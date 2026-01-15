<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const toasts = ref([]);
let id = 0;

function add(toast) {
    const newToast = {
        id: ++id,
        type: toast.type || 'info',
        title: toast.title,
        message: toast.message,
        duration: toast.duration || 4000,
    };
    toasts.value.push(newToast);

    if (newToast.duration > 0) {
        setTimeout(() => remove(newToast.id), newToast.duration);
    }
}

function remove(toastId) {
    toasts.value = toasts.value.filter(t => t.id !== toastId);
}

function success(title, message) {
    add({ type: 'success', title, message });
}

function error(title, message) {
    add({ type: 'error', title, message });
}

function info(title, message) {
    add({ type: 'info', title, message });
}

function warning(title, message) {
    add({ type: 'warning', title, message });
}

defineExpose({ add, remove, success, error, info, warning });
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
            <TransitionGroup
                enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transform transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[
                        'pointer-events-auto w-80 rounded-lg border p-4 shadow-lg backdrop-blur-sm',
                        toast.type === 'success' ? 'border-emerald-500/20 bg-emerald-500/10' : '',
                        toast.type === 'error' ? 'border-red-500/20 bg-red-500/10' : '',
                        toast.type === 'warning' ? 'border-amber-500/20 bg-amber-500/10' : '',
                        toast.type === 'info' ? 'border-blue-500/20 bg-blue-500/10' : '',
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <!-- Icon -->
                        <div :class="[
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                            toast.type === 'success' ? 'bg-emerald-500/20' : '',
                            toast.type === 'error' ? 'bg-red-500/20' : '',
                            toast.type === 'warning' ? 'bg-amber-500/20' : '',
                            toast.type === 'info' ? 'bg-blue-500/20' : '',
                        ]">
                            <svg v-if="toast.type === 'success'" :class="['h-4 w-4 text-emerald-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <svg v-else-if="toast.type === 'error'" :class="['h-4 w-4 text-red-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <svg v-else-if="toast.type === 'warning'" :class="['h-4 w-4 text-amber-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <svg v-else :class="['h-4 w-4 text-blue-400']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p :class="[
                                'text-sm font-medium',
                                toast.type === 'success' ? 'text-emerald-300' : '',
                                toast.type === 'error' ? 'text-red-300' : '',
                                toast.type === 'warning' ? 'text-amber-300' : '',
                                toast.type === 'info' ? 'text-blue-300' : '',
                            ]">{{ toast.title }}</p>
                            <p v-if="toast.message" class="mt-0.5 text-sm text-zinc-400">{{ toast.message }}</p>
                        </div>

                        <button @click="remove(toast.id)" class="shrink-0 text-zinc-500 hover:text-white transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
