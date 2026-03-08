<script setup lang="ts">
    import AppContent from '@/components/AppContent.vue'
    import AppShell from '@/components/AppShell.vue'
    import AppSidebar from '@/components/AppSidebar.vue'
    import AppSidebarHeader from '@/components/AppSidebarHeader.vue'
    import type { BreadcrumbItem } from '@/types'

    type Props = {
        breadcrumbs?: BreadcrumbItem[]
    }

    withDefaults(defineProps<Props>(), {
        breadcrumbs: () => [],
    })
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="max-h-screen overflow-hidden"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex-1 overflow-x-hidden overflow-y-auto">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
