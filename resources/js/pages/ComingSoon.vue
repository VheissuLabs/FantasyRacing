<script setup lang="ts">
    import { Head } from '@inertiajs/vue3'
    import { Construction } from 'lucide-vue-next'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    const props = defineProps<{
        title: string
    }>()

    const breadcrumbs: BreadcrumbItem[] = [{ title: props.title }]
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-h-[60vh] items-center justify-center px-4">
            <div class="text-center">
                <Construction
                    class="mx-auto mb-4 size-12 text-muted-foreground/50"
                />
                <h1 class="text-2xl font-bold">{{ title }}</h1>
                <p class="mt-2 text-muted-foreground">
                    Coming soon. We're working on it.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
