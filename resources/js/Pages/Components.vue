<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Chip from 'primevue/chip';
import ToggleButton from 'primevue/togglebutton';
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import RadioButton from 'primevue/radiobutton';
import ToggleSwitch from 'primevue/toggleswitch';
import Slider from 'primevue/slider';
import Rating from 'primevue/rating';
import ProgressBar from 'primevue/progressbar';
import Divider from 'primevue/divider';
import Avatar from 'primevue/avatar';
import AvatarGroup from 'primevue/avatargroup';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import { ref } from 'vue';

defineOptions({ layout: AuthenticatedLayout });

const toggleVal = ref(false);
const switchVal = ref(true);
const sliderVal = ref(40);
const ratingVal = ref(3);
const checkVals = ref(['Vue']);
const radioVal  = ref('M');
const selectVal = ref(null);
const alignOpts = ref([{ label: 'Left', value: 'left' }, { label: 'Center', value: 'center' }, { label: 'Right', value: 'right' }]);
const alignVal  = ref('center');
const frameworks = [
    { label: 'Vue',     value: 'Vue' },
    { label: 'React',   value: 'React' },
    { label: 'Angular', value: 'Angular' },
];
const countries = ref([
    { name: 'Indonesia', code: 'ID' },
    { name: 'Malaysia',  code: 'MY' },
    { name: 'Singapore', code: 'SG' },
    { name: 'Thailand',  code: 'TH' },
]);
</script>

<template>
    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-bold text-surface-900">Components</h1>
            <p class="text-sm text-surface-500 mt-0.5">PrimeVue component showcase</p>
        </div>

        <!-- Buttons -->
        <Card class="shadow-sm">
            <template #title>Buttons</template>
            <template #content>
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <Button label="Primary" />
                        <Button label="Secondary" severity="secondary" />
                        <Button label="Success" severity="success" />
                        <Button label="Warning" severity="warn" />
                        <Button label="Danger" severity="danger" />
                        <Button label="Info" severity="info" />
                        <Button label="Contrast" severity="contrast" />
                    </div>
                    <Divider />
                    <div class="flex flex-wrap gap-2">
                        <Button label="Outlined" outlined />
                        <Button label="Text" text />
                        <Button label="Link" link />
                        <Button label="Rounded" rounded />
                        <Button label="With Icon" icon="pi pi-star" />
                        <Button icon="pi pi-heart" rounded text severity="danger" />
                        <Button label="Loading" loading />
                    </div>
                    <Divider />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button label="Small" size="small" />
                        <Button label="Normal" />
                        <Button label="Large" size="large" />
                    </div>
                </div>
            </template>
        </Card>

        <!-- Tags, Badges, Chips -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <Card class="shadow-sm">
                <template #title>Tags</template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Tag value="Primary" />
                        <Tag value="Success" severity="success" />
                        <Tag value="Warning" severity="warn" />
                        <Tag value="Danger" severity="danger" />
                        <Tag value="Info" severity="info" />
                        <Tag value="Contrast" severity="contrast" />
                        <Tag value="Rounded" rounded />
                        <Tag value="Icon" icon="pi pi-star" severity="success" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Badges</template>
                <template #content>
                    <div class="flex flex-wrap items-center gap-4">
                        <Badge value="8" />
                        <Badge value="2" severity="success" />
                        <Badge value="4" severity="warn" />
                        <Badge value="12" severity="danger" />
                        <Badge value="3" severity="info" />
                        <Badge value="999+" severity="contrast" />
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Chips</template>
                <template #content>
                    <div class="flex flex-wrap gap-2">
                        <Chip label="Laravel" />
                        <Chip label="Vue 3" icon="pi pi-star" />
                        <Chip label="Inertia" removable />
                        <Chip label="PrimeVue" icon="pi pi-check" removable />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Form Controls -->
        <Card class="shadow-sm">
            <template #title>Form Controls</template>
            <template #content>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                    <!-- Left column -->
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium text-surface-700">Text Input</label>
                            <InputText placeholder="Enter value..." class="w-full" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium text-surface-700">Textarea</label>
                            <Textarea placeholder="Write something..." rows="3" class="w-full" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm font-medium text-surface-700">Select</label>
                            <Select
                                v-model="selectVal"
                                :options="countries"
                                optionLabel="name"
                                placeholder="Select a country"
                                class="w-full"
                            />
                        </div>
                    </div>

                    <!-- Right column -->
                    <div class="space-y-5">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Checkboxes</label>
                            <div class="flex gap-4">
                                <div v-for="fw in frameworks" :key="fw.value" class="flex items-center gap-2">
                                    <Checkbox v-model="checkVals" :inputId="fw.value" :value="fw.value" />
                                    <label :for="fw.value" class="text-sm">{{ fw.label }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Radio Buttons</label>
                            <div class="flex gap-4">
                                <div class="flex items-center gap-2">
                                    <RadioButton v-model="radioVal" inputId="male"   value="M" />
                                    <label for="male" class="text-sm">Male</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <RadioButton v-model="radioVal" inputId="female" value="F" />
                                    <label for="female" class="text-sm">Female</label>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-surface-700">Toggle Switch</label>
                            <ToggleSwitch v-model="switchVal" />
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-surface-700">Toggle Button</label>
                            <ToggleButton v-model="toggleVal" onLabel="On" offLabel="Off" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Select Button</label>
                            <SelectButton v-model="alignVal" :options="alignOpts" optionLabel="label" optionValue="value" />
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <!-- Slider, Rating, Progress -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <Card class="shadow-sm">
                <template #title>Slider</template>
                <template #content>
                    <div class="space-y-3">
                        <Slider v-model="sliderVal" class="w-full" />
                        <p class="text-sm text-center text-surface-500">Value: {{ sliderVal }}</p>
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Rating</template>
                <template #content>
                    <div class="space-y-2">
                        <Rating v-model="ratingVal" />
                        <p class="text-sm text-surface-500">Rating: {{ ratingVal }}/5</p>
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Progress</template>
                <template #content>
                    <div class="space-y-3">
                        <ProgressBar :value="sliderVal" />
                        <ProgressBar mode="indeterminate" style="height: 6px" />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Avatars & Skeleton -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <Card class="shadow-sm">
                <template #title>Avatars</template>
                <template #content>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <Avatar label="A" size="xlarge" shape="circle" style="background-color: #6366f1; color: #fff" />
                            <Avatar label="B" size="large" shape="circle" style="background-color: #22c55e; color: #fff" />
                            <Avatar label="C" shape="circle" style="background-color: #f59e0b; color: #fff" />
                            <Avatar icon="pi pi-user" size="xlarge" shape="circle" />
                        </div>
                        <AvatarGroup>
                            <Avatar label="U1" shape="circle" style="background-color: #6366f1; color: #fff" />
                            <Avatar label="U2" shape="circle" style="background-color: #22c55e; color: #fff" />
                            <Avatar label="U3" shape="circle" style="background-color: #f59e0b; color: #fff" />
                            <Avatar label="U4" shape="circle" style="background-color: #ef4444; color: #fff" />
                            <Avatar label="+3" shape="circle" />
                        </AvatarGroup>
                    </div>
                </template>
            </Card>

            <Card class="shadow-sm">
                <template #title>Skeleton</template>
                <template #content>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <Skeleton shape="circle" size="3rem" />
                            <div class="flex-1 space-y-2">
                                <Skeleton width="60%" height="0.75rem" />
                                <Skeleton width="40%" height="0.75rem" />
                            </div>
                        </div>
                        <Skeleton height="2.5rem" />
                        <Skeleton height="2.5rem" />
                    </div>
                </template>
            </Card>
        </div>

        <!-- Messages -->
        <Card class="shadow-sm">
            <template #title>Messages</template>
            <template #content>
                <div class="space-y-3">
                    <Message severity="info"    >This is an info message.</Message>
                    <Message severity="success" >Operation completed successfully!</Message>
                    <Message severity="warn"    >Warning: please review before continuing.</Message>
                    <Message severity="error"   >An error occurred. Please try again.</Message>
                    <Message severity="contrast">Contrast message for dark backgrounds.</Message>
                </div>
            </template>
        </Card>

    </div>
</template>

