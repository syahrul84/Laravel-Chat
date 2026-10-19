import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Channel from '@/Pages/Chat/Channel.vue';
import { router } from '@inertiajs/vue3';

// Mock @inertiajs/vue3 — Channel.vue uses usePage and router
const mockFormPost = vi.fn();
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
    useForm: (initialData) => ({
        ...initialData,
        post: mockFormPost,
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
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

    function mountChannel(propsOverrides = {}, mountOptions = {}) {
        return mount(Channel, {
            ...mountOptions,
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

    it('shows a load older button when there are older pages', () => {
        const wrapper = mountChannel({
            messages: { data: sampleMessages, current_page: 1, last_page: 2 },
        });

        expect(wrapper.text()).toContain('Load older messages');
    });

    it('hides the load older button on the last page', () => {
        const wrapper = mountChannel({
            messages: { data: sampleMessages, current_page: 1, last_page: 1 },
        });

        expect(wrapper.text()).not.toContain('Load older messages');
    });

    it('prepends older messages when load older is clicked', async () => {
        const older = { id: 0, content: 'The very first message', created_at: '2026-02-25T09:00:00Z', sender: { id: 2, name: 'Alice' } };
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [older, sampleMessages[0]], current_page: 2, last_page: 2 }),
        });

        const wrapper = mountChannel({
            messages: { data: sampleMessages, current_page: 1, last_page: 2 },
        });
        const button = wrapper.findAll('button').find((b) => b.text().includes('Load older messages'));
        await button.trigger('click');
        await new Promise((r) => setTimeout(r));

        expect(global.fetch).toHaveBeenCalledWith('/channels/10/messages?page=2', expect.any(Object));
        const contents = wrapper.findAll('.rounded-2xl.px-4').map((el) => el.text());
        expect(contents[0]).toBe('The very first message');
        expect(contents.filter((c) => c === 'Hello everyone!')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Load older messages');
    });

    it('leaves the channel when leave is clicked', async () => {
        const wrapper = mountChannel();

        const button = wrapper.findAll('button').find((b) => b.text() === 'Leave');
        await button.trigger('click');

        expect(router.post).toHaveBeenCalledWith('/channels/10/leave');
    });

    it('shows an invite form for private channels', async () => {
        const wrapper = mountChannel({
            channel: { ...defaultChannel, type: 'private' },
        });

        const input = wrapper.find('input[type="email"]');
        expect(input.exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(mockFormPost).toHaveBeenCalledWith('/channels/10/members', expect.any(Object));
    });

    it('does not show an invite form for public channels', () => {
        const wrapper = mountChannel({
            channel: { ...defaultChannel, type: 'public' },
        });

        expect(wrapper.find('input[type="email"]').exists()).toBe(false);
    });

    it('keeps the message box enabled and focused after sending', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ id: 99, content: 'hi', created_at: '2026-02-25T10:05:00Z', sender: mockAuthUser }),
        });
        const wrapper = mountChannel({}, { attachTo: document.body });
        const textarea = wrapper.find('textarea');

        await textarea.setValue('hi');
        await textarea.trigger('keydown', { key: 'Enter' });
        expect(textarea.element.disabled).toBe(false);
        await new Promise((r) => setTimeout(r));

        expect(document.activeElement).toBe(textarea.element);
        wrapper.unmount();
    });

    it('shows an error and restores the draft when sending fails', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            json: () => Promise.resolve({ message: 'You must be a member of this channel to send messages.' }),
        });
        const wrapper = mountChannel();
        const textarea = wrapper.find('textarea');

        await textarea.setValue('hi');
        await textarea.trigger('keydown', { key: 'Enter' });
        await new Promise((r) => setTimeout(r));

        expect(textarea.element.value).toBe('hi');
        expect(wrapper.text()).toContain('You must be a member of this channel to send messages.');
    });

    it('has a back link to the channel list beside the room name', () => {
        const wrapper = mountChannel();

        const back = wrapper.find('header a[aria-label="Back to channels"]');
        expect(back.exists()).toBe(true);
        expect(back.attributes('href')).toBe('/channels');
    });

    it('sends messages typed while a previous one is still sending, in order', async () => {
        let release;
        const firstPending = new Promise((r) => { release = r; });
        let nextId = 100;
        global.fetch = vi.fn().mockImplementation(async (url, { body }) => {
            const { content } = JSON.parse(body);
            if (content === 'first') await firstPending;
            return { ok: true, json: () => Promise.resolve({ id: nextId++, content, created_at: '2026-02-25T10:05:00Z', sender: mockAuthUser }) };
        });
        const wrapper = mountChannel();
        const textarea = wrapper.find('textarea');

        await textarea.setValue('first');
        await textarea.trigger('keydown', { key: 'Enter' });
        await textarea.setValue('second');
        await textarea.trigger('keydown', { key: 'Enter' });
        release();
        await new Promise((r) => setTimeout(r, 10));

        expect(global.fetch.mock.calls.map(([, opts]) => JSON.parse(opts.body).content)).toEqual(['first', 'second']);
        expect(wrapper.findAll('.rounded-2xl.leading-relaxed').map((el) => el.text()).slice(-2)).toEqual(['first', 'second']);
        expect(textarea.element.value).toBe('');
    });

    it('lists member names with a toggle', async () => {
        const wrapper = mountChannel({
            members: [{ id: 1, name: 'Test User' }, { id: 2, name: 'Alice' }],
        });

        expect(wrapper.find('.members-panel').exists()).toBe(false);
        await wrapper.find('.members-toggle').trigger('click');

        const names = wrapper.findAll('.members-panel .member').map((m) => m.text());
        expect(names).toEqual(['Test User(you)', 'Alice']);
    });

    it('starts a direct message when a member is clicked', async () => {
        const wrapper = mountChannel({
            members: [{ id: 1, name: 'Test User' }, { id: 2, name: 'Alice' }],
        });
        await wrapper.find('.members-toggle').trigger('click');

        await wrapper.findAll('.members-panel .member')[1].trigger('click');

        expect(router.post).toHaveBeenCalledWith('/direct-messages', { user_id: 2 });
    });

    it('shows the other person instead of a channel name in a direct message', () => {
        const wrapper = mountChannel({
            channel: { id: 50, name: null, type: 'direct' },
            partner: { id: 2, name: 'Alice' },
        });

        expect(wrapper.find('header h1').text()).toBe('Alice');
        expect(wrapper.find('textarea').attributes('placeholder')).toBe('Message Alice');
        expect(wrapper.find('.members-toggle').exists()).toBe(false);
        expect(wrapper.findAll('button').some((b) => b.text() === 'Leave')).toBe(false);
    });
});

