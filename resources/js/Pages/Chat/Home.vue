<script setup>
import { computed } from 'vue';
import { usePage, Head } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const channels = computed(() => page.props.sidebar?.channels ?? []);
const directMessages = computed(() => page.props.sidebar?.directMessages ?? []);
const isNew = computed(() => !channels.value.length && !directMessages.value.length);

// Conversations with something new first
const recent = computed(() => [
    ...directMessages.value.map((dm) => ({ id: dm.id, label: dm.partner?.name ?? 'Unknown', icon: '💬', unread: dm.unread })),
    ...channels.value.map((ch) => ({ id: ch.id, label: ch.name, icon: ch.type === 'private' ? '🔒' : '#', unread: ch.unread })),
].sort((a, b) => b.unread - a.unread));
</script>

<template>
    <Head title="Home" />
    <ChatLayout>
        <div class="flex-1 overflow-y-auto">
            <div class="max-w-2xl mx-auto px-6 py-12">
                <h1 class="text-2xl font-semibold text-gray-900">Welcome{{ isNew ? '' : ' back' }}, {{ user?.name }} 👋</h1>

                <template v-if="isNew">
                    <p class="mt-2 text-sm text-gray-500">You're not in any conversations yet. Here's how to get started:</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <a href="/channels" class="getting-started block bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition">
                            <p class="text-2xl">#</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">Browse channels</p>
                            <p class="mt-1 text-xs text-gray-500">Join a public channel and say hello.</p>
                        </a>
                        <a href="/channels?create=1" class="getting-started block bg-white rounded-xl border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition">
                            <p class="text-2xl">＋</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">Create a channel</p>
                            <p class="mt-1 text-xs text-gray-500">Start a public or private space.</p>
                        </a>
                        <div class="getting-started bg-white rounded-xl border border-gray-200 p-5">
                            <p class="text-2xl">💬</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900">Message someone</p>
                            <p class="mt-1 text-xs text-gray-500">Use the + next to "Direct messages" in the sidebar.</p>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p class="mt-2 text-sm text-gray-500">Pick up where you left off.</p>

                    <ul class="mt-6 bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                        <li v-for="item in recent" :key="item.id">
                            <a :href="`/channels/${item.id}`" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
                                <span class="w-6 text-center text-gray-400">{{ item.icon }}</span>
                                <span class="flex-1 text-sm" :class="item.unread ? 'font-semibold text-gray-900' : 'text-gray-700'">{{ item.label }}</span>
                                <span
                                    v-if="item.unread"
                                    class="min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
                                >{{ item.unread }} new</span>
                            </a>
                        </li>
                    </ul>
                </template>
            </div>
        </div>
    </ChatLayout>
</template>
