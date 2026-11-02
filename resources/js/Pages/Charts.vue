<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import { ref } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

// Bar chart
const barData = ref({
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
    datasets: [
        {
            label: 'Sales 2024',
            data: [65, 59, 80, 81, 56, 72],
            backgroundColor: 'rgba(0,91,172,0.7)',
            borderColor: '#005BAC',
            borderWidth: 1,
            borderRadius: 6,
        },
        {
            label: 'Sales 2025',
            data: [28, 48, 40, 60, 86, 90],
            backgroundColor: 'rgba(34,197,94,0.7)',
            borderColor: '#22c55e',
            borderWidth: 1,
            borderRadius: 6,
        },
    ],
});

// Line chart
const lineData = ref({
    labels: ['Q1', 'Q2', 'Q3', 'Q4'],
    datasets: [
        {
            label: 'Revenue',
            data: [42000, 58000, 51000, 79000],
            fill: true,
            borderColor: '#005BAC',
            backgroundColor: 'rgba(0,91,172,0.1)',
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#005BAC',
        },
        {
            label: 'Expenses',
            data: [28000, 35000, 30000, 42000],
            fill: false,
            borderColor: '#F37021',
            backgroundColor: 'rgba(243,112,33,0.1)',
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#F37021',
            borderDash: [5, 5],
        },
    ],
});

// Pie chart
const pieData = ref({
    labels: ['Laravel', 'Vue', 'Inertia', 'Tailwind', 'PrimeVue'],
    datasets: [{
        data: [35, 25, 15, 15, 10],
        backgroundColor: ['#005BAC', '#F37021', '#22c55e', '#3b82f6', '#64748b'],
        hoverOffset: 8,
    }],
});

// Radar chart
const radarData = ref({
    labels: ['Speed', 'Quality', 'Reliability', 'UX', 'Security', 'Scalability'],
    datasets: [
        {
            label: 'Our App',
            data: [90, 85, 92, 88, 80, 75],
            backgroundColor: 'rgba(0,91,172,0.2)',
            borderColor: '#005BAC',
            pointBackgroundColor: '#005BAC',
        },
        {
            label: 'Competitor',
            data: [70, 65, 75, 70, 85, 80],
            backgroundColor: 'rgba(243,112,33,0.2)',
            borderColor: '#F37021',
            pointBackgroundColor: '#F37021',
        },
    ],
});

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};

const barOptions  = { ...baseOptions, scales: { x: { grid: { display: false } }, y: { grid: { color: 'rgba(0,0,0,0.05)' } } } };
const lineOptions = { ...baseOptions, scales: { x: { grid: { display: false } }, y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: (v) => '$' + (v/1000).toFixed(0) + 'k' } } } };
const pieOptions  = { ...baseOptions };
const radarOptions = { ...baseOptions };
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-surface-900">Charts</h1>
            <p class="text-sm text-surface-500 mt-0.5">Powered by Chart.js via PrimeVue Chart component</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

            <Card class="shadow-sm">
                <template #title>Bar Chart — Monthly Sales</template>
                <template #content>
                    <div class="h-72">
                        <Chart type="bar" :data="barData" :options="barOptions" class="h-full" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Line Chart — Revenue vs Expenses</template>
                <template #content>
                    <div class="h-72">
                        <Chart type="line" :data="lineData" :options="lineOptions" class="h-full" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Pie Chart — Tech Stack Usage</template>
                <template #content>
                    <div class="h-72">
                        <Chart type="pie" :data="pieData" :options="pieOptions" class="h-full" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Radar Chart — Performance</template>
                <template #content>
                    <div class="h-72">
                        <Chart type="radar" :data="radarData" :options="radarOptions" class="h-full" />
                    </div>
                </template>
            </Card>

        </div>
    </div>
</template>

