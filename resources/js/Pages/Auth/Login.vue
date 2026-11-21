<script setup>
import { ref, computed, nextTick } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
});

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const clientErrors = ref({
    login: '',
    password: '',
});

const capsLockOn = ref(false);
const showHelpModal = ref(false);
const authErrorAttempt = ref(0);
const clientErrorAttempt = ref(0);

const loginInputRef = ref(null);
const passwordToggleRef = ref(null);
const forgotPasswordBtnRef = ref(null);
const authErrorRef = ref(null);

const serverAuthError = computed(() => form.errors.login || '');
const loginFieldError = computed(() => clientErrors.value.login);
const passwordFieldError = computed(() => clientErrors.value.password || form.errors.password || '');

const hasCredentialMismatchError = computed(() => {
    return Boolean(serverAuthError.value) && !serverAuthError.value.toLowerCase().includes('dinonaktifkan');
});

const loginDescribedBy = computed(() => {
    if (loginFieldError.value) return 'login-field-error';
    if (hasCredentialMismatchError.value) return 'auth-error';
    return undefined;
});

const passwordDescribedBy = computed(() => {
    const ids = [];
    if (passwordFieldError.value) ids.push('password-field-error');
    if (capsLockOn.value) ids.push('caps-lock-hint');
    if (!passwordFieldError.value && hasCredentialMismatchError.value) ids.push('auth-error');
    return ids.length ? ids.join(' ') : undefined;
});

const clearLoginError = () => {
    clientErrors.value.login = '';
    form.clearErrors('login');
};

const clearPasswordError = () => {
    clientErrors.value.password = '';
    form.clearErrors('password');
    if (hasCredentialMismatchError.value) {
        form.clearErrors('login');
    }
};

const handleCapsLock = (event) => {
    if (typeof event.getModifierState === 'function') {
        capsLockOn.value = event.getModifierState('CapsLock');
    }
};

const handlePasswordToggle = (toggleCallback) => {
    const keepFocus = document.activeElement === passwordToggleRef.value;
    toggleCallback();
    if (keepFocus) {
        nextTick(() => {
            passwordToggleRef.value?.focus();
        });
    }
};

const handleHelpDialogHide = () => {
    nextTick(() => {
        forgotPasswordBtnRef.value?.focus();
    });
};

const submit = () => {
    clientErrors.value = { login: '', password: '' };
    form.clearErrors();

    const trimmedLogin = form.login.trim();

    if (!trimmedLogin) {
        clientErrors.value.login = 'NIP / Username wajib diisi.';
    }
    if (!form.password) {
        clientErrors.value.password = 'Kata sandi wajib diisi.';
    }

    if (!trimmedLogin || !form.password) {
        clientErrorAttempt.value += 1;
        nextTick(() => {
            if (!trimmedLogin) {
                loginInputRef.value?.focus();
            } else {
                document.getElementById('password')?.focus();
            }
        });
        return;
    }

    const canUseMobileViewTransition =
        typeof window !== 'undefined' &&
        typeof document !== 'undefined' &&
        typeof document.startViewTransition === 'function' &&
        window.matchMedia('(max-width: 1023px)').matches &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let transitionStarted = false;

    if (canUseMobileViewTransition) {
        document.documentElement.classList.add('esdm-vt-mobile');
    }

    form.post('/login', {
        viewTransition: canUseMobileViewTransition
            ? (transition) => {
                  transitionStarted = true;
                  transition.finished.finally(() => {
                      document.documentElement.classList.remove('esdm-vt-mobile');
                      document.getElementById('main-content')?.focus({ preventScroll: true });
                  });
              }
            : false,
        onSuccess: () => {
            if (!transitionStarted) {
                nextTick(() => {
                    document.getElementById('main-content')?.focus({ preventScroll: true });
                });
            }
        },
        onError: () => {
            authErrorAttempt.value += 1;
            nextTick(() => {
                if (hasCredentialMismatchError.value) {
                    document.getElementById('password')?.focus();
                } else {
                    authErrorRef.value?.focus();
                }
            });
        },
        onFinish: () => {
            form.reset('password');
            if (!transitionStarted) {
                document.documentElement.classList.remove('esdm-vt-mobile');
            }
        },
    });
};
</script>

<template>
    <Head title="Masuk E-SDM KFPB" />

    <div class="min-h-screen min-h-[100dvh] w-full flex flex-col lg:flex-row bg-[#f5f5f9] font-sans antialiased text-slate-700 overflow-x-clip">
        <aside aria-label="Informasi Portal E-SDM" class="hidden lg:flex lg:w-1/2 bg-[#0b1c34] text-white flex-col p-10 xl:p-12 select-none border-r border-[#172c4d]">
            <div class="w-full max-w-md mx-auto flex-1 flex flex-col justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="size-11 rounded-xl bg-white border border-white/25 flex items-center justify-center p-1.5 shrink-0">
                        <img src="/images/esdm-icon.png" alt="Logo E-SDM KFPB" width="96" height="96" fetchpriority="high" class="size-7 object-contain" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-bold tracking-tight text-white leading-none">
                                E-SDM KFPB
                            </span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#c2410c] text-white leading-none">
                                v2.0
                            </span>
                        </div>
                        <p class="text-xs text-blue-200 font-medium mt-1">
                            PT Kimia Farma Plant Banjaran
                        </p>
                    </div>
                </div>

                <div class="my-auto py-8">
                    <p class="text-2xl xl:text-3xl font-bold text-white leading-snug tracking-tight">
                        Portal Presensi, Izin, dan Lembur Pegawai.
                    </p>
                    <p class="text-sm text-blue-100/90 leading-relaxed mt-3">
                        Layanan kepegawaian mandiri dan persetujuan operasional Plant Banjaran.
                    </p>
                </div>

                <p class="pt-6 border-t border-white/10 text-xs text-blue-200/80">
                    Akses internal pegawai dan administrator SDM.
                </p>
            </div>
        </aside>

        <div class="flex-1 w-full lg:w-1/2 lg:relative flex flex-col bg-[#f5f5f9]">
            <div class="relative flex-1 flex flex-col justify-center px-4 pt-8 pb-6 sm:px-8 sm:py-10 lg:px-12 lg:py-16">
                <div
                    aria-hidden="true"
                    class="vt-mobile-navy lg:hidden absolute inset-x-0 top-0 h-[56%] min-h-[240px] bg-[#0b1c34] rounded-b-[48px] sm:rounded-b-[64px] pointer-events-none"
                />

                <div class="vt-login-card relative z-10 w-full max-w-[420px] mx-auto">
                    <header class="lg:hidden text-center text-white mb-5 sm:mb-6 select-none">
                        <div class="size-12 rounded-2xl bg-white border border-white/25 shadow-xs flex items-center justify-center p-2 mx-auto mb-2.5">
                            <img src="/images/esdm-icon.png" alt="Logo E-SDM KFPB" width="96" height="96" fetchpriority="high" class="size-8 object-contain" />
                        </div>
                        <div class="flex items-center justify-center gap-2">
                            <span class="text-xl font-bold tracking-tight text-white leading-none">
                                E-SDM KFPB
                            </span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#c2410c] text-white leading-none">
                                v2.0
                            </span>
                        </div>
                        <p class="text-xs text-blue-200 font-medium mt-1">
                            PT Kimia Farma Plant Banjaran
                        </p>
                    </header>

                    <main class="w-full bg-white border border-slate-200/90 rounded-2xl shadow-md lg:shadow-xs p-6 sm:p-8">
                        <div class="mb-6">
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                                Masuk ke Akun
                            </h1>
                            <p class="text-sm text-slate-600 mt-1">
                                Gunakan NIP atau username Anda.
                            </p>
                        </div>

                        <div
                            v-if="status"
                            role="status"
                            class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2.5"
                        >
                            <i class="pi pi-check-circle text-base text-emerald-700 shrink-0" aria-hidden="true" />
                            <span>{{ status }}</span>
                        </div>

                        <div
                            v-if="serverAuthError"
                            :key="authErrorAttempt"
                            ref="authErrorRef"
                            id="auth-error"
                            role="alert"
                            tabindex="-1"
                            class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 text-xs flex items-start gap-2.5 animate-shake focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700"
                        >
                            <i class="pi pi-exclamation-triangle text-base text-rose-700 shrink-0 mt-0.5" aria-hidden="true" />
                            <div class="leading-snug">
                                <span class="font-bold block">Gagal Masuk</span>
                                <span>{{ serverAuthError }}</span>
                            </div>
                        </div>

                        <form @submit.prevent="submit" novalidate :aria-busy="form.processing" class="space-y-4">
                            <div>
                                <label for="login" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    NIP / Username
                                </label>
                                <input
                                    ref="loginInputRef"
                                    id="login"
                                    name="login"
                                    v-model="form.login"
                                    type="text"
                                    autocomplete="username"
                                    autocapitalize="none"
                                    autocorrect="off"
                                    spellcheck="false"
                                    enterkeyhint="next"
                                    autofocus
                                    required
                                    :disabled="form.processing"
                                    :aria-invalid="(loginFieldError || hasCredentialMismatchError) ? 'true' : 'false'"
                                    :aria-describedby="loginDescribedBy"
                                    placeholder="Masukkan NIP/Username"
                                    @input="clearLoginError"
                                    :class="[
                                        'w-full min-h-[44px] px-3.5 py-2.5 rounded-xl border text-base sm:text-sm text-slate-800 placeholder:text-slate-500 transition-colors motion-reduce:transition-none outline-none bg-white disabled:bg-slate-50 disabled:text-slate-500',
                                        (loginFieldError || hasCredentialMismatchError)
                                            ? 'border-rose-600 focus:border-rose-600 focus:ring-2 focus:ring-rose-200'
                                            : 'border-[#75859c] hover:border-slate-600 focus:border-[#005BAC] focus:ring-2 focus:ring-[#005BAC]/20',
                                    ]"
                                />
                                <p
                                    v-if="loginFieldError"
                                    :key="`login-err-${clientErrorAttempt}`"
                                    id="login-field-error"
                                    class="text-rose-700 text-xs mt-1.5 flex items-center gap-1.5 animate-error-in"
                                >
                                    <i class="pi pi-exclamation-circle text-xs shrink-0" aria-hidden="true" />
                                    <span>{{ loginFieldError }}</span>
                                </p>
                            </div>

                            <div>
                                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Kata Sandi
                                </label>
                                <div
                                    class="w-full"
                                    @keydown="handleCapsLock"
                                    @keyup="handleCapsLock"
                                    @focusout="capsLockOn = false"
                                >
                                    <Password
                                        inputId="password"
                                        v-model="form.password"
                                        :feedback="false"
                                        promptLabel="Masukkan kata sandi"
                                        toggleMask
                                        :disabled="form.processing"
                                        :invalid="Boolean(passwordFieldError || hasCredentialMismatchError)"
                                        class="w-full"
                                        :inputClass="[
                                            '!w-full !min-h-[44px] !px-3.5 !pr-12 !py-2.5 !rounded-xl !shadow-none !text-base sm:!text-sm !text-slate-800 placeholder:!text-slate-500 !bg-white disabled:!bg-slate-50 disabled:!text-slate-500 focus:!border-[#005BAC] focus:!ring-2 focus:!ring-[#005BAC]/20 motion-reduce:!transition-none',
                                            (passwordFieldError || hasCredentialMismatchError)
                                                ? '!border-rose-600 focus:!border-rose-600 focus:!ring-rose-200'
                                                : '!border-[#75859c] hover:!border-slate-600',
                                        ].join(' ')"
                                        :inputProps="{
                                            name: 'password',
                                            autocomplete: 'current-password',
                                            enterkeyhint: 'done',
                                            'aria-expanded': null,
                                            'aria-haspopup': null,
                                            'aria-invalid': (passwordFieldError || hasCredentialMismatchError) ? 'true' : 'false',
                                            'aria-describedby': passwordDescribedBy,
                                        }"
                                        placeholder="Masukkan kata sandi"
                                        required
                                        @update:modelValue="clearPasswordError"
                                    >
                                        <template #maskicon="{ toggleCallback }">
                                            <button
                                                ref="passwordToggleRef"
                                                type="button"
                                                :disabled="form.processing"
                                                class="absolute right-0.5 top-1/2 -translate-y-1/2 z-10 size-11 flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#005BAC] cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                                aria-label="Sembunyikan kata sandi"
                                                aria-pressed="true"
                                                @click="handlePasswordToggle(toggleCallback)"
                                            >
                                                <i class="pi pi-eye-slash text-sm" aria-hidden="true" />
                                            </button>
                                        </template>
                                        <template #unmaskicon="{ toggleCallback }">
                                            <button
                                                ref="passwordToggleRef"
                                                type="button"
                                                :disabled="form.processing"
                                                class="absolute right-0.5 top-1/2 -translate-y-1/2 z-10 size-11 flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-[#005BAC] cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                                aria-label="Tampilkan kata sandi"
                                                aria-pressed="false"
                                                @click="handlePasswordToggle(toggleCallback)"
                                            >
                                                <i class="pi pi-eye text-sm" aria-hidden="true" />
                                            </button>
                                        </template>
                                    </Password>
                                </div>
                                <p v-if="capsLockOn" id="caps-lock-hint" role="status" class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 text-xs mt-1.5 flex items-center gap-1.5">
                                    <i class="pi pi-exclamation-triangle text-xs shrink-0" aria-hidden="true" />
                                    <span>Caps Lock sedang aktif.</span>
                                </p>
                                <p
                                    v-if="passwordFieldError"
                                    :key="`password-err-${clientErrorAttempt}`"
                                    id="password-field-error"
                                    class="text-rose-700 text-xs mt-1.5 flex items-center gap-1.5 animate-error-in"
                                >
                                    <i class="pi pi-exclamation-circle text-xs shrink-0" aria-hidden="true" />
                                    <span>{{ passwordFieldError }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-0.5">
                                <label class="inline-flex items-center gap-2 min-h-[44px] py-2 cursor-pointer select-none">
                                    <Checkbox v-model="form.remember" :binary="true" inputId="remember" :disabled="form.processing" class="login-checkbox" />
                                    <span class="text-xs text-slate-700 font-medium">Ingat saya</span>
                                </label>
                                <button
                                    ref="forgotPasswordBtnRef"
                                    type="button"
                                    :disabled="form.processing"
                                    class="inline-flex items-center min-h-[44px] px-1 text-xs font-semibold text-[#005BAC] hover:text-[#004b8e] hover:underline rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005BAC] cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="showHelpModal = true"
                                >
                                    Lupa kata sandi?
                                </button>
                            </div>

                            <div class="pt-1">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    :class="[
                                        'w-full min-h-[44px] py-2.5 px-4 rounded-xl font-semibold text-sm text-white flex items-center justify-center gap-2 shadow-xs transition-colors motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#003e75]',
                                        form.processing
                                            ? 'bg-[#003e75] cursor-not-allowed'
                                            : 'bg-[#005BAC] hover:bg-[#004b8e] active:scale-[0.99] motion-reduce:active:scale-100 cursor-pointer',
                                    ]"
                                >
                                    <i v-if="form.processing" class="pi pi-spin motion-reduce:animate-none pi-spinner text-sm" aria-hidden="true" />
                                    <span>{{ form.processing ? 'Memverifikasi...' : 'Masuk' }}</span>
                                </button>
                            </div>
                        </form>
                    </main>
                </div>
            </div>

            <footer class="relative z-10 py-4 px-4 text-center text-xs text-slate-600 shrink-0 lg:absolute lg:inset-x-0 lg:bottom-0 lg:py-5">
                © 2026 PT Kimia Farma Plant Banjaran
            </footer>
        </div>

        <Dialog
            v-model:visible="showHelpModal"
            modal
            dismissableMask
            :draggable="false"
            header="Bantuan Lupa Kata Sandi"
            :style="{ width: '440px', maxWidth: '94vw' }"
            :closeButtonProps="{ 'aria-label': 'Tutup dialog bantuan kata sandi', severity: 'secondary', text: true }"
            :pt="{
                pcCloseButton: {
                    root: {
                        class: '!w-11 !h-11 !min-w-[44px] !min-h-[44px] !text-slate-600 hover:!text-slate-900 hover:!bg-slate-100 !rounded-xl focus-visible:!outline-2 focus-visible:!outline-offset-2 focus-visible:!outline-[#005BAC]',
                        'aria-label': 'Tutup dialog bantuan kata sandi',
                    },
                },
            }"
            @after-hide="handleHelpDialogHide"
        >
            <div class="space-y-3.5 text-xs text-slate-700 leading-relaxed pt-1">
                <p>
                    Pemulihan kata sandi dilakukan melalui verifikasi unit kerja untuk menjaga keamanan data kepegawaian:
                </p>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 space-y-2.5">
                    <div class="flex items-start gap-2.5">
                        <span class="size-5 rounded-full bg-blue-100 text-[#005BAC] font-bold flex items-center justify-center shrink-0 text-[11px]">1</span>
                        <span>Datang langsung ke <strong>SDM</strong> (Gedung Office Plant Banjaran).</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="size-5 rounded-full bg-blue-100 text-[#005BAC] font-bold flex items-center justify-center shrink-0 text-[11px]">2</span>
                        <span>Hubungi Helpdesk SDM KFPB dengan menyertakan <strong>NIP, Nama Lengkap, dan Unit Kerja</strong>.</span>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200/80 rounded-xl p-3 text-slate-800 flex items-center gap-3">
                    <i class="pi pi-phone text-base text-[#005BAC] shrink-0" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <div class="font-bold text-xs text-slate-900">Helpdesk SDM &amp; ME-LE KFPB</div>
                        <div class="text-xs text-slate-700">(Jam Kerja Operasional)</div>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-slate-700 flex items-start gap-2.5">
                    <i class="pi pi-info-circle text-[#005BAC] text-sm mt-0.5 shrink-0" aria-hidden="true" />
                    <span>
                        <strong>Catatan akun lama:</strong> Kata sandi tetap sama dengan sistem E-SDM sebelumnya (telah dimigrasikan).
                    </span>
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="min-h-[44px] px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005BAC] transition-colors motion-reduce:transition-none cursor-pointer"
                    @click="showHelpModal = false"
                >
                    Tutup
                </button>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.login-checkbox {
    --p-checkbox-border-color: #75859c;
    --p-checkbox-hover-border-color: #45556c;
    --p-checkbox-checked-background: #005bac;
    --p-checkbox-checked-border-color: #005bac;
    --p-checkbox-checked-hover-background: #004b8e;
    --p-checkbox-checked-hover-border-color: #004b8e;
    --p-checkbox-focus-border-color: #005bac;
    --p-checkbox-checked-focus-border-color: #003e75;
    --p-checkbox-focus-ring-width: 2px;
    --p-checkbox-focus-ring-style: solid;
    --p-checkbox-focus-ring-color: #005bac;
    --p-checkbox-focus-ring-offset: 2px;
}

.login-checkbox :deep(.p-checkbox-input) {
    top: -12px;
    left: 0;
    width: 44px;
    height: 44px;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-4px); }
    40%, 80% { transform: translateX(4px); }
}

.animate-shake {
    animation: shake 0.4s ease-in-out;
}

@keyframes error-in {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-error-in {
    animation: error-in 0.18s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .animate-shake,
    .animate-error-in {
        animation: none;
    }
}
</style>
