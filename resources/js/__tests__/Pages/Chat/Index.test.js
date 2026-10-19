import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '@/Pages/Chat/Index.vue';

// Mock @inertiajs/vue3 — Index.vue uses usePage, useForm
const mockPost = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { id: 1, name: 'Test User', email: 'test@test.com' } },
            flash: {},
        },
    }),
    useForm: (initialData) => ({
        ...initialData,
        post: mockPost,
        processing: false,
        errors: {},
        reset: vi.fn(),
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

// ChatLayout stub that renders sidebar and default slots
const ChatLayoutStub = {
    template: `
        <div class="chat-layout-stub">
            <div class="sidebar"><slot name="sidebar" /></div>
            <div class="main"><slot /></div>
        </div>
    `,
};

describe('Index.vue', () => {
    const sampleChannels = [
        { id: 10, name: 'general', description: 'General discussion', members_count: 5 },
        { id: 20, name: 'random', description: 'Random stuff', members_count: 3 },
        { id: 30, name: 'tech', description: null, members_count: 8 },
    ];

    const myChannels = [
        { id: 10, name: 'general' },
    ];

    function mountIndex(propsOverrides = {}) {
        return mount(Index, {
            props: {
                channels: { data: [] },
                myChannels: { data: [] },
                ...propsOverrides,
            },
            global: {
                stubs: {
                    ChatLayout: ChatLayoutStub,
                },
            },
        });
    }

    it('renders the browse channels heading', () => {
        const wrapper = mountIndex();

        expect(wrapper.text()).toContain('Browse channels');
        expect(wrapper.text()).toContain('Discover and join public channels');
    });

    it('renders empty state when no channels exist', () => {
        const wrapper = mountIndex({ channels: { data: [] } });

        expect(wrapper.text()).toContain('No public channels yet');
        expect(wrapper.text()).toContain('Create the first one');
    });

    it('renders the channel list when channels are provided', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: [] },
        });

        expect(wrapper.text()).toContain('general');
        expect(wrapper.text()).toContain('random');
        expect(wrapper.text()).toContain('tech');
    });

    it('renders channel descriptions', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: [] },
        });

        expect(wrapper.text()).toContain('General discussion');
        expect(wrapper.text()).toContain('Random stuff');
    });

    it('renders member count for each channel', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: [] },
        });

        expect(wrapper.text()).toContain('5 members');
        expect(wrapper.text()).toContain('3 members');
        expect(wrapper.text()).toContain('8 members');
    });

    it('shows Join button for channels not yet joined', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: [] },
        });

        const joinButtons = wrapper.findAll('button').filter((b) => b.text() === 'Join');
        // All 3 channels should have Join buttons since myChannels is empty
        expect(joinButtons).toHaveLength(3);
    });

    it('shows Open link for already joined channels', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: myChannels }, // user has joined channel 10 (general)
        });

        // "Open" link for the already-joined channel
        const openLinks = wrapper.findAll('a[href="/channels/10"]');
        const openLink = openLinks.find((a) => a.text().includes('Open'));
        expect(openLink).toBeDefined();
        expect(openLink.text()).toContain('Open');

        // The other two channels should have Join buttons
        const joinButtons = wrapper.findAll('button').filter((b) => b.text() === 'Join');
        expect(joinButtons).toHaveLength(2);
    });




    it('renders hash symbol before each channel name', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels },
            myChannels: { data: [] },
        });

        // Each channel card has a # prefix
        const hashSpans = wrapper.findAll('.text-gray-400');
        const hashTexts = hashSpans.map((el) => el.text());
        expect(hashTexts.filter((t) => t === '#').length).toBeGreaterThanOrEqual(3);
    });

    it('shows pagination links when there are more public channels', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels, current_page: 1, last_page: 3 },
        });

        const next = wrapper.findAll('a').find((a) => a.text().includes('Next'));
        expect(next.attributes('href')).toBe('/channels?page=2');
        expect(wrapper.text()).toContain('Page 1 of 3');
        expect(wrapper.findAll('a').some((a) => a.text().includes('Previous'))).toBe(false);
    });

    it('hides pagination when there is a single page', () => {
        const wrapper = mountIndex({
            channels: { data: sampleChannels, current_page: 1, last_page: 1 },
        });

        expect(wrapper.text()).not.toContain('Page 1 of');
    });

    it('uses the singular for a channel with one member', () => {
        const wrapper = mountIndex({
            channels: { data: [{ id: 40, name: 'solo', description: null, members_count: 1 }] },
        });

        expect(wrapper.text()).toContain('1 member');
        expect(wrapper.text()).not.toContain('1 members');
    });
});

