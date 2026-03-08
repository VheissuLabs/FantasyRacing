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
        index as constructorsIndex,
        show as constructorShow,
    } from '@/actions/App/Http/Controllers/ConstructorProfileController'

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
        country: Country | null
        franchise: { id: number; name: string; slug: string }
    }

    interface CareerSummary {
        seasons: number
        races_entered: number
        wins: number
        podiums: number
        best_championship: number | null
    }

    interface SeasonStat {
        id: number
        races_entered: number
        wins: number
        podiums: number
        points_total: string
        championship_position: number
        fantasy_points_total: string
        season: { id: number; name: string; year: number }
    }

    interface GroupedEventResult {
        event: { id: number; name: string; type: string }
        track: { id: number; name: string }
        fantasy_points: string | null
        results: {
            driver: { id: number; name: string; slug: string }
            grid_position: number | null
            finish_position: number | null
            status: string
            fastest_lap: boolean
            driver_of_the_day: boolean
            fia_points: string | null
            fantasy_points: string | null
        }[]
    }

    interface SeasonResults {
        season: { id: number; name: string; year: number }
        events: GroupedEventResult[]
    }

    interface AvailableSeason {
        id: number
        name: string
        year: number
    }

    const props = defineProps<{
        constructor: ConstructorModel
        currentDrivers: DriverRef[]
        careerSummary: CareerSummary
        seasonStats: SeasonStat[]
        resultsBySeason: SeasonResults[]
        availableSeasons: AvailableSeason[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Constructors', href: constructorsIndex().url },
        {
            title: props.constructor.name,
            href: constructorShow({ constructor: props.constructor.slug }).url,
        },
    ]

    const { selectedSeasonId } = useGlobalFilters()

    const careerItems = [
        { label: 'Seasons', value: props.careerSummary.seasons },
        { label: 'Races', value: props.careerSummary.races_entered },
        { label: 'Wins', value: props.careerSummary.wins },
        { label: 'Podiums', value: props.careerSummary.podiums },
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
    <Head :title="constructor.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
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
                    <p
                        v-if="constructor.country"
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        {{ constructor.country.emoji }}
                        {{ constructor.country.name }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ constructor.franchise.name }}
                    </p>
                </div>
            </div>

            <!-- Current Drivers -->
            <Card
                v-if="currentDrivers && currentDrivers.length > 0"
                class="mb-8"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Current Drivers</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-4">
                        <Link
                            v-for="driverItem in currentDrivers"
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
                    selectedSeasonResults.events.length > 0
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
                                <template
                                    v-for="group in selectedSeasonResults.events"
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
                                                        group.track?.name ?? '-'
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
                                        <td class="px-4 py-2 text-right">
                                            {{ result.fia_points ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <template v-if="index === 0">
                                                <span
                                                    :class="{
                                                        'text-red-500':
                                                            Number(
                                                                group.fantasy_points,
                                                            ) < 0,
                                                        'text-green-500':
                                                            Number(
                                                                group.fantasy_points,
                                                            ) > 0,
                                                    }"
                                                >
                                                    {{
                                                        group.fantasy_points ??
                                                        '-'
                                                    }}
                                                </span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
