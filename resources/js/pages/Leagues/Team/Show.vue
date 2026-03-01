<script setup lang="ts">
    import { Head, Form } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { Label } from '@/components/ui/label'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import {
        swapRoster,
        pickupFreeAgent,
    } from '@/actions/App/Http/Controllers/Leagues/FantasyTeamController'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface Entity {
        id: number
        name: string
        slug: string
    }

    interface RosterEntry {
        id: number
        entity_type: 'driver' | 'constructor'
        entity_id: number
        in_seat: boolean
        entity: Entity
    }

    interface FreeAgent {
        id: number
        entity_type: 'driver' | 'constructor'
        entity_id: number
        entity: Entity
    }

    interface PointsEvent {
        event: { id: number; name: string; type: string; round: number }
        total: number
        breakdown: { entity_type: string; entity_id: number; points: number }[]
    }

    interface Team {
        id: number
        name: string
        user: { id: number; name: string }
        roster: RosterEntry[]
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
        team: Team
        isOwner: boolean
        pointsByEvent: PointsEvent[]
        totalPoints: number
        freeAgents: FreeAgent[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: '/leagues' },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        { title: props.team.name, href: '#' },
    ]

    const inSeatDrivers = props.team.roster.filter(
        (entry) => entry.entity_type === 'driver' && entry.in_seat,
    )
    const benchDriver =
        props.team.roster.find(
            (entry) => entry.entity_type === 'driver' && !entry.in_seat,
        ) ?? null
    const constructor =
        props.team.roster.find(
            (entry) => entry.entity_type === 'constructor',
        ) ?? null

    // Swap form state
    const swapBenchId = ref<number | null>(null)
    const swapSeatId = ref<number | null>(null)

    // Pickup form state
    const pickupEntityType = ref<'driver' | 'constructor'>('driver')
    const pickupEntityId = ref<number | null>(null)
    const dropEntityId = ref<number | null>(null)

    const availableFreeAgents = (type: 'driver' | 'constructor') =>
        props.freeAgents.filter((fa) => fa.entity_type === type)
</script>

<template>
    <Head :title="team.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">{{ team.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ league.name }} · {{ league.season.name }} · Managed by
                    {{ team.user.name }}
                </p>
            </div>

            <div class="mb-8 grid gap-6 lg:grid-cols-3">
                <!-- Total points -->
                <Card class="flex flex-col items-center justify-center">
                    <CardContent class="py-6 text-center">
                        <p class="text-4xl font-bold text-primary">
                            {{ totalPoints.toFixed(1) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Total Points
                        </p>
                    </CardContent>
                </Card>

                <!-- Constructor -->
                <Card>
                    <CardContent class="p-5">
                        <p
                            class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Constructor
                        </p>
                        <p
                            v-if="constructor"
                            class="font-semibold"
                        >
                            {{ constructor.entity.name }}
                        </p>
                        <p
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            None
                        </p>
                    </CardContent>
                </Card>

                <!-- Drivers -->
                <Card>
                    <CardContent class="p-5">
                        <p
                            class="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Drivers
                        </p>
                        <ul class="space-y-1">
                            <li
                                v-for="driver in inSeatDrivers"
                                :key="driver.id"
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span
                                    class="inline-block h-2 w-2 rounded-full bg-primary"
                                ></span>
                                {{ driver.entity.name }}
                            </li>
                            <li
                                v-if="benchDriver"
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <span
                                    class="inline-block h-2 w-2 rounded-full bg-muted-foreground/40"
                                ></span>
                                {{ benchDriver.entity.name }}
                                <span class="text-xs">(bench)</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Roster management (owner only) -->
            <template v-if="isOwner">
                <!-- Bench swap -->
                <Card
                    v-if="benchDriver && inSeatDrivers.length"
                    class="mb-6"
                >
                    <CardHeader>
                        <CardTitle class="text-sm">Bench Swap</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            :action="
                                swapRoster({
                                    league: league.slug,
                                    team: team.id,
                                }).url
                            "
                            method="post"
                            #default="{ processing }"
                        >
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs">
                                        Bring in (bench)
                                    </Label>
                                    <select
                                        v-model="swapBenchId"
                                        name="bench_driver_id"
                                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option :value="benchDriver.entity_id">
                                            {{ benchDriver.entity.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">
                                        Sit out (seat → bench)
                                    </Label>
                                    <select
                                        v-model="swapSeatId"
                                        name="in_seat_driver_id"
                                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option
                                            v-for="driver in inSeatDrivers"
                                            :key="driver.id"
                                            :value="driver.entity_id"
                                        >
                                            {{ driver.entity.name }}
                                        </option>
                                    </select>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    size="sm"
                                >
                                    {{ processing ? 'Swapping…' : 'Swap' }}
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>

                <!-- Free agent pickup -->
                <Card
                    v-if="freeAgents.length"
                    class="mb-6"
                >
                    <CardHeader>
                        <CardTitle class="text-sm">Free Agent Pickup</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            :action="
                                pickupFreeAgent({
                                    league: league.slug,
                                    team: team.id,
                                }).url
                            "
                            method="post"
                            #default="{ processing }"
                        >
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Type</Label>
                                    <select
                                        v-model="pickupEntityType"
                                        name="entity_type"
                                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="driver">Driver</option>
                                        <option value="constructor">
                                            Constructor
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Pick up</Label>
                                    <select
                                        v-model="pickupEntityId"
                                        name="pickup_entity_id"
                                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="">Select…</option>
                                        <option
                                            v-for="fa in availableFreeAgents(
                                                pickupEntityType,
                                            )"
                                            :key="fa.id"
                                            :value="fa.entity_id"
                                        >
                                            {{ fa.entity.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Drop</Label>
                                    <select
                                        v-model="dropEntityId"
                                        name="drop_entity_id"
                                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <option value="">Select…</option>
                                        <option
                                            v-for="entry in team.roster.filter(
                                                (entry) =>
                                                    entry.entity_type ===
                                                    pickupEntityType,
                                            )"
                                            :key="entry.id"
                                            :value="entry.entity_id"
                                        >
                                            {{ entry.entity.name }}
                                        </option>
                                    </select>
                                </div>
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    size="sm"
                                >
                                    {{ processing ? 'Updating…' : 'Pickup' }}
                                </Button>
                            </div>
                        </Form>
                    </CardContent>
                </Card>
            </template>

            <!-- Points history -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm">Points History</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="pointsByEvent.length"
                        class="divide-y"
                    >
                        <div
                            v-for="row in pointsByEvent"
                            :key="row.event.id"
                            class="flex items-center justify-between px-5 py-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ row.event.name }}
                                </p>
                                <p
                                    class="text-xs text-muted-foreground capitalize"
                                >
                                    {{ row.event.type.replace('_', ' ') }} · Rd
                                    {{ row.event.round }}
                                </p>
                            </div>
                            <span class="font-semibold text-primary"
                                >+{{ row.total.toFixed(1) }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-else
                        class="px-5 py-8 text-center text-sm text-muted-foreground"
                    >
                        No points scored yet.
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
