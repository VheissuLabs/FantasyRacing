<script setup lang="ts">
    import { Head, useForm } from '@inertiajs/vue3'
    import {
        index as leaguesIndex,
        show as leagueShow,
    } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import { update as settingsUpdate } from '@/actions/App/Http/Controllers/Leagues/LeagueSettingsController'
    import AppLayout from '@/layouts/AppLayout.vue'
    import SettingsForm from '@/pages/Leagues/Settings/Forms/SettingsForm.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    interface League {
        id: number
        name: string
        slug: string
        description: string | null
        max_teams: number | null
        franchise_id: number
        visibility: 'public' | 'private'
        join_policy: 'open' | 'request' | 'invite_only'
        invite_code: string | null
        rules: {
            no_duplicates?: boolean
            trade_approval_required?: boolean
            trades_enabled?: boolean
            max_roster_size?: number | null
        } | null
    }

    const props = defineProps<{
        league: League
        franchises: Franchise[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: leaguesIndex().url },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        { title: 'Settings', href: '#' },
    ]

    const form = useForm({
        franchise_id: props.league.franchise_id as string | number,
        name: props.league.name,
        description: props.league.description ?? '',
        max_teams: props.league.max_teams ?? ('' as string | number),
        visibility: props.league.visibility,
        join_policy: props.league.join_policy,
        rules: {
            no_duplicates: props.league.rules?.no_duplicates ?? false,
            trade_approval_required:
                props.league.rules?.trade_approval_required ?? true,
            trades_enabled: props.league.rules?.trades_enabled ?? true,
            max_roster_size: (props.league.rules?.max_roster_size ?? '') as
                | string
                | number,
        },
    })

    function submit() {
        form.put(settingsUpdate({ league: props.league.slug }).url)
    }
</script>

<template>
    <Head title="League Settings" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6">
            <SettingsForm
                v-model:form="form"
                title="League Settings"
                description="Configure your league's name, policies, and rules."
                submit-label="Save Settings"
                submitting-label="Saving..."
                :franchises="props.franchises"
                :invite-code="props.league.invite_code"
                :league-slug="props.league.slug"
                @submit="submit"
            />
        </div>
    </AppLayout>
</template>
