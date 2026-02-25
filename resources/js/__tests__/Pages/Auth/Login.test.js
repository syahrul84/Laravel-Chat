import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Login from '@/Pages/Auth/Login.vue';

// Mock @inertiajs/vue3 — Login.vue uses useForm
const mockPost = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    useForm: (initialData) => ({
        ...initialData,
        post: mockPost,
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
}));

describe('Login.vue', () => {
    function mountLogin() {
        return mount(Login, {
            global: {
                stubs: {
                    // No layout or child components to stub
                },
            },
        });
    }

    it('renders the login form with email and password inputs', () => {
        const wrapper = mountLogin();

        const emailInput = wrapper.find('input[type="email"]');
        const passwordInput = wrapper.find('input[type="password"]');

        expect(emailInput.exists()).toBe(true);
        expect(passwordInput.exists()).toBe(true);
    });

    it('renders the sign in button', () => {
        const wrapper = mountLogin();

        const button = wrapper.find('button[type="submit"]');
        expect(button.exists()).toBe(true);
        expect(button.text()).toBe('Sign In');
    });

    it('shows the page heading and subtitle', () => {
        const wrapper = mountLogin();

        expect(wrapper.text()).toContain('Laravel Chat');
        expect(wrapper.text()).toContain('Sign in to your account');
    });

    it('shows a link to the register page', () => {
        const wrapper = mountLogin();

        const registerLink = wrapper.find('a[href="/register"]');
        expect(registerLink.exists()).toBe(true);
        expect(registerLink.text()).toBe('Register');
    });

    it('renders email and password labels', () => {
        const wrapper = mountLogin();

        const labels = wrapper.findAll('label');
        const labelTexts = labels.map((l) => l.text());

        expect(labelTexts).toContain('Email');
        expect(labelTexts).toContain('Password');
    });

    it('has correct autocomplete attributes on inputs', () => {
        const wrapper = mountLogin();

        const emailInput = wrapper.find('input[type="email"]');
        const passwordInput = wrapper.find('input[type="password"]');

        expect(emailInput.attributes('autocomplete')).toBe('email');
        expect(passwordInput.attributes('autocomplete')).toBe('current-password');
    });
});
