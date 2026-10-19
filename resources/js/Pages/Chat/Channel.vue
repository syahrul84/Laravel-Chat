<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { usePage, router, useForm, Head } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';

const props = defineProps({
    channel: Object,
    messages: Object,   // LengthAwarePaginator JSON
    members: { type: Array, default: () => [] },
    partner: { type: Object, default: null },  // the other person, for a direct message
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

const isDirect = computed(() => props.channel.type === 'direct');
const title = computed(() => (isDirect.value ? props.partner?.name ?? 'Direct message' : props.channel.name));

// ─── Members ─────────────────────────────────────────────────────────────────
const showMembers = ref(false);
const onlineIds = ref(new Set());

function startDirectMessage(person) {
    if (person.id === user.value?.id) return;
    router.post('/direct-messages', { user_id: person.id });
}

// ─── Messages ────────────────────────────────────────────────────────────────
const messageList = ref(props.messages?.data ?? []);
const messageThread = ref(null);

// Jump straight to the bottom: a smooth scroll can be cut short when several updates land together
function scrollToBottom() {
    nextTick(() => requestAnimationFrame(() => {
        if (messageThread.value) {
            messageThread.value.scrollTop = messageThread.value.scrollHeight;
        }
    }));
}

onMounted(() => {
    scrollToBottom();
    messageInput.value?.focus();
});

// ─── Older messages (page 1 is the newest) ──────────────────────────────────
const loadedPage = ref(props.messages?.current_page ?? 1);
const lastPage = ref(props.messages?.last_page ?? 1);
const loadingOlder = ref(false);
const hasOlder = computed(() => loadedPage.value < lastPage.value);

async function loadOlder() {
    if (!hasOlder.value || loadingOlder.value) return;

    loadingOlder.value = true;

    try {
        const response = await fetch(`/channels/${props.channel.id}/messages?page=${loadedPage.value + 1}`, {
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) return;

        const data = await response.json();
        // New messages shift page boundaries, so an older page can repeat ones already shown
        const seen = new Set(messageList.value.map((m) => m.id));
        messageList.value.unshift(...data.data.filter((m) => !seen.has(m.id)));
        loadedPage.value = data.current_page;
        lastPage.value = data.last_page;
    } finally {
        loadingOlder.value = false;
    }
}

// ─── Membership ──────────────────────────────────────────────────────────────
function leaveChannel() {
    router.post(`/channels/${props.channel.id}/leave`);
}

const inviteForm = useForm({ email: '' });

function submitInvite() {
    inviteForm.post(`/channels/${props.channel.id}/members`, {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
}

// ─── Send message ─────────────────────────────────────────────────────────────
const draft = ref('');
const sendError = ref('');
const messageInput = ref(null);

// Messages sent while an earlier one is still in flight wait their turn, so order is preserved
let sendQueue = Promise.resolve();

function sendMessage() {
    const content = draft.value.trim();
    if (!content) return;

    sendError.value = '';
    draft.value = '';
    messageInput.value?.focus();

    sendQueue = sendQueue.then(() => postMessage(content));
}

async function postMessage(content) {
    try {
        const response = await fetch(`/channels/${props.channel.id}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ content }),
        });

        if (!response.ok) {
            // Keep anything typed while the request was in flight
            draft.value = draft.value || content;
            const error = await response.json().catch(() => ({}));
            sendError.value = error.message || 'Message could not be sent. Please try again.';
            return;
        }

        const data = await response.json();
        messageList.value.push({
            id: data.id,
            content: data.content,
            created_at: data.created_at,
            sender: data.sender,
        });
        scrollToBottom();
    } catch {
        draft.value = draft.value || content;
        sendError.value = 'Message could not be sent. Please check your connection.';
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ─── Real-time (Echo / Reverb) ───────────────────────────────────────────────
let echoChannel = null;

onMounted(() => {
    if (!window.Echo) return;

    echoChannel = window.Echo.join(`channel.${props.channel.id}`)
        .here((users) => { onlineIds.value = new Set(users.map((u) => u.id)); })
        .joining((u) => { onlineIds.value = new Set([...onlineIds.value, u.id]); })
        .leaving((u) => {
            const ids = new Set(onlineIds.value);
            ids.delete(u.id);
            onlineIds.value = ids;
        })
        .listen('.message.sent', (event) => {
            // Only append messages from others (sender won't receive their own via toOthers)
            if (event.sender.id !== user.value.id) {
                messageList.value.push({
                    id: event.id,
                    content: event.content,
                    created_at: event.created_at,
                    sender: event.sender,
                });
                scrollToBottom();
                markRead();
            }
        });
});

onUnmounted(() => {
    if (echoChannel) {
        window.Echo.leave(`channel.${props.channel.id}`);
    }
});

// Seen while the chat is open, so it shouldn't show as unread in the sidebar later
function markRead() {
    fetch(`/channels/${props.channel.id}/read`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'Accept': 'application/json',
        },
    }).catch(() => {});
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function isMine(msg) {
    return msg.sender?.id === user.value?.id;
}

// Re-scroll when a new message arrives (not when older ones are prepended)
watch(() => messageList.value.at(-1)?.id, () => scrollToBottom());
</script>

<template>
    <Head :title="isDirect ? title : `#${channel.name}`" />
    <ChatLayout :active-channel-id="channel.id">

        <!-- Channel header -->
        <header class="h-16 flex items-center px-6 border-b border-gray-200 bg-white shrink-0 gap-3">
            <a
                href="/channels"
                aria-label="Back to channels"
                title="Back to channels"
                class="-ml-2 w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition-colors text-lg"
            >←</a>
            <span v-if="isDirect" class="w-7 h-7 rounded-full bg-gray-400 text-white text-xs font-semibold flex items-center justify-center">
                {{ title[0]?.toUpperCase() }}
            </span>
            <span v-else class="text-gray-400 text-xl">{{ channel.type === 'private' ? '🔒' : '#' }}</span>
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-semibold text-gray-900 leading-tight">{{ title }}</h1>
                <p v-if="isDirect" class="text-xs text-gray-400 leading-tight">
                    {{ onlineIds.has(partner?.id) ? 'Online' : 'Direct message' }}
                </p>
                <p v-else-if="channel.description" class="text-xs text-gray-400 leading-tight">{{ channel.description }}</p>
            </div>

            <button
                v-if="!isDirect"
                type="button"
                @click="showMembers = !showMembers"
                class="members-toggle px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors"
                :aria-expanded="showMembers"
            >👥 {{ members.length }}</button>

            <form
                v-if="channel.type === 'private'"
                @submit.prevent="submitInvite"
                class="flex items-center gap-2"
            >
                <div>
                    <input
                        v-model="inviteForm.email"
                        type="email"
                        placeholder="Invite by email"
                        class="w-48 rounded-lg border border-gray-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="inviteForm.errors.email" class="mt-1 text-xs text-red-600">{{ inviteForm.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="inviteForm.processing"
                    class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-500 rounded-lg hover:bg-indigo-600 transition-colors disabled:opacity-50"
                >Invite</button>
            </form>

            <button
                v-if="!isDirect"
                type="button"
                @click="leaveChannel"
                class="px-3 py-1.5 text-xs font-medium text-gray-500 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors"
            >Leave</button>
        </header>

        <div v-if="flash?.success" class="px-6 py-2 bg-green-50 text-green-700 text-xs">
            {{ flash.success }}
        </div>

        <div class="flex-1 flex overflow-hidden">
        <!-- Message thread -->
        <div ref="messageThread" class="flex-1 overflow-y-auto px-6 py-4 space-y-3 bg-gray-50">
            <div v-if="hasOlder" class="text-center">
                <button
                    type="button"
                    @click="loadOlder"
                    :disabled="loadingOlder"
                    class="text-xs text-indigo-500 hover:text-indigo-600 disabled:opacity-50"
                >{{ loadingOlder ? 'Loading…' : 'Load older messages' }}</button>
            </div>

            <div v-if="messageList.length === 0" class="flex items-center justify-center h-full text-gray-400 text-sm">
                No messages yet. Be the first to say something!
            </div>

            <div
                v-for="msg in messageList"
                :key="msg.id"
                class="flex gap-3"
                :class="isMine(msg) ? 'flex-row-reverse' : ''"
            >
                <!-- Avatar -->
                <div
                    class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white shrink-0"
                    :class="isMine(msg) ? 'bg-indigo-500' : 'bg-gray-400'"
                >
                    {{ msg.sender?.name?.[0]?.toUpperCase() ?? '?' }}
                </div>

                <!-- Bubble -->
                <div :class="isMine(msg) ? 'items-end' : 'items-start'" class="flex flex-col max-w-[70%]">
                    <span class="text-xs text-gray-400 mb-1 px-1">
                        <button
                            v-if="!isMine(msg)"
                            type="button"
                            @click="startDirectMessage(msg.sender)"
                            class="font-medium text-gray-600 mr-1 hover:underline"
                            :title="`Message ${msg.sender?.name}`"
                        >{{ msg.sender?.name }}</button>
                        {{ formatTime(msg.created_at) }}
                    </span>
                    <div
                        class="rounded-2xl px-4 py-2 text-sm leading-relaxed break-words"
                        :class="isMine(msg)
                            ? 'bg-indigo-500 text-white rounded-tr-sm'
                            : 'bg-white text-gray-800 shadow-sm rounded-tl-sm'"
                    >
                        {{ msg.content }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Members -->
        <aside v-if="showMembers && !isDirect" class="members-panel w-56 shrink-0 border-l border-gray-200 bg-white overflow-y-auto">
            <p class="px-4 pt-4 pb-2 text-xs text-gray-400 uppercase tracking-widest font-semibold">
                Members · {{ members.length }}
            </p>
            <ul>
                <li v-for="member in members" :key="member.id">
                    <button
                        type="button"
                        @click="startDirectMessage(member)"
                        :disabled="member.id === user?.id"
                        class="member w-full flex items-center gap-2 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:hover:bg-transparent disabled:cursor-default text-left"
                        :title="member.id === user?.id ? '' : `Message ${member.name}`"
                    >
                        <span
                            class="w-2 h-2 rounded-full shrink-0"
                            :class="onlineIds.has(member.id) ? 'bg-green-500' : 'bg-gray-300'"
                        />
                        <span class="truncate">{{ member.name }}</span>
                        <span v-if="member.id === user?.id" class="text-xs text-gray-400">(you)</span>
                    </button>
                </li>
            </ul>
        </aside>
        </div>

        <!-- Message input -->
        <div class="px-6 py-4 bg-white border-t border-gray-200 shrink-0">
            <div class="flex items-end gap-3 bg-gray-100 rounded-2xl px-4 py-2">
                <textarea
                    ref="messageInput"
                    v-model="draft"
                    @keydown="onKeydown"
                    :placeholder="isDirect ? `Message ${title}` : `Message #${channel.name}`"
                    rows="1"
                    class="flex-1 bg-transparent resize-none outline-none text-sm text-gray-800 placeholder-gray-400 max-h-32"
                />
                <button
                    @click="sendMessage"
                    :disabled="!draft.trim()"
                    class="w-8 h-8 rounded-full flex items-center justify-center transition-colors shrink-0 mb-px"
                    :class="draft.trim()
                        ? 'bg-indigo-500 hover:bg-indigo-600 text-white'
                        : 'bg-gray-300 text-gray-400 cursor-not-allowed'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                    </svg>
                </button>
            </div>
            <p v-if="sendError" class="text-xs text-red-600 mt-1 px-2">{{ sendError }}</p>
            <p class="text-xs text-gray-400 mt-1 px-2">Enter to send · Shift+Enter for new line</p>
        </div>
    </ChatLayout>
</template>
