<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    // The conversation currently open, if any — its activity is handled by the page itself
    activeChannelId: { type: Number, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// ─── Sidebar state (server snapshot, then kept live by activity events) ─────
const channels = ref([]);
const directMessages = ref([]);

watch(
    () => page.props.sidebar,
    (sidebar) => {
        channels.value = (sidebar?.channels ?? []).map((c) => ({ ...c }));
        directMessages.value = (sidebar?.directMessages ?? []).map((d) => ({ ...d }));
    },
    { immediate: true },
);

function closeConversation(id) {
    router.post(`/channels/${id}/close`);
}

// ─── New direct message: people search ───────────────────────────────────────
const showPeopleSearch = ref(false);
const peopleQuery = ref('');
const peopleResults = ref([]);
let searchTimer = null;

watch(peopleQuery, (q) => {
    clearTimeout(searchTimer);
    if (!q.trim()) {
        peopleResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        const response = await fetch(`/users/search?q=${encodeURIComponent(q.trim())}`, {
            headers: { 'Accept': 'application/json' },
        });
        peopleResults.value = response.ok ? await response.json() : [];
    }, 250);
});

function startDirectMessage(person) {
    router.post('/direct-messages', { user_id: person.id });
}

// ─── Pop-up alerts ───────────────────────────────────────────────────────────
const toasts = ref([]);
let toastSeq = 0;

function showToast(toast) {
    const id = ++toastSeq;
    toasts.value = [...toasts.value.slice(-2), { id, ...toast }];
    setTimeout(() => dismissToast(id), 6000);
}

function dismissToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

// ─── Live activity on the user's personal channel ────────────────────────────
function onActivity(event) {
    if (event.channel_id === props.activeChannelId) return;

    if (event.channel_type === 'direct') {
        let dm = directMessages.value.find((d) => d.id === event.channel_id);
        if (!dm) {
            // A new conversation, or one this user had closed
            dm = { id: event.channel_id, partner: event.sender, unread: 0 };
            directMessages.value.unshift(dm);
        }
        dm.unread += 1;
        showToast({
            title: event.sender.name,
            body: event.preview,
            href: `/channels/${event.channel_id}`,
        });
    } else {
        const channel = channels.value.find((c) => c.id === event.channel_id);
        if (channel) channel.unread += 1;
    }
}

let personalChannel = null;

onMounted(() => {
    if (!window.Echo || !user.value) return;
    personalChannel = `App.Models.User.${user.value.id}`;
    window.Echo.private(personalChannel).listen('.channel.activity', onActivity);
});

onUnmounted(() => {
    if (personalChannel) window.Echo.leave(personalChannel);
    clearTimeout(searchTimer);
});

defineExpose({ onActivity });
</script>

<template>
    <div class="flex h-screen bg-gray-100 overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-gray-100 flex flex-col">
            <!-- App branding -->
            <div class="h-16 flex items-center px-4 border-b border-gray-700">
                <a href="/chat" class="text-lg font-semibold tracking-tight">Laravel Chat</a>
            </div>

            <nav class="flex-1 overflow-y-auto py-2">
                <a
                    href="/chat"
                    class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
                >⌂ Home</a>

                <!-- Channels -->
                <div class="flex items-center justify-between px-4 pt-4 pb-1">
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Channels</p>
                    <a href="/channels?create=1" class="text-gray-400 hover:text-white text-lg leading-none" title="Create channel">+</a>
                </div>
                <div
                    v-for="ch in channels"
                    :key="ch.id"
                    class="sidebar-channel group flex items-center pr-2 transition-colors"
                    :class="ch.id === activeChannelId ? 'bg-gray-700' : 'hover:bg-gray-800'"
                >
                    <a
                        :href="`/channels/${ch.id}`"
                        class="flex-1 min-w-0 flex items-center gap-2 pl-4 py-2 text-sm"
                        :class="ch.unread && ch.id !== activeChannelId ? 'text-white font-semibold' : 'text-gray-300'"
                    >
                        <span class="text-gray-500">{{ ch.type === 'private' ? '🔒' : '#' }}</span>
                        <span class="truncate">{{ ch.name }}</span>
                    </a>
                    <span
                        v-if="ch.unread && ch.id !== activeChannelId"
                        class="unread-badge min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
                    >{{ ch.unread > 99 ? '99+' : ch.unread }}</span>
                    <button
                        type="button"
                        @click="closeConversation(ch.id)"
                        class="close-button ml-1 w-5 h-5 rounded text-gray-500 hover:text-white hover:bg-gray-600 opacity-0 group-hover:opacity-100 focus:opacity-100 text-xs"
                        :aria-label="`Close ${ch.name}`"
                        title="Leave channel"
                    >✕</button>
                </div>
                <p v-if="!channels.length" class="px-4 py-1 text-xs text-gray-500 italic">No channels yet</p>
                <a href="/channels" class="block px-4 py-1 text-xs text-indigo-400 hover:text-indigo-300">Browse all channels</a>

                <!-- Direct messages -->
                <div class="flex items-center justify-between px-4 pt-4 pb-1">
                    <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold">Direct messages</p>
                    <button
                        type="button"
                        @click="showPeopleSearch = !showPeopleSearch"
                        class="text-gray-400 hover:text-white text-lg leading-none"
                        title="New message"
                    >+</button>
                </div>
                <div v-if="showPeopleSearch" class="px-4 pb-2">
                    <input
                        v-model="peopleQuery"
                        type="search"
                        placeholder="Find someone by name"
                        class="w-full rounded-md bg-gray-800 border border-gray-700 px-2 py-1 text-xs text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                    <button
                        v-for="person in peopleResults"
                        :key="person.id"
                        type="button"
                        @click="startDirectMessage(person)"
                        class="person-result w-full text-left px-2 py-1 mt-1 rounded text-xs text-gray-200 hover:bg-gray-700"
                    >{{ person.name }}</button>
                    <p v-if="peopleQuery.trim() && !peopleResults.length" class="mt-1 text-xs text-gray-500">No one found</p>
                </div>
                <div
                    v-for="dm in directMessages"
                    :key="dm.id"
                    class="sidebar-dm group flex items-center pr-2 transition-colors"
                    :class="dm.id === activeChannelId ? 'bg-gray-700' : 'hover:bg-gray-800'"
                >
                    <a
                        :href="`/channels/${dm.id}`"
                        class="flex-1 min-w-0 flex items-center gap-2 pl-4 py-2 text-sm"
                        :class="dm.unread && dm.id !== activeChannelId ? 'text-white font-semibold' : 'text-gray-300'"
                    >
                        <span class="w-5 h-5 rounded-full bg-gray-600 flex items-center justify-center text-[10px] shrink-0">
                            {{ dm.partner?.name?.[0]?.toUpperCase() ?? '?' }}
                        </span>
                        <span class="truncate">{{ dm.partner?.name ?? 'Unknown' }}</span>
                    </a>
                    <span
                        v-if="dm.unread && dm.id !== activeChannelId"
                        class="unread-badge min-w-5 h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center"
                    >{{ dm.unread > 99 ? '99+' : dm.unread }}</span>
                    <button
                        type="button"
                        @click="closeConversation(dm.id)"
                        class="close-button ml-1 w-5 h-5 rounded text-gray-500 hover:text-white hover:bg-gray-600 opacity-0 group-hover:opacity-100 focus:opacity-100 text-xs"
                        :aria-label="`Close conversation with ${dm.partner?.name}`"
                        title="Close and delete history"
                    >✕</button>
                </div>
                <p v-if="!directMessages.length && !showPeopleSearch" class="px-4 py-1 text-xs text-gray-500 italic">No conversations yet</p>
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

        <!-- Pop-up alerts for direct messages (top-right, clear of the message box and send button) -->
        <div class="fixed top-20 right-4 z-50 flex flex-col gap-2 w-80">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="toast bg-white rounded-xl shadow-lg border border-gray-200 p-3 flex gap-3 items-start"
                role="status"
            >
                <a :href="toast.href" class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900">💬 {{ toast.title }}</p>
                    <p class="text-xs text-gray-600 truncate">{{ toast.body }}</p>
                </a>
                <button
                    type="button"
                    @click="dismissToast(toast.id)"
                    class="text-gray-400 hover:text-gray-600 text-xs"
                    aria-label="Dismiss"
                >✕</button>
            </div>
        </div>
    </div>
</template>
