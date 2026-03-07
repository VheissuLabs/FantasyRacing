<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { dashboard } from '@/routes'
    import { type BreadcrumbItem } from '@/types'
    import PlaceholderPattern from '../components/PlaceholderPattern.vue'

    interface NextEvent {
        id: number
        name: string
        type: string
        scheduled_at: string
        scheduled_at_human: string
        scheduled_at_local: string
        round: number
        track: {
            id: number
            name: string
        } | null
    }

    function eventTypeLabel(type: string): string {
        return (
            {
                race: 'Race',
                qualifying: 'Qualifying',
                sprint: 'Sprint',
                sprint_qualifying: 'Sprint Qualifying',
            }[type] ?? type
        )
    }

    interface League {
        id: number
        name: string
        slug: string
        members_count: number
        fantasy_team_name: string | null
        franchise: { id: number; name: string }
    }

    defineProps<{
        nextEvent: NextEvent | null
        leagues: League[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
    ]
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <template v-if="nextEvent">
                        <p class="text-sm font-medium text-muted-foreground">
                            Round {{ nextEvent.round }} ·
                            {{ eventTypeLabel(nextEvent.type) }}
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            {{ nextEvent.name }}
                        </h2>
                        <p
                            v-if="nextEvent.track"
                            class="mt-0.5 text-sm text-muted-foreground"
                        >
                            {{ nextEvent.track.name }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ nextEvent.scheduled_at_local }}
                        </p>
                        <p class="mt-4 text-lg font-semibold">
                            {{ nextEvent.scheduled_at_human }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-sm text-muted-foreground">
                            No upcoming races scheduled
                        </p>
                    </template>
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
                <div
                    class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <PlaceholderPattern />
                </div>
            </div>
            <!-- My Leagues -->
            <div
                class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <h2 class="mb-4 text-sm font-medium text-muted-foreground">
                    My Leagues
                </h2>
                <div
                    v-if="leagues.length"
                    class="divide-y"
                >
                    <Link
                        v-for="league in leagues"
                        :key="league.id"
                        :href="leagueShow({ league: league.slug }).url"
                        class="flex items-center justify-between py-3 transition hover:opacity-75"
                    >
                        <div>
                            <p class="font-medium">{{ league.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ league.franchise.name }}
                                <span v-if="league.fantasy_team_name">
                                    · {{ league.fantasy_team_name }}
                                </span>
                            </p>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            {{ league.members_count }}
                            {{
                                league.members_count === 1
                                    ? 'member'
                                    : 'members'
                            }}
                        </span>
                    </Link>
                </div>
                <p
                    v-else
                    class="text-sm text-muted-foreground"
                >
                    You haven't joined any leagues yet.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
