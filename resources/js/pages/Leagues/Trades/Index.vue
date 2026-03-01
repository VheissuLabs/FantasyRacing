<script setup lang="ts">
    import { Head, Link, Form } from '@inertiajs/vue3'
    import { computed } from 'vue'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import {
        create as tradeCreate,
        accept as tradeAccept,
        reject as tradeReject,
    } from '@/actions/App/Http/Controllers/Leagues/TradeController'
    import { Badge } from '@/components/ui/badge'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface TradeItem {
        id: number
        from_team_id: number | null
        to_team_id: number | null
        entity_type: string
        entity_id: number
    }

    interface TradeTeam {
        id: number
        name: string
        user: { id: number; name: string }
    }

    interface Trade {
        id: number
        status: 'pending' | 'completed' | 'rejected'
        initiated_at: string
        resolved_at: string | null
        initiator_team: TradeTeam
        receiver_team: TradeTeam | null
        items: TradeItem[]
    }

    interface Paginator<T> {
        data: T[]
        current_page: number
        last_page: number
        next_page_url: string | null
        prev_page_url: string | null
    }

    interface League {
        id: number
        name: string
        slug: string
        franchise: { name: string }
        season: { name: string }
        commissioner: { id: number; name: string }
    }

    interface MyTeam {
        id: number
        name: string
    }

    const props = defineProps<{
        league: League
        trades: Paginator<Trade>
        myTeam: MyTeam | null
        isCommissioner: boolean
        tradeApprovalRequired: boolean
    }>()

    const pendingTrades = computed(() =>
        props.trades.data.filter((t) => t.status === 'pending'),
    )
    const showApprovalQueue = computed(
        () =>
            props.isCommissioner &&
            props.tradeApprovalRequired &&
            pendingTrades.value.length > 0,
    )

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: '/leagues' },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        { title: 'Trades', href: '#' },
    ]

    function statusVariant(
        status: string,
    ): 'default' | 'secondary' | 'outline' {
        return (
            {
                pending: 'secondary' as const,
                completed: 'default' as const,
                rejected: 'outline' as const,
            }[status] ?? 'outline'
        )
    }

    function canAccept(trade: Trade): boolean {
        if (trade.status !== 'pending') return false
        if (props.isCommissioner) return true
        if (!props.myTeam) return false
        return trade.receiver_team?.id === props.myTeam.id
    }

    function canReject(trade: Trade): boolean {
        if (trade.status !== 'pending') return false
        if (props.isCommissioner) return true
        if (!props.myTeam) return false
        return (
            trade.initiator_team.id === props.myTeam.id ||
            trade.receiver_team?.id === props.myTeam.id
        )
    }
</script>

<template>
    <Head :title="`${league.name} — Trades`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Trades</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ league.name }}
                    </p>
                </div>
                <Button
                    v-if="myTeam"
                    as-child
                >
                    <Link :href="tradeCreate({ league: league.slug }).url">
                        Propose Trade
                    </Link>
                </Button>
            </div>

            <!-- Commissioner Approval Queue -->
            <Card
                v-if="showApprovalQueue"
                class="mb-6 border-amber-500/30 bg-amber-50/50 dark:bg-amber-950/20"
            >
                <CardHeader class="pb-3">
                    <CardTitle class="text-lg">
                        Trades Awaiting Approval
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Card
                        v-for="trade in pendingTrades"
                        :key="`approval-${trade.id}`"
                    >
                        <CardContent class="p-5">
                            <div
                                class="mb-3 flex items-start justify-between gap-2"
                            >
                                <div class="text-sm">
                                    <span class="font-medium">{{
                                        trade.initiator_team.name
                                    }}</span>
                                    <span class="text-muted-foreground">
                                        →
                                    </span>
                                    <span class="font-medium">{{
                                        trade.receiver_team?.name ??
                                        'Free Agent Pool'
                                    }}</span>
                                </div>
                                <Badge variant="secondary">Pending</Badge>
                            </div>

                            <div
                                class="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2"
                            >
                                <div>
                                    <p
                                        class="mb-1 font-medium tracking-wide uppercase"
                                    >
                                        {{ trade.initiator_team.name }} gives
                                    </p>
                                    <p
                                        v-for="item in trade.items.filter(
                                            (i) =>
                                                i.from_team_id ===
                                                trade.initiator_team.id,
                                        )"
                                        :key="item.id"
                                        class="capitalize"
                                    >
                                        {{ item.entity_type }} #{{
                                            item.entity_id
                                        }}
                                    </p>
                                </div>
                                <div v-if="trade.receiver_team">
                                    <p
                                        class="mb-1 font-medium tracking-wide uppercase"
                                    >
                                        {{ trade.receiver_team.name }} gives
                                    </p>
                                    <p
                                        v-for="item in trade.items.filter(
                                            (i) =>
                                                i.from_team_id ===
                                                trade.receiver_team!.id,
                                        )"
                                        :key="item.id"
                                        class="capitalize"
                                    >
                                        {{ item.entity_type }} #{{
                                            item.entity_id
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex gap-2">
                                <Form
                                    :action="
                                        tradeAccept({
                                            league: league.slug,
                                            trade: trade.id,
                                        }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        size="sm"
                                    >
                                        {{
                                            processing
                                                ? 'Approving…'
                                                : 'Approve'
                                        }}
                                    </Button>
                                </Form>
                                <Form
                                    :action="
                                        tradeReject({
                                            league: league.slug,
                                            trade: trade.id,
                                        }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        variant="outline"
                                        size="sm"
                                    >
                                        {{
                                            processing ? 'Rejecting…' : 'Reject'
                                        }}
                                    </Button>
                                </Form>
                            </div>
                        </CardContent>
                    </Card>
                </CardContent>
            </Card>

            <div
                v-if="trades.data.length"
                class="space-y-4"
            >
                <Card
                    v-for="trade in trades.data"
                    :key="trade.id"
                >
                    <CardContent class="p-5">
                        <div
                            class="mb-3 flex items-start justify-between gap-2"
                        >
                            <div class="text-sm">
                                <span class="font-medium">{{
                                    trade.initiator_team.name
                                }}</span>
                                <span class="text-muted-foreground"> → </span>
                                <span class="font-medium">{{
                                    trade.receiver_team?.name ??
                                    'Free Agent Pool'
                                }}</span>
                            </div>
                            <Badge :variant="statusVariant(trade.status)">
                                {{
                                    trade.status.charAt(0).toUpperCase() +
                                    trade.status.slice(1)
                                }}
                            </Badge>
                        </div>

                        <div
                            class="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2"
                        >
                            <div>
                                <p
                                    class="mb-1 font-medium tracking-wide uppercase"
                                >
                                    {{ trade.initiator_team.name }} gives
                                </p>
                                <p
                                    v-for="item in trade.items.filter(
                                        (i) =>
                                            i.from_team_id ===
                                            trade.initiator_team.id,
                                    )"
                                    :key="item.id"
                                    class="capitalize"
                                >
                                    {{ item.entity_type }} #{{ item.entity_id }}
                                </p>
                            </div>
                            <div v-if="trade.receiver_team">
                                <p
                                    class="mb-1 font-medium tracking-wide uppercase"
                                >
                                    {{ trade.receiver_team.name }} gives
                                </p>
                                <p
                                    v-for="item in trade.items.filter(
                                        (i) =>
                                            i.from_team_id ===
                                            trade.receiver_team!.id,
                                    )"
                                    :key="item.id"
                                    class="capitalize"
                                >
                                    {{ item.entity_type }} #{{ item.entity_id }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="canAccept(trade) || canReject(trade)"
                            class="mt-4 flex gap-2"
                        >
                            <Form
                                v-if="canAccept(trade)"
                                :action="
                                    tradeAccept({
                                        league: league.slug,
                                        trade: trade.id,
                                    }).url
                                "
                                method="post"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    size="sm"
                                >
                                    {{ processing ? 'Accepting…' : 'Accept' }}
                                </Button>
                            </Form>
                            <Form
                                v-if="canReject(trade)"
                                :action="
                                    tradeReject({
                                        league: league.slug,
                                        trade: trade.id,
                                    }).url
                                "
                                method="post"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ processing ? 'Declining…' : 'Decline' }}
                                </Button>
                            </Form>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div
                v-else
                class="py-16 text-center text-muted-foreground"
            >
                No trades yet.
                <span v-if="myTeam">
                    <Link
                        :href="tradeCreate({ league: league.slug }).url"
                        class="text-primary underline"
                    >
                        Propose the first one!
                    </Link>
                </span>
            </div>

            <!-- Pagination -->
            <div
                v-if="trades.last_page > 1"
                class="mt-6 flex justify-center gap-2"
            >
                <Button
                    v-if="trades.prev_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="trades.prev_page_url">← Prev</Link>
                </Button>
                <span class="px-3 py-1.5 text-sm text-muted-foreground">
                    {{ trades.current_page }} / {{ trades.last_page }}
                </span>
                <Button
                    v-if="trades.next_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="trades.next_page_url">Next →</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
