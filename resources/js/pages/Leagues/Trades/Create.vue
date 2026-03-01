<script setup lang="ts">
    import { Head, useForm } from '@inertiajs/vue3'
    import { ref, computed } from 'vue'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import {
        index as tradesIndex,
        store as tradesStore,
    } from '@/actions/App/Http/Controllers/Leagues/TradeController'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { Label } from '@/components/ui/label'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Entity {
        id: number
        name: string
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

    interface TeamWithRoster {
        id: number
        name: string
        user: { id: number; name: string }
        roster: RosterEntry[]
    }

    interface League {
        id: number
        name: string
        slug: string
        season: { name: string }
    }

    const props = defineProps<{
        league: League
        myTeam: TeamWithRoster
        otherTeams: TeamWithRoster[]
        freeAgents: FreeAgent[]
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: '/leagues' },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        {
            title: 'Trades',
            href: tradesIndex({ league: props.league.slug }).url,
        },
        { title: 'Propose', href: '#' },
    ]

    const selectedTeamId = ref<number | null>(null)

    const selectedTeam = computed(
        () =>
            props.otherTeams.find((t) => t.id === selectedTeamId.value) ?? null,
    )

    // Items the user is giving away (from their roster)
    const giving = ref<{ entity_type: string; entity_id: number }[]>([])
    // Items the user wants to receive
    const receiving = ref<{ entity_type: string; entity_id: number }[]>([])

    function toggleGiving(entityType: string, entityId: number) {
        const idx = giving.value.findIndex(
            (i) => i.entity_type === entityType && i.entity_id === entityId,
        )
        if (idx >= 0) {
            giving.value.splice(idx, 1)
        } else {
            giving.value.push({ entity_type: entityType, entity_id: entityId })
        }
    }

    function toggleReceiving(entityType: string, entityId: number) {
        const idx = receiving.value.findIndex(
            (i) => i.entity_type === entityType && i.entity_id === entityId,
        )
        if (idx >= 0) {
            receiving.value.splice(idx, 1)
        } else {
            receiving.value.push({
                entity_type: entityType,
                entity_id: entityId,
            })
        }
    }

    function isGiving(entityType: string, entityId: number) {
        return giving.value.some(
            (i) => i.entity_type === entityType && i.entity_id === entityId,
        )
    }

    function isReceiving(entityType: string, entityId: number) {
        return receiving.value.some(
            (i) => i.entity_type === entityType && i.entity_id === entityId,
        )
    }

    const form = useForm({
        receiver_team_id: null as number | null,
        giving: [] as { entity_type: string; entity_id: number }[],
        receiving: [] as { entity_type: string; entity_id: number }[],
    })

    function submit() {
        form.receiver_team_id = selectedTeamId.value
        form.giving = giving.value
        form.receiving = receiving.value
        form.post(tradesStore({ league: props.league.slug }).url)
    }
</script>

<template>
    <Head :title="`${league.name} — Propose Trade`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Propose a Trade</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Select trade partner -->
                        <div class="grid gap-2">
                            <Label for="trade_partner">Trade Partner</Label>
                            <select
                                id="trade_partner"
                                v-model="selectedTeamId"
                                required
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option :value="null">Select a team…</option>
                                <option
                                    v-for="team in otherTeams"
                                    :key="team.id"
                                    :value="team.id"
                                >
                                    {{ team.name }} ({{ team.user.name }})
                                </option>
                                <option :value="-1">Free Agent Pool</option>
                            </select>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <!-- My roster — giving -->
                            <div>
                                <Label class="mb-3">You give up</Label>
                                <div class="space-y-1">
                                    <button
                                        v-for="roster in myTeam.roster"
                                        :key="roster.id"
                                        type="button"
                                        @click="
                                            toggleGiving(
                                                roster.entity_type,
                                                roster.entity_id,
                                            )
                                        "
                                        :class="[
                                            'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
                                            isGiving(roster.entity_type, roster.entity_id)
                                                ? 'border-destructive bg-destructive/10 text-destructive'
                                                : 'border-input hover:bg-accent',
                                        ]"
                                    >
                                        <span
                                            class="text-xs text-muted-foreground capitalize"
                                            >{{ roster.entity_type }}</span
                                        >
                                        {{ roster.entity.name }}
                                        <span
                                            v-if="!roster.in_seat"
                                            class="ml-auto text-xs text-muted-foreground"
                                            >(bench)</span
                                        >
                                    </button>
                                </div>
                                <p
                                    v-if="!myTeam.roster.length"
                                    class="text-sm text-muted-foreground"
                                >
                                    Your roster is empty.
                                </p>
                            </div>

                            <!-- Their roster / free agents — receiving -->
                            <div>
                                <Label class="mb-3">You receive</Label>

                                <template v-if="selectedTeamId === -1">
                                    <div class="space-y-1">
                                        <button
                                            v-for="fa in freeAgents"
                                            :key="fa.id"
                                            type="button"
                                            @click="
                                                toggleReceiving(
                                                    fa.entity_type,
                                                    fa.entity_id,
                                                )
                                            "
                                            :class="[
                                                'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
                                                isReceiving(
                                                    fa.entity_type,
                                                    fa.entity_id,
                                                )
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-input hover:bg-accent',
                                            ]"
                                        >
                                            <span
                                                class="text-xs text-muted-foreground capitalize"
                                                >{{ fa.entity_type }}</span
                                            >
                                            {{ fa.entity.name }}
                                        </button>
                                    </div>
                                    <p
                                        v-if="!freeAgents.length"
                                        class="text-sm text-muted-foreground"
                                    >
                                        Free agent pool is empty.
                                    </p>
                                </template>

                                <template v-else-if="selectedTeam">
                                    <div class="space-y-1">
                                        <button
                                            v-for="roster in selectedTeam.roster"
                                            :key="roster.id"
                                            type="button"
                                            @click="
                                                toggleReceiving(
                                                    roster.entity_type,
                                                    roster.entity_id,
                                                )
                                            "
                                            :class="[
                                                'flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-left text-sm transition',
                                                isReceiving(
                                                    roster.entity_type,
                                                    roster.entity_id,
                                                )
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-input hover:bg-accent',
                                            ]"
                                        >
                                            <span
                                                class="text-xs text-muted-foreground capitalize"
                                                >{{ roster.entity_type }}</span
                                            >
                                            {{ roster.entity.name }}
                                            <span
                                                v-if="!roster.in_seat"
                                                class="ml-auto text-xs text-muted-foreground"
                                                >(bench)</span
                                            >
                                        </button>
                                    </div>
                                    <p
                                        v-if="!selectedTeam.roster.length"
                                        class="text-sm text-muted-foreground"
                                    >
                                        Their roster is empty.
                                    </p>
                                </template>

                                <p
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    Select a trade partner above.
                                </p>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div
                            v-if="giving.length || receiving.length"
                            class="rounded-lg bg-accent px-4 py-3 text-sm"
                        >
                            <p
                                class="mb-1 text-xs font-medium text-muted-foreground uppercase"
                            >
                                Summary
                            </p>
                            <p>
                                You give:
                                <strong>{{ giving.length }}</strong> item(s)
                                &nbsp;·&nbsp; You receive:
                                <strong>{{ receiving.length }}</strong> item(s)
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter class="justify-end">
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                !giving.length ||
                                !receiving.length
                            "
                        >
                            {{
                                form.processing ? 'Proposing…' : 'Propose Trade'
                            }}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
