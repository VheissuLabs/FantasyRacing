<script setup lang="ts">
    import { Head, router, useForm } from '@inertiajs/vue3'
    import {
        index as leaguesIndex,
        show as leagueShow,
    } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import {
        destroy as settingsDestroy,
        update as settingsUpdate,
    } from '@/actions/App/Http/Controllers/Leagues/LeagueSettingsController'
    import Heading from '@/components/Heading.vue'
    import { Button } from '@/components/ui/button'
    import {
        Dialog,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogTrigger,
    } from '@/components/ui/dialog'
    import AppLayout from '@/layouts/AppLayout.vue'
    import SettingsForm from '@/pages/Leagues/Settings/Forms/SettingsForm.vue'
    import { type BreadcrumbItem } from '@/types'
    import { ref } from 'vue'

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

    const deleting = ref(false)

    function submit() {
        form.put(settingsUpdate({ league: props.league.slug }).url)
    }

    function deleteLeague() {
        deleting.value = true
        router.delete(settingsDestroy({ league: props.league.slug }).url, {
            onFinish: () => (deleting.value = false),
        })
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

            <div class="mt-10 space-y-6">
                <Heading
                    variant="small"
                    title="Delete league"
                    description="Permanently delete this league and all of its data"
                />
                <div
                    class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
                >
                    <div
                        class="relative space-y-0.5 text-red-600 dark:text-red-100"
                    >
                        <p class="font-medium">Warning</p>
                        <p class="text-sm">
                            This will permanently delete the league, all teams,
                            draft data, and trade history. This cannot be undone.
                        </p>
                    </div>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive"
                                >Delete league</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader class="space-y-3">
                                <DialogTitle
                                    >Are you sure you want to delete this
                                    league?</DialogTitle
                                >
                                <DialogDescription>
                                    This action cannot be undone. All teams,
                                    draft sessions, trades, and member data will
                                    be permanently removed.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    :disabled="deleting"
                                    @click="deleteLeague"
                                >
                                    {{ deleting ? 'Deleting...' : 'Delete league' }}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
