<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import { Card, CardContent } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import { show as teamShow } from '@/actions/App/Http/Controllers/Leagues/FantasyTeamController'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface StandingEntry {
        id: number
        name: string
        user: { id: number; name: string }
        total_points: number
        rank: number
    }

    interface League {
        id: number
        name: string
        slug: string
        franchise: { name: string }
        season: { name: string }
        commissioner: { name: string }
    }

    const props = defineProps<{
        league: League
        standings: StandingEntry[]
        events: { id: number; name: string; type: string; round: number }[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: '/leagues' },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        { title: 'Standings', href: '#' },
    ]

    function rankIcon(rank: number): string {
        if (rank === 1) return '🥇'
        if (rank === 2) return '🥈'
        if (rank === 3) return '🥉'
        return String(rank)
    }
</script>

<template>
    <Head :title="`${league.name} — Standings`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Standings</h1>
                <p class="text-sm text-muted-foreground">
                    {{ league.name }} · {{ league.season.name }}
                </p>
            </div>

            <Card v-if="standings.length">
                <CardContent class="p-0">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-3">Rank</th>
                                <th class="px-4 py-3">Team</th>
                                <th class="px-4 py-3">Manager</th>
                                <th class="px-4 py-3 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="entry in standings"
                                :key="entry.id"
                                class="transition hover:bg-accent"
                            >
                                <td class="px-4 py-3 text-lg">
                                    {{ rankIcon(entry.rank) }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        :href="
                                            teamShow({
                                                league: league.slug,
                                                team: entry.id,
                                            }).url
                                        "
                                        class="hover:text-primary"
                                    >
                                        {{ entry.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ entry.user.name }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold text-primary"
                                >
                                    {{ entry.total_points.toFixed(1) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div
                v-else
                class="py-16 text-center text-muted-foreground"
            >
                No teams have scored points yet.
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                Based on {{ events.length }} completed event{{
                    events.length !== 1 ? 's' : ''
                }}.
            </p>
        </div>
    </AppLayout>
</template>
