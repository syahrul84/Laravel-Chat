import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import ChatLayout from '@/Layouts/ChatLayout.vue';
import { router } from '@inertiajs/vue3';

const mockPage = { props: {} };

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => mockPage,
    router: { post: vi.fn() },
    Link: {
        template: '<a><slot/></a>',
        props: ['href', 'method', 'as'],
    },
}));

describe('ChatLayout.vue', () => {
    beforeEach(() => {
        mockPage.props = {
            auth: { user: { id: 1, name: 'Test User', email: 'test@test.com' } },
            sidebar: {
                channels: [
                    { id: 10, name: 'general', type: 'public', unread: 3 },
                    { id: 20, name: 'secret', type: 'private', unread: 0 },
                ],
                directMessages: [
                    { id: 30, partner: { id: 2, name: 'Alice' }, unread: 1 },
                ],
            },
        };
        vi.clearAllMocks();
    });

    afterEach(() => {
        delete window.Echo;
        vi.useRealTimers();
    });

    function mountLayout(props = {}) {
        return mount(ChatLayout, { props, slots: { default: '<div class="page">page</div>' } });
    }

    it('lists joined channels and direct messages', () => {
        const wrapper = mountLayout();

        expect(wrapper.findAll('.sidebar-channel').map((c) => c.find('a').text())).toEqual(['#general', '🔒secret']);
        expect(wrapper.find('.sidebar-dm').text()).toContain('Alice');
        expect(wrapper.find('.page').exists()).toBe(true);
    });

    it('shows a red unread badge only for conversations with new messages', () => {
        const wrapper = mountLayout();

        const badges = wrapper.findAll('.unread-badge');
        expect(badges.map((b) => b.text())).toEqual(['3', '1']);
        expect(badges[0].classes()).toContain('bg-red-500');
    });

    it('hides the badge for the conversation that is open', () => {
        const wrapper = mountLayout({ activeChannelId: 10 });

        expect(wrapper.findAll('.unread-badge').map((b) => b.text())).toEqual(['1']);
    });

    it('shows empty states when the user has no conversations', () => {
        mockPage.props.sidebar = { channels: [], directMessages: [] };
        const wrapper = mountLayout();

        expect(wrapper.text()).toContain('No channels yet');
        expect(wrapper.text()).toContain('No conversations yet');
    });

    it('closes a channel or direct message with the X button', async () => {
        const wrapper = mountLayout();

        await wrapper.find('.sidebar-dm .close-button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/channels/30/close');

        await wrapper.find('.sidebar-channel .close-button').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/channels/10/close');
    });

    it('counts live activity in other conversations and alerts for direct messages', async () => {
        const wrapper = mountLayout({ activeChannelId: 20 });

        wrapper.vm.onActivity({ channel_id: 10, channel_type: 'public', sender: { id: 2, name: 'Alice' }, preview: 'hi' });
        wrapper.vm.onActivity({ channel_id: 30, channel_type: 'direct', sender: { id: 2, name: 'Alice' }, preview: 'are you there?' });
        await nextTick();

        expect(wrapper.findAll('.unread-badge').map((b) => b.text())).toEqual(['4', '2']);
        const toast = wrapper.find('.toast');
        expect(toast.text()).toContain('Alice');
        expect(toast.text()).toContain('are you there?');
        expect(toast.find('a').attributes('href')).toBe('/channels/30');
    });

    it('adds a new direct message conversation when someone messages for the first time', async () => {
        const wrapper = mountLayout();

        wrapper.vm.onActivity({ channel_id: 40, channel_type: 'direct', sender: { id: 3, name: 'Bob' }, preview: 'hey' });
        await nextTick();

        const dms = wrapper.findAll('.sidebar-dm');
        expect(dms[0].text()).toContain('Bob');
        expect(dms[0].find('.unread-badge').text()).toBe('1');
    });

    it('ignores activity in the conversation that is open', async () => {
        const wrapper = mountLayout({ activeChannelId: 30 });

        wrapper.vm.onActivity({ channel_id: 30, channel_type: 'direct', sender: { id: 2, name: 'Alice' }, preview: 'hi' });
        await nextTick();

        expect(wrapper.find('.toast').exists()).toBe(false);
    });

    it('dismisses alerts automatically', async () => {
        vi.useFakeTimers();
        const wrapper = mountLayout();

        wrapper.vm.onActivity({ channel_id: 30, channel_type: 'direct', sender: { id: 2, name: 'Alice' }, preview: 'hi' });
        await nextTick();
        expect(wrapper.find('.toast').exists()).toBe(true);

        vi.advanceTimersByTime(6000);
        await nextTick();
        expect(wrapper.find('.toast').exists()).toBe(false);
    });

    it('subscribes to the personal activity channel', () => {
        const listen = vi.fn();
        window.Echo = { private: vi.fn(() => ({ listen })), leave: vi.fn() };

        mountLayout();

        expect(window.Echo.private).toHaveBeenCalledWith('App.Models.User.1');
        expect(listen).toHaveBeenCalledWith('.channel.activity', expect.any(Function));
    });

    it('searches people and starts a direct message', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([{ id: 5, name: 'Carol' }]) });
        vi.useFakeTimers();
        const wrapper = mountLayout();

        await wrapper.findAll('button').find((b) => b.attributes('title') === 'New message').trigger('click');
        await wrapper.find('input[type="search"]').setValue('car');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        expect(global.fetch).toHaveBeenCalledWith('/users/search?q=car', expect.any(Object));
        await wrapper.find('.person-result').trigger('click');
        expect(router.post).toHaveBeenCalledWith('/direct-messages', { user_id: 5 });
    });
});
