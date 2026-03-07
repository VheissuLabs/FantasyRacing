<script setup lang="ts">
    import { Head, Link, router } from '@inertiajs/vue3'
    import { Badge } from '@/components/ui/badge'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import {
        index as driversIndex,
        show as driverShow,
        season as driverSeason,
    } from '@/actions/App/Http/Controllers/DriverProfileController'

    interface Country {
        id: number
        name: string
        nationality: string
        emoji: string | null
    }

    interface ConstructorRef {
        id: number
        name: string
        slug: string
    }

    interface Driver {
        id: number
        name: string
        slug: string
        photo_path: string | null
        is_active: boolean
        country: Country | null
        franchise: { id: number; name: string; slug: string }
    }

    interface SeasonDriver {
        id: number
        number: number
        constructor: ConstructorRef
    }

    interface CareerSummary {
        seasons: number
        races_entered: number
        wins: number
        podiums: number
        poles: number
        fastest_laps: number
        points_total: number
        best_championship: number | null
        fantasy_points_total: number
    }

    interface SeasonStat {
        id: number
        races_entered: number
        races_classified: number
        wins: number
        podiums: number
        poles: number
        fastest_laps: number
        dnfs: number
        points_total: string
        best_finish: number
        championship_position: number
        fantasy_points_total: string
        season: { id: number; name: string; year: number }
        constructor: ConstructorRef
    }

    interface EventResult {
        id: number
        grid_position: number | null
        finish_position: number | null
        status: string
        fastest_lap: boolean
        driver_of_the_day: boolean
        fia_points: string | null
        fantasy_points: string | null
        event: {
            id: number
            name: string
            type: string
            track: { id: number; name: string }
        }
        constructor: ConstructorRef
    }

    interface FantasyStats {
        ownership_pct: string | null
        avg_points: string | null
        best_haul: string | null
    }

    interface AvailableSeason {
        id: number
        name: string
        year: number
    }

    const props = defineProps<{
        driver: Driver
        currentSeasonDriver?: SeasonDriver | null
        careerSummary?: CareerSummary
        seasonStats?: SeasonStat[]
        fantasyStats?: FantasyStats
        recentResults?: EventResult[]
        availableSeasons: AvailableSeason[]
        season?: { id: number; name: string; year: number }
        seasonStat?: SeasonStat
        seasonDriver?: SeasonDriver | null
        eventResults?: EventResult[]
    }>()

    const isSeasonView = !!props.season

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Drivers', href: driversIndex().url },
        {
            title: props.driver.name,
            href: driverShow({ driver: props.driver.slug }).url,
        },
        ...(props.season ? [{ title: props.season.name }] : []),
    ]

    function onSeasonChange(event: globalThis.Event) {
        const target = event.target as HTMLSelectElement
        const value = target.value
        if (value === '') {
            router.get(driverShow({ driver: props.driver.slug }).url)
        } else {
            router.get(
                driverSeason({
                    driver: props.driver.slug,
                    season: Number(value),
                }).url,
            )
        }
    }

    const statItems =
        isSeasonView && props.seasonStat
            ? [
                  { label: 'Races', value: props.seasonStat.races_entered },
                  { label: 'Wins', value: props.seasonStat.wins },
                  { label: 'Podiums', value: props.seasonStat.podiums },
                  { label: 'Poles', value: props.seasonStat.poles },
                  {
                      label: 'Fastest Laps',
                      value: props.seasonStat.fastest_laps,
                  },
                  { label: 'DNFs', value: props.seasonStat.dnfs },
                  { label: 'FIA Points', value: props.seasonStat.points_total },
                  {
                      label: 'Fantasy Points',
                      value: props.seasonStat.fantasy_points_total,
                  },
                  {
                      label: 'Championship',
                      value: props.seasonStat.championship_position
                          ? `P${props.seasonStat.championship_position}`
                          : '-',
                  },
              ]
            : props.careerSummary
              ? [
                    { label: 'Seasons', value: props.careerSummary.seasons },
                    {
                        label: 'Races',
                        value: props.careerSummary.races_entered,
                    },
                    { label: 'Wins', value: props.careerSummary.wins },
                    { label: 'Podiums', value: props.careerSummary.podiums },
                    { label: 'Poles', value: props.careerSummary.poles },
                    {
                        label: 'Fastest Laps',
                        value: props.careerSummary.fastest_laps,
                    },
                    {
                        label: 'FIA Points',
                        value: props.careerSummary.points_total,
                    },
                    {
                        label: 'Fantasy Points',
                        value: props.careerSummary.fantasy_points_total,
                    },
                    {
                        label: 'Best Championship',
                        value: props.careerSummary.best_championship
                            ? `P${props.careerSummary.best_championship}`
                            : '-',
                    },
                ]
              : []

    const displayResults = isSeasonView
        ? props.eventResults
        : props.recentResults
</script>

<template>
    <Head :title="driver.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-start gap-5">
                <div
                    class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-muted text-2xl font-bold text-muted-foreground"
                >
                    <img
                        v-if="driver.photo_path"
                        :src="driver.photo_path"
                        :alt="driver.name"
                        class="h-full w-full rounded-full object-cover"
                    />
                    <span v-else>{{ driver.name.charAt(0) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold">{{ driver.name }}</h1>
                        <Badge
                            v-if="currentSeasonDriver"
                            variant="outline"
                        >
                            #{{ currentSeasonDriver.number }}
                        </Badge>
                        <Badge
                            v-if="!driver.is_active"
                            variant="secondary"
                        >
                            Retired
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span v-if="driver.country"
                            >{{ driver.country.emoji }}
                            {{ driver.country.nationality }}</span
                        >
                        <span v-if="driver.country && currentSeasonDriver">
                            ·
                        </span>
                        <Link
                            v-if="currentSeasonDriver"
                            :href="`/constructors/${currentSeasonDriver.constructor.slug}`"
                            class="text-primary hover:underline"
                        >
                            {{ currentSeasonDriver.constructor.name }}
                        </Link>
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ driver.franchise.name }}
                    </p>
                </div>
            </div>

            <!-- Season selector -->
            <div class="mb-6">
                <select
                    @change="onSeasonChange"
                    :value="season?.id ?? ''"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="">Career Overview</option>
                    <option
                        v-for="availableSeason in availableSeasons"
                        :key="availableSeason.id"
                        :value="availableSeason.id"
                    >
                        {{ availableSeason.name }}
                    </option>
                </select>
            </div>

            <!-- Stats grid -->
            <div
                v-if="statItems.length"
                class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4"
            >
                <Card
                    v-for="stat in statItems"
                    :key="stat.label"
                >
                    <CardContent class="p-4 text-center">
                        <p class="text-2xl font-bold">{{ stat.value }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ stat.label }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Season-by-Season table (overview only) -->
            <Card
                v-if="!isSeasonView && seasonStats && seasonStats.length > 0"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Season History</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-4 py-2 font-medium">Year</th>
                                    <th class="px-4 py-2 font-medium">
                                        Constructor
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Races
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Wins
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Podiums
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Poles
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        FL
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Pts
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Pos
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="stat in seasonStats"
                                    :key="stat.id"
                                    class="border-b last:border-0 hover:bg-muted/50"
                                >
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="
                                                driverSeason({
                                                    driver: driver.slug,
                                                    season: stat.season.id,
                                                }).url
                                            "
                                            class="text-primary hover:underline"
                                        >
                                            {{ stat.season.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2">
                                        <Link
                                            :href="`/constructors/${stat.constructor.slug}`"
                                            class="hover:underline"
                                        >
                                            {{ stat.constructor.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.races_entered }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.wins }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.podiums }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.poles }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.fastest_laps }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ stat.points_total }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        P{{ stat.championship_position }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Event Results table -->
            <Card
                v-if="displayResults && displayResults.length > 0"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">{{
                        isSeasonView ? 'Event Results' : 'Recent Results'
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-4 py-2 font-medium">Event</th>
                                    <th class="px-4 py-2 font-medium">Track</th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Grid
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Finish
                                    </th>
                                    <th class="px-4 py-2 font-medium">
                                        Status
                                    </th>
                                    <th class="px-4 py-2 font-medium"></th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        FIA Pts
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        Fantasy Pts
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="result in displayResults"
                                    :key="result.id"
                                    class="border-b last:border-0 hover:bg-muted/50"
                                >
                                    <td class="px-4 py-2">
                                        {{ result.event.name }}
                                    </td>
                                    <td class="px-4 py-2">
                                        {{ result.event.track?.name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ result.grid_position ?? '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right font-medium"
                                    >
                                        {{ result.finish_position ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2 capitalize">
                                        {{ result.status }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex gap-1">
                                            <Badge
                                                v-if="result.fastest_lap"
                                                variant="secondary"
                                                class="text-xs"
                                            >
                                                FL
                                            </Badge>
                                            <Badge
                                                v-if="result.driver_of_the_day"
                                                variant="default"
                                                class="text-xs"
                                            >
                                                DOTD
                                            </Badge>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        {{ result.fia_points ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <span
                                            :class="{
                                                'text-red-500':
                                                    Number(
                                                        result.fantasy_points,
                                                    ) < 0,
                                                'text-green-500':
                                                    Number(
                                                        result.fantasy_points,
                                                    ) > 0,
                                            }"
                                        >
                                            {{ result.fantasy_points ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Fantasy Stats -->
            <Card
                v-if="
                    fantasyStats &&
                    (fantasyStats.ownership_pct || fantasyStats.avg_points)
                "
            >
                <CardHeader>
                    <CardTitle class="text-sm">Fantasy Stats</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xl font-bold">
                                {{
                                    fantasyStats.ownership_pct
                                        ? `${fantasyStats.ownership_pct}%`
                                        : '-'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Ownership
                            </p>
                        </div>
                        <div>
                            <p class="text-xl font-bold">
                                {{
                                    fantasyStats.avg_points
                                        ? Number(
                                              fantasyStats.avg_points,
                                          ).toFixed(1)
                                        : '-'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Avg Points
                            </p>
                        </div>
                        <div>
                            <p class="text-xl font-bold">
                                {{ fantasyStats.best_haul ?? '-' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Best Haul
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
