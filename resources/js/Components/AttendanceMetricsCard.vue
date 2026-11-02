<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    count: {
        type: [Number, String],
        required: true,
    },
    total: {
        type: [Number, String],
        default: null,
    },
    description: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: 'pi pi-users',
    },
    variant: {
        type: String,
        default: 'primary',
        validator: (val) => ['primary', 'success', 'warning', 'info', 'danger'].includes(val),
    },
    badgeText: {
        type: String,
        default: null,
    },
    badgeDirection: {
        type: String,
        default: 'up', // 'up' | 'down' | 'neutral'
    },
});

// Map aesthetic pastel styles ala Sneat Dashboard
const variantStyles = computed(() => {
    switch (props.variant) {
        case 'success':
            return {
                iconBg: 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-500/15',
                badgeBg: 'bg-emerald-50 text-emerald-700 border border-emerald-200/60',
                badgeIcon: 'pi pi-arrow-up-right text-emerald-600',
                accentLine: 'bg-emerald-500',
            };
        case 'warning':
            return {
                iconBg: 'bg-orange-50 text-[#F37021] ring-1 ring-[#F37021]/15',
                badgeBg: 'bg-orange-50 text-[#F37021] border border-orange-200/60',
                badgeIcon: 'pi pi-exclamation-circle text-[#F37021]',
                accentLine: 'bg-[#F37021]',
            };
        case 'info':
            return {
                iconBg: 'bg-blue-50 text-blue-600 ring-1 ring-blue-500/15',
                badgeBg: 'bg-blue-50 text-blue-700 border border-blue-200/60',
                badgeIcon: 'pi pi-info-circle text-blue-600',
                accentLine: 'bg-blue-500',
            };
        case 'danger':
            return {
                iconBg: 'bg-rose-50 text-rose-600 ring-1 ring-rose-500/15',
                badgeBg: 'bg-rose-50 text-rose-700 border border-rose-200/60',
                badgeIcon: 'pi pi-arrow-down-right text-rose-600',
                accentLine: 'bg-rose-500',
            };
        case 'primary':
        default:
            return {
                iconBg: 'bg-[#005BAC]/10 text-[#005BAC] ring-1 ring-[#005BAC]/20',
                badgeBg: 'bg-[#005BAC]/10 text-[#005BAC] border border-[#005BAC]/20',
                badgeIcon: 'pi pi-check text-[#005BAC]',
                accentLine: 'bg-[#005BAC]',
            };
    }
});
</script>

<template>
    <div
        class="group relative bg-white rounded-xl border border-slate-200/70 p-5 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] hover:shadow-[0_4px_16px_0_rgba(67,89,113,0.12)] transition-all duration-200 flex flex-col justify-between overflow-hidden"
    >
        <!-- Top decorative micro accent bar -->
        <div
            :class="['absolute top-0 left-0 right-0 h-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200', variantStyles.accentLine]"
        />

        <!-- Card Header: Icon + Title & Badge -->
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3.5">
                <div
                    :class="[
                        'flex items-center justify-center size-11 rounded-lg shrink-0 transition-transform duration-200 group-hover:scale-105',
                        variantStyles.iconBg,
                    ]"
                >
                    <i :class="[icon, 'text-lg']" />
                </div>
                <div>
                    <span class="text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        {{ title }}
                    </span>
                    <div class="text-2xl font-bold text-slate-800 tracking-tight mt-0.5 flex items-baseline gap-1.5">
                        <span>{{ count }}</span>
                        <span v-if="total !== null" class="text-xs font-normal text-slate-400">
                            / {{ total }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Optional status pill/badge -->
            <div
                v-if="badgeText"
                :class="[
                    'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold shrink-0',
                    variantStyles.badgeBg,
                ]"
            >
                <i :class="[variantStyles.badgeIcon, 'text-[10px]']" />
                <span>{{ badgeText }}</span>
            </div>
        </div>

        <!-- Card Footer / Contextual Description -->
        <div v-if="description" class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span class="truncate">{{ description }}</span>
            <slot name="footer-extra" />
        </div>
    </div>
</template>

