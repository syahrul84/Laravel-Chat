import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import Home from '@/Pages/Chat/Home.vue';

const mockPage = { props: {} };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => mockPage,
    Head: { render: () => null },
}));

const ChatLayoutStub = { template: '<div><slot /></div>' };

describe('Home.vue', () => {
    beforeEach(() => {
        mockPage.props = {
            auth: { user: { id: 1, name: 'Test User' } },
            sidebar: { channels: [], directMessages: [] },
        };
    });

    function mountHome() {
        return mount(Home, { global: { stubs: { ChatLayout: ChatLayoutStub } } });
    }

    it('shows getting-started options for a brand new user', () => {
        const wrapper = mountHome();

        expect(wrapper.text()).toContain('Welcome, Test User');
        const cards = wrapper.findAll('.getting-started').map((c) => c.text());
        expect(cards.some((t) => t.includes('Browse channels'))).toBe(true);
        expect(cards.some((t) => t.includes('Create a channel'))).toBe(true);
        expect(cards.some((t) => t.includes('Message someone'))).toBe(true);
    });

    it('lists conversations with unread ones first for a returning user', () => {
        mockPage.props.sidebar = {
            channels: [{ id: 10, name: 'general', type: 'public', unread: 0 }],
            directMessages: [{ id: 30, partner: { id: 2, name: 'Alice' }, unread: 2 }],
        };
        const wrapper = mountHome();

        expect(wrapper.text()).toContain('Welcome back');
        expect(wrapper.find('.getting-started').exists()).toBe(false);
        const items = wrapper.findAll('li a');
        expect(items[0].text()).toContain('Alice');
        expect(items[0].text()).toContain('2 new');
        expect(items[1].attributes('href')).toBe('/channels/10');
    });
});
