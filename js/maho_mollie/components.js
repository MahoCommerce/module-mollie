    /**
 * Maho
 *
 * @package    Maho_Mollie
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

(function () {
    const METHOD_CODE = 'mollie_creditcard';
    const MOLLIE_JS_URL = 'https://js.mollie.com/v1/mollie.js';
    const COMPONENT_TYPES = ['cardHolder', 'cardNumber', 'expiryDate', 'verificationCode'];
    // `styles` controls the text rendering INSIDE each Mollie iframe (per
    // https://docs.mollie.com/docs/styling-mollie-components). Mollie's iframe
    // document cannot see our :root CSS variables, so we resolve them at
    // runtime and pass concrete values. Falls back to hex defaults when a
    // variable isn't defined.
    function readVar(name, fallback) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return value || fallback;
    }
    function buildStyles() {
        return {
            base: {
                color: readVar('--maho-color-text-primary', '#1f1f1f'),
                fontSize: '14px',
                lineHeight: '1.4',
                '::placeholder': {
                    color: readVar('--maho-color-text-secondary', '#888888'),
                },
            },
            valid: {
                color: readVar('--maho-color-text-primary', '#1f1f1f'),
            },
            invalid: {
                color: readVar('--maho-color-error', '#c0392b'),
            },
        };
    }

    const state = {
        mollie: null,
        components: {},
        mounted: false,
        boundFieldset: null,
        loadingPromise: null,
        tokenInFlight: null,
    };

    function getFieldset() {
        return document.getElementById('payment_form_' + METHOD_CODE);
    }

    function loadMollieJs() {
        if (window.Mollie) {
            return Promise.resolve();
        }
        if (state.loadingPromise) {
            return state.loadingPromise;
        }
        state.loadingPromise = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-mollie-components]');
            if (existing) {
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error('Failed to load Mollie.js')));
                return;
            }
            const script = document.createElement('script');
            script.src = MOLLIE_JS_URL;
            script.async = true;
            script.dataset.mollieComponents = 'true';
            script.addEventListener('load', () => resolve());
            script.addEventListener('error', () => reject(new Error('Failed to load Mollie.js')));
            document.head.appendChild(script);
        });
        return state.loadingPromise;
    }

    function setError(componentName, message) {
        // Mollie component name (e.g. cardNumber) → DOM slug (card-number).
        const slug = componentName.replace(/([A-Z])/g, '-$1').toLowerCase();
        const el = document.getElementById('mollie-' + slug + '-error');
        if (el) {
            el.textContent = message || '';
        }
    }

    function clearErrors() {
        COMPONENT_TYPES.forEach(c => setError(c, ''));
    }

    function destroyComponents() {
        Object.values(state.components).forEach(component => {
            try { component.unmount(); } catch { /* ignore */ }
        });
        state.components = {};
        state.mollie = null;
        state.mounted = false;
        state.boundFieldset = null;
    }

    async function ensureMounted() {
        const fieldset = getFieldset();
        if (!fieldset) {
            // Expected on OPC pages where the payment step is loaded later via AJAX.
            // The switchMethod patch will call us again once the form is in the DOM.
            return;
        }

        if (state.mounted) {
            if (state.boundFieldset === fieldset) {
                return;
            }
            // The payment step re-rendered (AJAX) — old components reference dead DOM.
            destroyComponents();
        }

        const profileId = fieldset.dataset.mollieProfileId || '';
        if (profileId === '') {
            console.warn('[Mollie Components] data-mollie-profile-id is empty — set Profile ID in admin');
            return;
        }
        const testmode = fieldset.dataset.mollieTestmode === 'true';
        const locale = fieldset.dataset.mollieLocale || 'en_US';

        await loadMollieJs();
        if (typeof window.Mollie !== 'function') {
            console.error('[Mollie Components] window.Mollie not available after loading ' + MOLLIE_JS_URL);
            return;
        }
        try {
            state.mollie = window.Mollie(profileId, { locale, testmode });
        } catch (err) {
            console.error('[Mollie Components] Mollie() constructor threw:', err);
            return;
        }

        COMPONENT_TYPES.forEach(type => {
            const slug = type.replace(/([A-Z])/g, '-$1').toLowerCase();
            const target = document.getElementById('mollie-' + slug);
            if (!target) {
                console.warn('[Mollie Components] mount target #mollie-' + slug + ' not found');
                return;
            }
            const component = state.mollie.createComponent(type, {
                styles: buildStyles(),
            });
            component.mount('#mollie-' + slug);
            component.addEventListener('change', event => {
                // Only show the message once the user has touched the field,
                // otherwise we'd render "X cannot be empty" before any input.
                if (event && event.error && event.touched) {
                    setError(type, event.error);
                } else {
                    setError(type, '');
                }
            });
            state.components[type] = component;
        });

        state.boundFieldset = fieldset;
        state.mounted = true;
    }

    async function createTokenAndInject() {
        clearErrors();
        if (state.tokenInFlight) {
            return state.tokenInFlight;
        }
        state.tokenInFlight = (async () => {
            const { token, error } = await state.mollie.createToken();
            if (error) {
                setError('cardNumber', error.message || 'Card validation failed.');
                throw error;
            }
            const input = document.getElementById('mollie-card-token');
            if (input) {
                input.value = token;
            }
            return token;
        })();
        try {
            return await state.tokenInFlight;
        } finally {
            state.tokenInFlight = null;
        }
    }

    function clearToken() {
        const input = document.getElementById('mollie-card-token');
        if (input) {
            input.value = '';
        }
    }

    // Patch Review.prototype.save so every Review instance — including the
    // ones created by onestep's loadReview() AJAX flow, which reassigns the
    // global `review` on every step — inherits our wrap via the prototype
    // chain. Instance-level patching was racy: onestep recreates `review`
    // mid-flow and orphans any direct instance wrap.
    function patchReviewSavePrototype() {
        if (typeof Review === 'undefined' || Review.prototype.__mollieSavePatched) {
            return;
        }
        Review.prototype.__mollieSavePatched = true;
        const originalSave = Review.prototype.save;
        Review.prototype.save = async function () {
            const methodInput = document.querySelector('input[name="payment[method]"]:checked');
            const selectedMethod = methodInput ? methodInput.value : null;
            const usingComponents = selectedMethod === METHOD_CODE && state.mounted;

            if (!usingComponents) {
                return originalSave.apply(this, arguments);
            }

            const input = document.getElementById('mollie-card-token');
            if (input && input.value !== '') {
                return originalSave.apply(this, arguments);
            }

            try {
                await createTokenAndInject();
            } catch {
                // Validation error already surfaced via setError; abort submit.
                if (typeof checkout !== 'undefined' && typeof checkout.setLoadWaiting === 'function') {
                    checkout.setLoadWaiting(false);
                }
                return;
            }
            return originalSave.apply(this, arguments);
        };
    }

    function patchPaymentSwitchMethod() {
        if (typeof Payment === 'undefined' || Payment.prototype.__mollieComponentsPatched) {
            return;
        }
        const originalSwitch = Payment.prototype.switchMethod;
        Payment.prototype.switchMethod = function (method) {
            const previous = this.currentMethod;
            const result = originalSwitch.apply(this, arguments);
            if (method === METHOD_CODE) {
                clearToken();
                ensureMounted().catch(err => console.error('[Mollie Components]', err));
            } else if (previous === METHOD_CODE) {
                clearToken();
                clearErrors();
            }
            return result;
        };
        Payment.prototype.__mollieComponentsPatched = true;
    }

    function init() {
        patchReviewSavePrototype();
        patchPaymentSwitchMethod();

        // If credit card is already the selected method at page load, mount immediately.
        if (typeof payment !== 'undefined' && payment.currentMethod === METHOD_CODE) {
            ensureMounted().catch(err => console.error('[Mollie Components]', err));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
