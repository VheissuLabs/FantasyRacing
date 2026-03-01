<script setup lang="ts">
    import { Head, Link, router } from '@inertiajs/vue3'
    import {
        index as constructorsIndex,
        show as constructorShow,
        season as constructorSeason,
    } from '@/actions/App/Http/Controllers/ConstructorProfileController'
    import { Badge } from '@/components/ui/badge'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Country {
        id: number
        name: string
        nationality: string
        emoji: string | null
    }

    interface DriverRef {
        id: number
        name: string
        slug: string
        number: number
        country: Country | null
    }

    interface ConstructorModel {
        id: number
        name: string
        slug: string
        logo_path: string | null
        is_active: boolean
        franchise: { id: number; name: string; slug: string }
    }

    interface CareerSummary {
        seasons: number
        races_entered: number
        wins: number
        podiums: number
        one_twos: number
        poles: number
        fastest_laps: number
        points_total: number
        best_championship: number | null
    }

    interface SeasonStat {
        id: number
        races_entered: number
        wins: number
        podiums: number
        one_twos: number
        poles: number
        fastest_laps: number
        points_total: string
        championship_position: number
        season: { id: number; name: string; year: number }
    }

    interface GroupedEventResult {
        event: { id: number; name: string }
        track: { id: number; name: string }
        results: {
            driver: { id: number; name: string; slug: string }
            grid_position: number | null
            finish_position: number | null
            status: string
            fastest_lap: boolean
            driver_of_the_day: boolean
        }[]
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
        constructor: ConstructorModel
        currentDrivers?: DriverRef[]
        careerSummary?: CareerSummary
        seasonStats?: SeasonStat[]
        fantasyStats?: FantasyStats
        availableSeasons: AvailableSeason[]
        season?: { id: number; name: string; year: number }
        seasonStat?: SeasonStat
        seasonDrivers?: DriverRef[]
        eventResults?: GroupedEventResult[]
    }>()

    const isSeasonView = !!props.season

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Constructors', href: constructorsIndex().url },
        {
            title: props.constructor.name,
            href: constructorShow({ constructor: props.constructor.slug }).url,
        },
        ...(props.season ? [{ title: props.season.name }] : []),
    ]

    function onSeasonChange(event: globalThis.Event) {
        const target = event.target as HTMLSelectElement
        const value = target.value
        if (value === '') {
            router.get(
                constructorShow({ constructor: props.constructor.slug }).url,
            )
        } else {
            router.get(
                constructorSeason({
                    constructor: props.constructor.slug,
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
                  { label: '1-2 Finishes', value: props.seasonStat.one_twos },
                  { label: 'Poles', value: props.seasonStat.poles },
                  {
                      label: 'Fastest Laps',
                      value: props.seasonStat.fastest_laps,
                  },
                  { label: 'Points', value: props.seasonStat.points_total },
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
                    {
                        label: '1-2 Finishes',
                        value: props.careerSummary.one_twos,
                    },
                    { label: 'Poles', value: props.careerSummary.poles },
                    {
                        label: 'Fastest Laps',
                        value: props.careerSummary.fastest_laps,
                    },
                    {
                        label: 'Points',
                        value: props.careerSummary.points_total,
                    },
                    {
                        label: 'Best Championship',
                        value: props.careerSummary.best_championship
                            ? `P${props.careerSummary.best_championship}`
                            : '-',
                    },
                ]
              : []

    const drivers = isSeasonView ? props.seasonDrivers : props.currentDrivers
</script>

<template>
    <Head :title="constructor.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 flex items-start gap-5">
                <div
                    class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg bg-muted text-2xl font-bold text-muted-foreground"
                >
                    <img
                        v-if="constructor.logo_path"
                        :src="constructor.logo_path"
                        :alt="constructor.name"
                        class="h-full w-full rounded-lg object-contain p-2"
                    />
                    <span v-else>{{ constructor.name.charAt(0) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold">
                            {{ constructor.name }}
                        </h1>
                        <Badge
                            v-if="!constructor.is_active"
                            variant="secondary"
                        >
                            Inactive
                        </Badge>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ constructor.franchise.name }}
                    </p>
                </div>
            </div>

            <!-- Current/Season Drivers -->
            <Card
                v-if="drivers && drivers.length > 0"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">{{
                        isSeasonView ? 'Drivers' : 'Current Drivers'
                    }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4">
                        <Link
                            v-for="driverItem in drivers"
                            :key="driverItem.id"
                            :href="`/drivers/${driverItem.slug}`"
                            class="flex items-center gap-3 rounded-lg border p-3 transition hover:bg-muted/50"
                        >
                            <Badge variant="outline">
                                #{{ driverItem.number }}
                            </Badge>
                            <div>
                                <p class="font-medium">{{ driverItem.name }}</p>
                                <p
                                    v-if="driverItem.country"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ driverItem.country.emoji }}
                                    {{ driverItem.country.nationality }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </CardContent>
            </Card>

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

            <!-- Season History table (overview only) -->
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
                                        1-2s
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
                                                constructorSeason({
                                                    constructor:
                                                        constructor.slug,
                                                    season: stat.season.id,
                                                }).url
                                            "
                                            class="text-primary hover:underline"
                                        >
                                            {{ stat.season.name }}
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
                                        {{ stat.one_twos }}
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

            <!-- Event Results (season view, grouped by event) -->
            <Card
                v-if="isSeasonView && eventResults && eventResults.length > 0"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Event Results</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-4 py-2 font-medium">Event</th>
                                    <th class="px-4 py-2 font-medium">
                                        Driver
                                    </th>
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
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-for="group in eventResults"
                                    :key="group.event.id"
                                >
                                    <tr
                                        v-for="(result, index) in group.results"
                                        :key="`${group.event.id}-${result.driver.id}`"
                                        class="border-b last:border-0 hover:bg-muted/50"
                                    >
                                        <td class="px-4 py-2">
                                            <template v-if="index === 0">
                                                {{ group.event.name }}
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                    >{{
                                                        group.track.name
                                                    }}</span
                                                >
                                            </template>
                                        </td>
                                        <td class="px-4 py-2">
                                            <Link
                                                :href="`/drivers/${result.driver.slug}`"
                                                class="hover:underline"
                                            >
                                                {{ result.driver.name }}
                                            </Link>
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
                                                    v-if="
                                                        result.driver_of_the_day
                                                    "
                                                    variant="default"
                                                    class="text-xs"
                                                >
                                                    DOTD
                                                </Badge>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
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
