<script setup lang="ts">
    import { Head, useForm } from '@inertiajs/vue3'
    import { store as leaguesStore } from '@/actions/App/Http/Controllers/Leagues/LeagueController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import AppLayout from '@/layouts/AppLayout.vue'
    import SettingsForm from '@/pages/Leagues/Settings/Forms/SettingsForm.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    const props = defineProps<{
        franchises: Franchise[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: leaguesIndex().url },
        { title: 'Create League', href: '#' },
    ]

    const form = useForm({
        franchise_id: '',
        name: '',
        description: '',
        max_teams: '' as string | number,
        visibility: 'public',
        join_policy: 'open',
        rules: {
            no_duplicates: false,
            trade_approval_required: true,
            trades_enabled: true,
            max_roster_size: '' as string | number,
        },
    })

    function submit() {
        form.post(leaguesStore().url)
    }
</script>

<template>
    <Head title="Create League" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6">
            <SettingsForm
                v-model:form="form"
                title="Create a League"
                submit-label="Create League"
                submitting-label="Creating..."
                :franchises="props.franchises"
                @submit="submit"
            />
        </div>
    </AppLayout>
</template>
