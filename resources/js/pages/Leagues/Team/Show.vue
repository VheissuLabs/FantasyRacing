<script setup lang="ts">
    import { Head, Form, router, useForm } from '@inertiajs/vue3'
    import { computed, ref } from 'vue'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog'
    import { Input } from '@/components/ui/input'
    import InputError from '@/components/InputError.vue'
    import { Label } from '@/components/ui/label'
    import { Switch } from '@/components/ui/switch'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import {
        update as updateTeam,
        swapRoster,
        pickupFreeAgent,
    } from '@/actions/App/Http/Controllers/Leagues/FantasyTeamController'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface Entity {
        id: number
        name: string
        slug: string
        country_emoji?: string | null
        constructor_name?: string | null
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

    const allDrivers = computed(() =>
        props.team.roster.filter((entry) => entry.entity_type === 'driver'),
    )
    const inSeatDrivers = computed(() =>
        allDrivers.value.filter((entry) => entry.in_seat),
    )
    const benchDriver = computed(
        () => allDrivers.value.find((entry) => !entry.in_seat) ?? null,
    )
    const constructor = computed(
        () =>
            props.team.roster.find(
                (entry) => entry.entity_type === 'constructor',
            ) ?? null,
    )

    // Swap via toggle
    const swapping = ref(false)
    const showSwapDialog = ref(false)
    const pendingBenchDriverId = ref<number | null>(null)

    function handleToggle(driver: RosterEntry) {
        if (swapping.value) return

        if (driver.in_seat && benchDriver.value) {
            // Toggling OFF an in-seat driver → swap with bench (unambiguous)
            submitSwap(benchDriver.value.entity_id, driver.entity_id)
        } else if (!driver.in_seat && inSeatDrivers.value.length > 1) {
            // Toggling ON bench driver with multiple in-seat → ask which to sit
            pendingBenchDriverId.value = driver.entity_id
            showSwapDialog.value = true
        } else if (!driver.in_seat && inSeatDrivers.value.length === 1) {
            // Only one in-seat driver, swap directly
            submitSwap(driver.entity_id, inSeatDrivers.value[0].entity_id)
        }
    }

    function confirmSwap(inSeatDriverId: number) {
        if (pendingBenchDriverId.value !== null) {
            submitSwap(pendingBenchDriverId.value, inSeatDriverId)
        }
        showSwapDialog.value = false
        pendingBenchDriverId.value = null
    }

    function submitSwap(benchDriverId: number, inSeatDriverId: number) {
        swapping.value = true
        router.post(
            swapRoster({ league: props.league.slug, team: props.team.id }).url,
            {
                bench_driver_id: benchDriverId,
                in_seat_driver_id: inSeatDriverId,
            },
            {
                preserveScroll: true,
                onFinish: () => (swapping.value = false),
            },
        )
    }

    // Pickup form state
    const pickupEntityType = ref<'driver' | 'constructor'>('driver')
    const pickupEntityId = ref<number | null>(null)
    const dropEntityId = ref<number | null>(null)

    const availableFreeAgents = (type: 'driver' | 'constructor') =>
        props.freeAgents.filter((fa) => fa.entity_type === type)

    // Rename team
    const editingName = ref(false)
    const nameForm = useForm({ name: props.team.name })

    function saveName() {
        nameForm.put(
            updateTeam({
                league: props.league.slug,
                team: props.team.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => (editingName.value = false),
            },
        )
    }

    function cancelEdit() {
        nameForm.name = props.team.name
        nameForm.clearErrors()
        editingName.value = false
    }
</script>

<template>
    <Head :title="team.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6">
            <div class="mb-6">
                <div
                    v-if="isOwner && editingName"
                    class="flex items-center gap-2"
                >
                    <Input
                        v-model="nameForm.name"
                        class="max-w-xs text-2xl font-bold"
                        @keydown.enter="saveName"
                        @keydown.escape="cancelEdit"
                    />
                    <Button
                        size="sm"
                        :disabled="nameForm.processing"
                        @click="saveName"
                    >
                        Save
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="cancelEdit"
                    >
                        Cancel
                    </Button>
                </div>
                <div v-else>
                    <h1
                        class="text-2xl font-bold"
                        :class="
                            isOwner ? 'cursor-pointer hover:text-primary' : ''
                        "
                        @click="isOwner && (editingName = true)"
                    >
                        {{ team.name }}
                    </h1>
                </div>
                <InputError :message="nameForm.errors.name" />
                <p class="text-sm text-muted-foreground">
                    {{ league.name }} · {{ league.season.name }} · Team
                    Principal: {{ team.user.name }}
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
                        <ul class="space-y-2">
                            <li
                                v-for="driver in allDrivers"
                                :key="driver.id"
                                class="flex items-center justify-between text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-2 w-2 rounded-full"
                                        :class="
                                            driver.in_seat
                                                ? 'bg-primary'
                                                : 'bg-muted-foreground/40'
                                        "
                                    ></span>
                                    <span v-if="driver.entity.country_emoji">{{
                                        driver.entity.country_emoji
                                    }}</span>
                                    <span
                                        :class="
                                            driver.in_seat
                                                ? 'font-medium'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ driver.entity.name }}
                                    </span>
                                    ·
                                    <span
                                        v-if="driver.entity.constructor_name"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ driver.entity.constructor_name }}
                                    </span>
                                </div>
                                <Switch
                                    v-if="isOwner"
                                    :model-value="driver.in_seat"
                                    :disabled="swapping"
                                    @update:model-value="handleToggle(driver)"
                                />
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Roster management (owner only) -->
            <template v-if="isOwner">
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

        <!-- Dialog for choosing which in-seat driver to bench -->
        <Dialog
            :open="showSwapDialog"
            @update:open="showSwapDialog = $event"
        >
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle
                        >Which driver should go to the bench?</DialogTitle
                    >
                </DialogHeader>
                <div class="space-y-2">
                    <Button
                        v-for="driver in inSeatDrivers"
                        :key="driver.id"
                        variant="outline"
                        class="w-full justify-start"
                        :disabled="swapping"
                        @click="confirmSwap(driver.entity_id)"
                    >
                        {{ driver.entity.name }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
