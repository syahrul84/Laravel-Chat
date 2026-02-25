<script setup>
import { ref, computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import ChatLayout from '@/Layouts/ChatLayout.vue';

const props = defineProps({
    channels: Object,   // public channels paginator
    myChannels: Object, // joined channels paginator
});

const page = usePage();
const flash = computed(() => page.props.flash);
const myChannelIds = computed(() => new Set((props.myChannels?.data ?? []).map(c => c.id)));

// ─── Create channel form ──────────────────────────────────────────────────────
const showCreate = ref(false);
const form = useForm({
    name: '',
    description: '',
    type: 'public',
});

function submitCreate() {
    form.post('/channels', {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
}

// ─── Join channel ─────────────────────────────────────────────────────────────
const joiningId = ref(null);

function joinChannel(channel) {
    joiningId.value = channel.id;
    useForm({}).post(`/channels/${channel.id}/join`, {
        onFinish: () => joiningId.value = null,
    });
}
</script>

<template>
    <ChatLayout>
        <!-- Sidebar: my channels -->
        <template #sidebar>
            <div class="flex items-center justify-between px-4 pt-3 pb-1">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold">My Channels</p>
                <button
                    @click="showCreate = !showCreate"
                    class="text-gray-400 hover:text-white transition-colors text-lg leading-none"
                    title="Create channel"
                >+</button>
            </div>

            <a
                v-for="ch in myChannels?.data ?? []"
                :key="ch.id"
                :href="`/channels/${ch.id}`"
                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white transition-colors"
            >
                <span class="text-gray-500">#</span>
                {{ ch.name }}
            </a>

            <p v-if="!myChannels?.data?.length" class="px-4 py-2 text-xs text-gray-500 italic">
                No channels yet
            </p>
        </template>

        <!-- Main content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Flash messages -->
            <div v-if="flash?.error" class="mx-6 mt-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                {{ flash.error }}
            </div>

            <!-- Create channel panel -->
            <div v-if="showCreate" class="mx-6 mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Create a channel</h2>
                <form @submit.prevent="submitCreate" class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. general"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                        <input
                            v-model="form.description"
                            type="text"
                            placeholder="What's this channel about?"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" v-model="form.type" value="public" class="accent-indigo-500" />
                            Public
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" v-model="form.type" value="private" class="accent-indigo-500" />
                            Private
                        </label>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                        >
                            Create
                        </button>
                        <button
                            type="button"
                            @click="showCreate = false; form.reset()"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Public channel browser -->
            <div class="px-6 py-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Browse channels</h2>
                <p class="text-sm text-gray-500 mb-4">Discover and join public channels</p>

                <div v-if="channels?.data?.length" class="grid gap-3">
                    <div
                        v-for="ch in channels.data"
                        :key="ch.id"
                        class="flex items-center justify-between bg-white rounded-xl p-4 shadow-sm border border-gray-200"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-gray-400">#</span>
                                <span class="font-medium text-gray-900 text-sm">{{ ch.name }}</span>
                                <span class="text-xs text-gray-400">· {{ ch.members_count }} members</span>
                            </div>
                            <p v-if="ch.description" class="text-xs text-gray-500 truncate pl-5">
                                {{ ch.description }}
                            </p>
                        </div>

                        <div class="shrink-0 ml-4">
                            <a
                                v-if="myChannelIds.has(ch.id)"
                                :href="`/channels/${ch.id}`"
                                class="inline-block px-3 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors"
                            >
                                Open
                            </a>
                            <button
                                v-else
                                @click="joinChannel(ch)"
                                :disabled="joiningId === ch.id"
                                class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-500 rounded-lg hover:bg-indigo-600 transition-colors disabled:opacity-50"
                            >
                                {{ joiningId === ch.id ? 'Joining…' : 'Join' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-16 text-gray-400">
                    <p class="text-4xl mb-3">#</p>
                    <p class="text-sm">No public channels yet.</p>
                    <button
                        @click="showCreate = true"
                        class="mt-3 text-indigo-500 text-sm hover:underline"
                    >Create the first one</button>
                </div>
            </div>
        </div>
    </ChatLayout>
</template>
