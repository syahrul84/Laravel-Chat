import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Channel from '@/Pages/Chat/Channel.vue';

// Mock @inertiajs/vue3 — Channel.vue uses usePage and router
const mockAuthUser = { id: 1, name: 'Test User', email: 'test@test.com' };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: mockAuthUser },
            myChannels: {
                data: [
                    { id: 10, name: 'general' },
                    { id: 20, name: 'random' },
                ],
            },
        },
    }),
    router: {
        post: vi.fn(),
        visit: vi.fn(),
    },
    Head: { render: () => null },
    Link: {
        template: '<a><slot/></a>',
        props: ['href', 'method', 'as'],
    },
}));

// ChatLayout stub that renders sidebar slot and default slot
const ChatLayoutStub = {
    template: `
        <div class="chat-layout-stub">
            <div class="sidebar"><slot name="sidebar" /></div>
            <div class="main"><slot /></div>
        </div>
    `,
};

describe('Channel.vue', () => {
    const defaultChannel = {
        id: 10,
        name: 'general',
        description: 'General discussion channel',
    };

    const sampleMessages = [
        {
            id: 1,
            content: 'Hello everyone!',
            created_at: '2026-02-25T10:00:00Z',
            sender: { id: 2, name: 'Alice' },
        },
        {
            id: 2,
            content: 'Welcome to the channel!',
            created_at: '2026-02-25T10:01:00Z',
            sender: { id: 1, name: 'Test User' },
        },
        {
            id: 3,
            content: 'How is everyone doing?',
            created_at: '2026-02-25T10:02:00Z',
            sender: { id: 3, name: 'Bob' },
        },
    ];

    function mountChannel(propsOverrides = {}) {
        return mount(Channel, {
            props: {
                channel: defaultChannel,
                messages: { data: [] },
                ...propsOverrides,
            },
            global: {
                stubs: {
                    ChatLayout: ChatLayoutStub,
                },
            },
        });
    }

    it('renders the channel name', () => {
        const wrapper = mountChannel();

        expect(wrapper.text()).toContain('general');
    });

    it('renders the channel description', () => {
        const wrapper = mountChannel();

        expect(wrapper.text()).toContain('General discussion channel');
    });

    it('does not render description when channel has none', () => {
        const wrapper = mountChannel({
            channel: { id: 10, name: 'general', description: null },
        });

        // The <p v-if="channel.description"> should not render
        const header = wrapper.find('header');
        const descEl = header.findAll('p');
        // Filter only description paragraphs (not the message area)
        const descTexts = descEl.map((el) => el.text());
        expect(descTexts).not.toContain('General discussion channel');
    });

    it('renders the message input textarea', () => {
        const wrapper = mountChannel();

        const textarea = wrapper.find('textarea');
        expect(textarea.exists()).toBe(true);
        expect(textarea.attributes('placeholder')).toContain('general');
    });

    it('renders the send button', () => {
        const wrapper = mountChannel();

        // The send button is the button with the SVG icon in the input area
        const buttons = wrapper.findAll('button');
        const sendButton = buttons.find((b) => b.find('svg').exists());
        expect(sendButton).toBeTruthy();
    });

    it('renders empty state when no messages', () => {
        const wrapper = mountChannel({ messages: { data: [] } });

        expect(wrapper.text()).toContain('No messages yet');
        expect(wrapper.text()).toContain('Be the first to say something');
    });

    it('renders messages when provided', () => {
        const wrapper = mountChannel({
            messages: { data: sampleMessages },
        });

        expect(wrapper.text()).toContain('Hello everyone!');
        expect(wrapper.text()).toContain('Welcome to the channel!');
        expect(wrapper.text()).toContain('How is everyone doing?');
    });

    it('displays sender name for other users messages', () => {
        const wrapper = mountChannel({
            messages: { data: sampleMessages },
        });

        // Alice (id: 2) is not the current user, so her name should appear
        expect(wrapper.text()).toContain('Alice');
        // Bob (id: 3) is not the current user either
        expect(wrapper.text()).toContain('Bob');
    });

    it('does not display sender name for own messages', () => {
        // The current user is { id: 1, name: 'Test User' }
        // The component uses v-if="!isMine(msg)" before showing sender name
        const wrapper = mountChannel({
            messages: {
                data: [
                    {
                        id: 2,
                        content: 'My own message',
                        created_at: '2026-02-25T10:01:00Z',
                        sender: { id: 1, name: 'Test User' },
                    },
                ],
            },
        });

        // Find the bubble area — the sender name span with class "font-medium text-gray-600"
        const senderNameSpans = wrapper.findAll('.font-medium.text-gray-600');
        // For own messages, isMine returns true, so the v-if="!isMine(msg)" hides the sender name
        expect(senderNameSpans).toHaveLength(0);
    });

    it('applies flex-row-reverse class for own messages', () => {
        const wrapper = mountChannel({
            messages: {
                data: [
                    {
                        id: 2,
                        content: 'My message',
                        created_at: '2026-02-25T10:01:00Z',
                        sender: { id: 1, name: 'Test User' },
                    },
                ],
            },
        });

        // Own messages get flex-row-reverse class
        const messageRow = wrapper.find('.flex-row-reverse');
        expect(messageRow.exists()).toBe(true);
    });

    it('does not apply flex-row-reverse for other users messages', () => {
        const wrapper = mountChannel({
            messages: {
                data: [
                    {
                        id: 1,
                        content: 'Hello from Alice',
                        created_at: '2026-02-25T10:00:00Z',
                        sender: { id: 2, name: 'Alice' },
                    },
                ],
            },
        });

        // Other users' messages should NOT have flex-row-reverse
        const messageRows = wrapper.findAll('.flex.gap-3');
        const hasReverse = messageRows.some((el) =>
            el.classes().includes('flex-row-reverse')
        );
        expect(hasReverse).toBe(false);
    });

    it('renders sidebar with my channels list', () => {
        const wrapper = mountChannel();

        const sidebar = wrapper.find('.sidebar');
        expect(sidebar.text()).toContain('My Channels');
        expect(sidebar.text()).toContain('general');
        expect(sidebar.text()).toContain('random');
    });

    it('shows the input hint text about Enter and Shift+Enter', () => {
        const wrapper = mountChannel();

        expect(wrapper.text()).toContain('Enter to send');
        expect(wrapper.text()).toContain('Shift+Enter for new line');
    });

    it('renders sender avatar initials', () => {
        const wrapper = mountChannel({
            messages: {
                data: [
                    {
                        id: 1,
                        content: 'Hi',
                        created_at: '2026-02-25T10:00:00Z',
                        sender: { id: 2, name: 'Alice' },
                    },
                ],
            },
        });

        // Avatar shows first letter uppercase: 'A' for Alice
        const avatars = wrapper.findAll('.w-8.h-8.rounded-full');
        expect(avatars.length).toBeGreaterThan(0);
        expect(avatars[0].text()).toBe('A');
    });
});
