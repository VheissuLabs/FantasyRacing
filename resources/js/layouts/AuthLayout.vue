<script setup lang="ts">
    import AuthLayout from '@/layouts/Auth/AuthSimpleLayout.vue'

    defineProps<{
        title?: string
        description?: string
    }>()
</script>

<template>
    <AuthLayout
        :title="title"
        :description="description"
    >
        <slot />
    </AuthLayout>
</template>
