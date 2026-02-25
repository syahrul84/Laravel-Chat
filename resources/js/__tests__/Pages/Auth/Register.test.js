import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Register from '@/Pages/Auth/Register.vue';

// Mock @inertiajs/vue3 — Register.vue uses useForm
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

describe('Register.vue', () => {
    function mountRegister() {
        return mount(Register, {
            global: {
                stubs: {},
            },
        });
    }

    it('renders the registration form with all required fields', () => {
        const wrapper = mountRegister();

        const textInput = wrapper.find('input[type="text"]');
        const emailInput = wrapper.find('input[type="email"]');
        const passwordInputs = wrapper.findAll('input[type="password"]');

        expect(textInput.exists()).toBe(true); // Name
        expect(emailInput.exists()).toBe(true); // Email
        expect(passwordInputs).toHaveLength(2); // Password + Confirm
    });

    it('renders all four labels: Name, Email, Password, Confirm Password', () => {
        const wrapper = mountRegister();

        const labels = wrapper.findAll('label');
        const labelTexts = labels.map((l) => l.text());

        expect(labelTexts).toContain('Name');
        expect(labelTexts).toContain('Email');
        expect(labelTexts).toContain('Password');
        expect(labelTexts).toContain('Confirm Password');
    });

    it('renders the create account button', () => {
        const wrapper = mountRegister();

        const button = wrapper.find('button[type="submit"]');
        expect(button.exists()).toBe(true);
        expect(button.text()).toBe('Create Account');
    });

    it('shows the page heading and subtitle', () => {
        const wrapper = mountRegister();

        expect(wrapper.text()).toContain('Laravel Chat');
        expect(wrapper.text()).toContain('Create your account');
    });

    it('shows a link to the login page', () => {
        const wrapper = mountRegister();

        const loginLink = wrapper.find('a[href="/login"]');
        expect(loginLink.exists()).toBe(true);
        expect(loginLink.text()).toBe('Sign in');
    });

    it('has the name field with correct autocomplete attribute', () => {
        const wrapper = mountRegister();

        const nameInput = wrapper.find('input[type="text"]');
        expect(nameInput.attributes('autocomplete')).toBe('name');
    });

    it('has password fields with new-password autocomplete', () => {
        const wrapper = mountRegister();

        const passwordInputs = wrapper.findAll('input[type="password"]');
        passwordInputs.forEach((input) => {
            expect(input.attributes('autocomplete')).toBe('new-password');
        });
    });
});
