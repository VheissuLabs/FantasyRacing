<script setup lang="ts">
    import { Head, Link } from '@inertiajs/vue3'
    import { computed } from 'vue'
    import { Badge } from '@/components/ui/badge'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { useGlobalFilters } from '@/composables/useGlobalFilters'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import {
        index as driversIndex,
        show as driverShow,
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
        best_championship: number | null
    }

    interface SeasonStat {
        id: number
        races_entered: number
        wins: number
        podiums: number
        poles: number
        fastest_laps: number
        dnfs: number
        points_total: string
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

    interface SeasonResults {
        season: { id: number; name: string; year: number }
        results: EventResult[]
    }

    interface AvailableSeason {
        id: number
        name: string
        year: number
    }

    const props = defineProps<{
        driver: Driver
        currentSeasonDriver?: SeasonDriver | null
        careerSummary: CareerSummary
        seasonStats: SeasonStat[]
        resultsBySeason: SeasonResults[]
        availableSeasons: AvailableSeason[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Drivers', href: driversIndex().url },
        {
            title: props.driver.name,
            href: driverShow({ driver: props.driver.slug }).url,
        },
    ]

    const { selectedSeasonId } = useGlobalFilters()

    const careerItems = [
        { label: 'Seasons', value: props.careerSummary.seasons },
        { label: 'Races', value: props.careerSummary.races_entered },
        { label: 'Wins', value: props.careerSummary.wins },
        { label: 'Podiums', value: props.careerSummary.podiums },
        { label: 'Poles', value: props.careerSummary.poles },
        { label: 'Fastest Laps', value: props.careerSummary.fastest_laps },
        {
            label: 'Best Championship',
            value: props.careerSummary.best_championship
                ? `P${props.careerSummary.best_championship}`
                : '-',
        },
    ]

    const effectiveSeasonId = computed(() => {
        if (selectedSeasonId.value) {
            const exists = props.availableSeasons?.some(
                (s) => s.id === selectedSeasonId.value,
            )
            if (exists) return selectedSeasonId.value
        }
        return props.availableSeasons?.[0]?.id ?? null
    })

    const selectedSeasonStat = computed(() => {
        if (!effectiveSeasonId.value) return null
        return props.seasonStats?.find(
            (s) => s.season.id === effectiveSeasonId.value,
        )
    })

    const selectedSeasonResults = computed(() => {
        if (!effectiveSeasonId.value) return null
        return props.resultsBySeason?.find(
            (s) => s.season.id === effectiveSeasonId.value,
        )
    })
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

            <!-- Career Overview -->
            <Card class="mb-8">
                <CardHeader>
                    <CardTitle class="text-sm">Career Overview</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div
                            v-for="stat in careerItems"
                            :key="stat.label"
                            class="text-center"
                        >
                            <p class="text-2xl font-bold">{{ stat.value }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ stat.label }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Season Overview -->
            <Card
                v-if="selectedSeasonStat"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">
                        {{ selectedSeasonStat.season.name }} Season Overview
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold">
                                {{ selectedSeasonStat.points_total }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                FIA Points
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold">
                                {{ selectedSeasonStat.fantasy_points_total }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Fantasy Points
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold">
                                {{ selectedSeasonStat.races_entered }}
                            </p>
                            <p class="text-xs text-muted-foreground">Races</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold">
                                P{{ selectedSeasonStat.championship_position }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Championship
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Event Results -->
            <Card
                v-if="
                    selectedSeasonResults &&
                    selectedSeasonResults.results.length > 0
                "
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
                                    v-for="result in selectedSeasonResults.results"
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
        </div>
    </AppLayout>
</template>
