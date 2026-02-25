<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="flex h-screen bg-gray-100 overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-gray-100 flex flex-col">
            <!-- App branding -->
            <div class="h-16 flex items-center px-4 border-b border-gray-700">
                <span class="text-lg font-semibold tracking-tight">Laravel Chat</span>
            </div>

            <!-- Channel list slot -->
            <nav class="flex-1 overflow-y-auto py-2">
                <slot name="sidebar" />
            </nav>

            <!-- User info + logout -->
            <div class="border-t border-gray-700 p-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-sm font-semibold select-none">
                    {{ user?.name?.[0]?.toUpperCase() ?? '?' }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ user?.name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ user?.email }}</p>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-gray-400 hover:text-white transition-colors text-xs"
                >
                    Out
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 flex flex-col overflow-hidden">
            <slot />
        </main>
    </div>
</template>
