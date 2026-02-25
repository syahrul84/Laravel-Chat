<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';

const props = defineProps({
    channel: Object,
    messages: Object,   // LengthAwarePaginator JSON
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const myChannels = computed(() => page.props.myChannels ?? []);

// ─── Messages ────────────────────────────────────────────────────────────────
const messageList = ref(props.messages?.data ?? []);
const messagesEnd = ref(null);

function scrollToBottom() {
    nextTick(() => messagesEnd.value?.scrollIntoView({ behavior: 'smooth' }));
}

onMounted(() => scrollToBottom());

// ─── Send message ─────────────────────────────────────────────────────────────
const draft = ref('');
const sending = ref(false);

async function sendMessage() {
    const content = draft.value.trim();
    if (!content || sending.value) return;

    sending.value = true;
    draft.value = '';

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
            draft.value = content;
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
    } finally {
        sending.value = false;
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
        .here((users) => { /* presence: online users list */ })
        .joining((u) => { /* user joined */ })
        .leaving((u) => { /* user left */ })
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
            }
        });
});

onUnmounted(() => {
    if (echoChannel) {
        window.Echo.leave(`channel.${props.channel.id}`);
    }
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatTime(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function isMine(msg) {
    return msg.sender?.id === user.value?.id;
}

// Re-scroll when list grows
watch(() => messageList.value.length, () => scrollToBottom());
</script>

<template>
    <ChatLayout>
        <!-- Sidebar: my channels -->
        <template #sidebar>
            <p class="px-4 pt-3 pb-1 text-xs text-gray-400 uppercase tracking-widest font-semibold">
                My Channels
            </p>
            <a
                v-for="ch in myChannels?.data ?? []"
                :key="ch.id"
                :href="`/channels/${ch.id}`"
                class="flex items-center gap-2 px-4 py-2 text-sm transition-colors"
                :class="ch.id === channel.id
                    ? 'bg-gray-700 text-white'
                    : 'text-gray-300 hover:bg-gray-800 hover:text-white'"
            >
                <span class="text-gray-500">#</span>
                {{ ch.name }}
            </a>

            <div class="mt-4 px-4">
                <a
                    href="/channels"
                    class="block w-full text-center text-xs text-indigo-400 hover:text-indigo-300 transition-colors py-1"
                >
                    + Browse all channels
                </a>
            </div>
        </template>

        <!-- Channel header -->
        <header class="h-16 flex items-center px-6 border-b border-gray-200 bg-white shrink-0 gap-3">
            <span class="text-gray-400 text-xl">#</span>
            <div>
                <h1 class="text-base font-semibold text-gray-900 leading-tight">{{ channel.name }}</h1>
                <p v-if="channel.description" class="text-xs text-gray-400 leading-tight">{{ channel.description }}</p>
            </div>
        </header>

        <!-- Message thread -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3 bg-gray-50">
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
                        <span v-if="!isMine(msg)" class="font-medium text-gray-600 mr-1">{{ msg.sender?.name }}</span>
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

            <!-- Scroll anchor -->
            <div ref="messagesEnd" />
        </div>

        <!-- Message input -->
        <div class="px-6 py-4 bg-white border-t border-gray-200 shrink-0">
            <div class="flex items-end gap-3 bg-gray-100 rounded-2xl px-4 py-2">
                <textarea
                    v-model="draft"
                    @keydown="onKeydown"
                    :placeholder="`Message #${channel.name}`"
                    rows="1"
                    class="flex-1 bg-transparent resize-none outline-none text-sm text-gray-800 placeholder-gray-400 max-h-32"
                    :disabled="sending"
                />
                <button
                    @click="sendMessage"
                    :disabled="!draft.trim() || sending"
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
            <p class="text-xs text-gray-400 mt-1 px-2">Enter to send · Shift+Enter for new line</p>
        </div>
    </ChatLayout>
</template>
