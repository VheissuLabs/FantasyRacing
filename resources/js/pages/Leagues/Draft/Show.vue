<script setup lang="ts">
    import { Head, Form, router, useForm, usePoll } from '@inertiajs/vue3'
    import { echo } from '@laravel/echo-vue'
    import { ref, computed, watch, onUnmounted } from 'vue'
    import draggable from 'vuedraggable'
    import InputError from '@/components/InputError.vue'
    import { Alert, AlertDescription } from '@/components/ui/alert'
    import { Badge } from '@/components/ui/badge'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { Input } from '@/components/ui/input'
    import { Label } from '@/components/ui/label'
    import { toLocalDatetimeValue } from '@/composables/useDate'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import {
        pick as draftPick,
        setup as draftSetup,
        schedule as draftSchedule,
        updateOrder as draftUpdateOrder,
        start as draftStart,
        pause as draftPause,
        resume as draftResume,
        restart as draftRestart,
    } from '@/actions/App/Http/Controllers/Leagues/DraftController'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface Entity {
        id: number
        name: string
        slug: string
    }

    interface SeasonDriver {
        id: number
        driver_id: number
        driver: Entity
        constructor: Entity
    }

    interface SeasonConstructor {
        id: number
        constructor_id: number
        constructor: Entity
    }

    interface DraftOrder {
        id: number
        pick_number: number
        round: number
        entity_type_restriction: string | null
        status: 'pending' | 'active' | 'completed'
        fantasy_team: { id: number; name: string; user: { name: string } }
    }

    interface DraftPick {
        id: number
        pick_number: number
        entity_type: string
        entity_id: number
        is_auto_pick: boolean
        fantasy_team: { id: number; name: string; user: { name: string } }
    }

    interface DraftSession {
        id: number
        status: 'pending' | 'active' | 'paused' | 'completed'
        type: string
        current_pick_number: number
        total_picks: number
        pick_time_limit_seconds: number | null
        scheduled_at: string | null
        orders: DraftOrder[]
        picks: DraftPick[]
    }

    interface League {
        id: number
        name: string
        slug: string
        commissioner: { id: number; name: string }
        franchise: { name: string }
        season: { name: string }
    }

    interface MyTeam {
        id: number
        name: string
    }

    interface Team {
        id: number
        name: string
        user: { id: number; name: string }
    }

    const props = defineProps<{
        league: League
        session: DraftSession | null
        availableDrivers: SeasonDriver[]
        availableConstructors: SeasonConstructor[]
        allDrivers: SeasonDriver[]
        allConstructors: SeasonConstructor[]
        teams: Team[]
        myTeam: MyTeam | null
        isCommissioner: boolean
        teamCount: number
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: '/leagues' },
        {
            title: props.league.name,
            href: leagueShow({ league: props.league.slug }).url,
        },
        { title: 'Draft', href: '#' },
    ]

    // Setup form for commissioners
    const setupForm = useForm({
        type: 'snake',
        pick_time_limit_seconds: 60,
        scheduled_at: '',
        present_user_ids: [] as number[],
    })

    function submitSetup() {
        setupForm.present_user_ids = onlineUserIds.value
        setupForm.post(draftSetup({ league: props.league.slug }).url)
    }

    // Schedule form for commissioners (existing pending session)
    const scheduleForm = useForm({
        scheduled_at: props.session?.scheduled_at
            ? toLocalDatetimeValue(props.session.scheduled_at)
            : '',
        present_user_ids: [] as number[],
    })

    function submitSchedule() {
        scheduleForm.present_user_ids = onlineUserIds.value
        scheduleForm.post(draftSchedule({ league: props.league.slug }).url, {
            preserveScroll: true,
        })
    }

    // Editable team order for commissioners (pending state)
    const editableTeamOrder = ref<Team[]>([...props.teams])
    const orderForm = useForm({ team_ids: [] as number[] })

    function saveOrder() {
        orderForm.team_ids = editableTeamOrder.value.map((t) => t.id)
        orderForm.put(draftUpdateOrder({ league: props.league.slug }).url, {
            preserveScroll: true,
        })
    }

    function confirmRestart(e: Event) {
        if (
            !confirm(
                'Are you sure you want to restart the draft? All picks will be cleared.',
            )
        ) {
            e.preventDefault()
        }
    }

    // Reactive state updated via Echo
    const currentPickNumber = ref(props.session?.current_pick_number ?? 0)
    const sessionStatus = ref(props.session?.status ?? 'pending')
    const picks = ref<DraftPick[]>([...(props.session?.picks ?? [])])
    const orders = ref<DraftOrder[]>([...(props.session?.orders ?? [])])

    const currentOrder = computed(() =>
        orders.value.find((o) => o.pick_number === currentPickNumber.value),
    )

    const remainingOrders = computed(() =>
        orders.value.filter((o) => o.status !== 'completed'),
    )

    const isMyTurn = computed(
        () =>
            sessionStatus.value === 'active' &&
            props.myTeam !== null &&
            currentOrder.value?.fantasy_team?.id === props.myTeam.id,
    )

    const isMyTurnNext = computed(() => {
        if (
            sessionStatus.value !== 'active' ||
            !props.myTeam ||
            isMyTurn.value
        ) {
            return false
        }
        const nextOrder = orders.value.find(
            (o) => o.pick_number === currentPickNumber.value + 1,
        )
        return nextOrder?.fantasy_team?.id === props.myTeam.id
    })

    const restriction = computed(
        () => currentOrder.value?.entity_type_restriction ?? null,
    )

    const pickedDriverIds = computed(
        () =>
            new Set(
                picks.value
                    .filter((p) => p.entity_type === 'driver')
                    .map((p) => p.entity_id),
            ),
    )
    const pickedConstructorIds = computed(
        () =>
            new Set(
                picks.value
                    .filter((p) => p.entity_type === 'constructor')
                    .map((p) => p.entity_id),
            ),
    )

    const availableDrivers = computed(() =>
        props.availableDrivers.filter(
            (d) => !pickedDriverIds.value.has(d.driver_id),
        ),
    )
    const availableConstructors = computed(() =>
        props.availableConstructors.filter(
            (c) => !pickedConstructorIds.value.has(c.constructor_id),
        ),
    )

    // Search & filter for pick board
    const driverSearch = ref('')
    const constructorSearch = ref('')
    const constructorFilter = ref('')

    const filteredDrivers = computed(() => {
        let drivers = availableDrivers.value
        if (driverSearch.value) {
            const q = driverSearch.value.toLowerCase()
            drivers = drivers.filter(
                (d) =>
                    d.driver.name.toLowerCase().includes(q) ||
                    d.constructor.name.toLowerCase().includes(q),
            )
        }
        if (constructorFilter.value) {
            drivers = drivers.filter(
                (d) => d.constructor.name === constructorFilter.value,
            )
        }
        return drivers
    })

    const filteredConstructors = computed(() => {
        if (!constructorSearch.value) {
            return availableConstructors.value
        }
        const q = constructorSearch.value.toLowerCase()
        return availableConstructors.value.filter((c) =>
            c.constructor.name.toLowerCase().includes(q),
        )
    })

    const uniqueConstructorNames = computed(() =>
        [
            ...new Set(availableDrivers.value.map((d) => d.constructor.name)),
        ].sort(),
    )

    // Entity name map for roster panel
    const entityNameMap = computed(() => {
        const map: Record<string, string> = {}
        for (const sd of props.allDrivers) {
            map[`driver:${sd.driver_id}`] = sd.driver.name
        }
        for (const sc of props.allConstructors) {
            map[`constructor:${sc.constructor_id}`] = sc.constructor.name
        }
        return map
    })

    // Team rosters from picks
    const teamRosters = computed(() => {
        const rosters: Record<
            number,
            {
                fantasy_team: {
                    id: number
                    name: string
                    user: { name: string }
                }
                picks: DraftPick[]
            }
        > = {}
        for (const pick of picks.value) {
            if (!rosters[pick.fantasy_team.id]) {
                rosters[pick.fantasy_team.id] = {
                    fantasy_team: pick.fantasy_team,
                    picks: [],
                }
            }
            rosters[pick.fantasy_team.id].picks.push(pick)
        }
        return Object.values(rosters)
    })

    // Countdown timer
    const secondsLeft = ref<number | null>(null)
    let timerInterval: ReturnType<typeof setInterval> | null = null

    function startTimer(expiresAt: string) {
        if (timerInterval) {
            clearInterval(timerInterval)
        }
        timerInterval = setInterval(() => {
            const diff = Math.max(
                0,
                Math.round((new Date(expiresAt).getTime() - Date.now()) / 1000),
            )
            secondsLeft.value = diff
            if (diff <= 0 && timerInterval) {
                clearInterval(timerInterval)
            }
        }, 500)
    }

    // Lobby countdown
    const scheduledAt = ref<string | null>(props.session?.scheduled_at ?? null)
    const lobbyCountdown = ref('')
    let lobbyInterval: ReturnType<typeof setInterval> | null = null

    function startLobbyCountdown(targetDate: string) {
        if (lobbyInterval) {
            clearInterval(lobbyInterval)
        }
        const tick = () => {
            const diff = Math.max(
                0,
                Math.round(
                    (new Date(targetDate).getTime() - Date.now()) / 1000,
                ),
            )
            if (diff <= 0) {
                lobbyCountdown.value = 'Starting soon...'
                if (lobbyInterval) {
                    clearInterval(lobbyInterval)
                }
                return
            }
            const hours = Math.floor(diff / 3600)
            const minutes = Math.floor((diff % 3600) / 60)
            const secs = diff % 60
            lobbyCountdown.value =
                hours > 0
                    ? `${hours}h ${minutes}m ${secs}s`
                    : minutes > 0
                      ? `${minutes}m ${secs}s`
                      : `${secs}s`
        }
        tick()
        lobbyInterval = setInterval(tick, 1000)
    }

    if (scheduledAt.value && props.session?.status === 'pending') {
        startLobbyCountdown(scheduledAt.value)
    }

    // Sync forms and refs when Inertia updates props (e.g. after form submissions)
    watch(
        () => props.session,
        (session) => {
            scheduleForm.scheduled_at = session?.scheduled_at
                ? toLocalDatetimeValue(session.scheduled_at)
                : ''
            scheduledAt.value = session?.scheduled_at ?? null
            currentPickNumber.value = session?.current_pick_number ?? 0
            sessionStatus.value = session?.status ?? 'pending'
            picks.value = [...(session?.picks ?? [])]
            orders.value = [...(session?.orders ?? [])]

            if (session?.scheduled_at && session.status === 'pending') {
                startLobbyCountdown(session.scheduled_at)
            }
        },
    )

    watch(
        () => props.teams,
        (teams) => {
            editableTeamOrder.value = [...teams]
        },
    )

    onUnmounted(() => {
        if (timerInterval) {
            clearInterval(timerInterval)
        }
        if (lobbyInterval) {
            clearInterval(lobbyInterval)
        }
    })

    // Presence tracking via league channel (always available, even before a draft session)
    const onlineUserIds = ref<number[]>([])
    const leagueChannelName = `league.${props.league.id}`

    echo()
        .join(leagueChannelName)
        .here((users: { id: number }[]) => {
            onlineUserIds.value = users.map((u) => u.id)
        })
        .joining((user: { id: number }) => {
            if (!onlineUserIds.value.includes(user.id)) {
                onlineUserIds.value = [...onlineUserIds.value, user.id]
            }
        })
        .leaving((user: { id: number }) => {
            onlineUserIds.value = onlineUserIds.value.filter(
                (id) => id !== user.id,
            )
        })

    function isUserOnline(userId: number): boolean {
        return onlineUserIds.value.includes(userId)
    }

    const allMembersPresent = computed(() => {
        return (
            props.teams.length > 0 &&
            props.teams.every((t) => onlineUserIds.value.includes(t.user.id))
        )
    })

    // Draft event channel (only when session exists)
    let activeDraftChannel: string | null = null

    function joinDraftChannel(sessionId: number) {
        const channelName = `draft.${sessionId}`
        activeDraftChannel = channelName

        echo()
            .private(channelName)
            .listen(
                '.PickMade',
                (data: {
                    pick_number: number
                    team_id: number
                    entity_type: string
                    entity_id: number
                    entity_name: string
                    is_auto_pick: boolean
                    next_pick_number: number
                    next_team_id: number | null
                    timer_expires_at: string | null
                }) => {
                    picks.value.push({
                        id: 0,
                        pick_number: data.pick_number,
                        entity_type: data.entity_type,
                        entity_id: data.entity_id,
                        is_auto_pick: data.is_auto_pick,
                        fantasy_team: {
                            id: data.team_id,
                            name:
                                props.teams.find((t) => t.id === data.team_id)
                                    ?.name ?? '',
                            user: {
                                name:
                                    props.teams.find(
                                        (t) => t.id === data.team_id,
                                    )?.user.name ?? '',
                            },
                        },
                    })
                    // Update order statuses
                    const completedOrder = orders.value.find(
                        (o) => o.pick_number === data.pick_number,
                    )
                    if (completedOrder) {
                        completedOrder.status = 'completed'
                    }
                    const nextOrder = orders.value.find(
                        (o) => o.pick_number === data.next_pick_number,
                    )
                    if (nextOrder) {
                        nextOrder.status = 'active'
                    }

                    currentPickNumber.value = data.next_pick_number
                    secondsLeft.value = null
                },
            )
            .listen(
                '.PickTurnStarted',
                (data: {
                    pick_number: number
                    timer_expires_at: string | null
                }) => {
                    currentPickNumber.value = data.pick_number
                    if (data.timer_expires_at) {
                        startTimer(data.timer_expires_at)
                    }
                },
            )
            .listen('.DraftStarted', () => {
                sessionStatus.value = 'active'
                router.reload({
                    only: [
                        'session',
                        'availableDrivers',
                        'availableConstructors',
                    ],
                })
            })
            .listen('.DraftPaused', () => {
                sessionStatus.value = 'paused'
                if (timerInterval) {
                    clearInterval(timerInterval)
                    timerInterval = null
                }
                secondsLeft.value = null
            })
            .listen(
                '.DraftResumed',
                (data: { timer_expires_at: string | null }) => {
                    sessionStatus.value = 'active'
                    if (data.timer_expires_at) {
                        startTimer(data.timer_expires_at)
                    }
                },
            )
            .listen('.DraftCompleted', () => {
                sessionStatus.value = 'completed'
                router.reload()
            })
            .listen('.DraftRestarted', () => {
                router.reload()
            })
            .listen('.DraftOrderUpdated', () => {
                router.reload({ only: ['session'] })
            })
            .listen(
                '.DraftScheduled',
                (data: { scheduled_at: string | null }) => {
                    scheduledAt.value = data.scheduled_at
                    if (data.scheduled_at) {
                        startLobbyCountdown(data.scheduled_at)
                    } else {
                        lobbyCountdown.value = ''
                        if (lobbyInterval) {
                            clearInterval(lobbyInterval)
                        }
                    }
                },
            )
    }

    function leaveDraftChannel() {
        if (activeDraftChannel) {
            echo().leave(activeDraftChannel)
            activeDraftChannel = null
        }
    }

    // Poll for session creation when members are waiting for the commissioner
    const { stop: stopPolling } = usePoll(
        3000,
        { only: ['session'] },
        {
            autoStart: !props.session,
        },
    )

    watch(
        () => props.session?.id,
        (newId, oldId) => {
            if (oldId) {
                leaveDraftChannel()
            }
            if (newId) {
                stopPolling()
                joinDraftChannel(newId)
            }
        },
        { immediate: true },
    )

    onUnmounted(() => {
        leaveDraftChannel()
        echo().leave(leagueChannelName)
    })

    const activeTab = ref<'drivers' | 'constructors'>('drivers')

    watch(
        restriction,
        (value) => {
            if (value === 'constructor') {
                activeTab.value = 'constructors'
            } else if (value === 'driver') {
                activeTab.value = 'drivers'
            }
        },
        { immediate: true },
    )
</script>

<template>
    <Head :title="`${league.name} — Draft`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <div
                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-xl font-bold">{{ league.name }} Draft</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ league.season.name }}
                    </p>
                </div>
                <div
                    v-if="session"
                    class="flex items-center gap-3"
                >
                    <Badge
                        :variant="
                            sessionStatus === 'active'
                                ? 'default'
                                : sessionStatus === 'completed'
                                  ? 'secondary'
                                  : 'outline'
                        "
                    >
                        {{
                            sessionStatus.charAt(0).toUpperCase() +
                            sessionStatus.slice(1)
                        }}
                    </Badge>
                    <span class="text-sm text-muted-foreground">
                        Pick
                        {{ Math.min(currentPickNumber, session.total_picks) }} /
                        {{ session.total_picks }}
                    </span>
                </div>
            </div>

            <!-- No draft session yet — Commissioner setup -->
            <template v-if="!session">
                <div
                    v-if="isCommissioner"
                    class="mx-auto max-w-lg"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Set Up Draft</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                {{ teamCount }} team{{
                                    teamCount !== 1 ? 's' : ''
                                }}
                                in this league.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <form
                                @submit.prevent="submitSetup"
                                class="space-y-4"
                            >
                                <div class="grid gap-2">
                                    <Label>Draft Type</Label>
                                    <div class="flex gap-4">
                                        <Label
                                            class="flex cursor-pointer items-center gap-2 font-normal"
                                        >
                                            <input
                                                type="radio"
                                                v-model="setupForm.type"
                                                value="snake"
                                            />
                                            Snake
                                        </Label>
                                        <Label
                                            class="flex cursor-pointer items-center gap-2 font-normal"
                                        >
                                            <input
                                                type="radio"
                                                v-model="setupForm.type"
                                                value="linear"
                                            />
                                            Linear
                                        </Label>
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="pick_time_limit">
                                        Pick Time Limit (seconds)
                                    </Label>
                                    <Input
                                        id="pick_time_limit"
                                        v-model.number="
                                            setupForm.pick_time_limit_seconds
                                        "
                                        type="number"
                                        min="10"
                                        max="300"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Time each team has to make a pick before
                                        auto-pick.
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="setup_scheduled_at">
                                        Scheduled Date (optional)
                                    </Label>
                                    <Input
                                        id="setup_scheduled_at"
                                        v-model="setupForm.scheduled_at"
                                        type="datetime-local"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Members will be notified when a date is
                                        set.
                                    </p>
                                    <InputError
                                        :message="setupForm.errors.scheduled_at"
                                    />
                                </div>

                                <InputError
                                    :message="
                                        (
                                            setupForm.errors as Record<
                                                string,
                                                string
                                            >
                                        ).draft
                                    "
                                />

                                <Button
                                    :disabled="
                                        setupForm.processing || teamCount < 2
                                    "
                                    class="w-full"
                                >
                                    {{
                                        setupForm.processing
                                            ? 'Creating...'
                                            : 'Create Draft Session'
                                    }}
                                </Button>
                                <p
                                    v-if="teamCount < 2"
                                    class="text-xs text-destructive"
                                >
                                    At least 2 teams are needed to start a
                                    draft.
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                </div>
                <div
                    v-else
                    class="py-12 text-center text-muted-foreground"
                >
                    <p class="text-lg font-medium">No draft yet</p>
                    <p class="mt-1 text-sm">
                        The commissioner hasn't set up the draft for this
                        league.
                    </p>
                </div>
            </template>

            <!-- Draft session exists -->
            <template v-else>
                <!-- Commissioner controls -->
                <Card
                    v-if="isCommissioner"
                    class="mb-6"
                >
                    <CardContent class="space-y-4 py-3">
                        <div
                            v-if="
                                allMembersPresent && sessionStatus === 'pending'
                            "
                            class="flex items-center gap-3 rounded-lg border border-green-500/20 bg-green-500/5 px-4 py-2"
                        >
                            <span class="text-sm font-medium">
                                All members are here!
                            </span>
                            <span
                                v-if="session.total_picks === 0"
                                class="text-xs text-muted-foreground"
                            >
                                Generate the draft order first.
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="text-sm font-medium text-muted-foreground"
                                >Commissioner:</span
                            >

                            <!-- Pending state: Start + Schedule -->
                            <template v-if="sessionStatus === 'pending'">
                                <Form
                                    :action="
                                        draftStart({ league: league.slug }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="
                                            processing ||
                                            session.total_picks === 0
                                        "
                                        size="sm"
                                    >
                                        {{
                                            processing
                                                ? 'Starting...'
                                                : 'Start Draft'
                                        }}
                                    </Button>
                                </Form>

                                <span class="text-muted-foreground">|</span>

                                <form
                                    @submit.prevent="submitSchedule"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-model="scheduleForm.scheduled_at"
                                        type="datetime-local"
                                        class="h-8 text-sm"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            scheduleForm.processing ||
                                            !scheduleForm.scheduled_at
                                        "
                                    >
                                        {{
                                            scheduleForm.processing
                                                ? 'Saving...'
                                                : session.scheduled_at
                                                  ? 'Reschedule'
                                                  : 'Schedule'
                                        }}
                                    </Button>
                                    <InputError
                                        :message="
                                            scheduleForm.errors.scheduled_at
                                        "
                                    />
                                </form>
                            </template>

                            <!-- Active state: Pause -->
                            <Form
                                v-if="sessionStatus === 'active'"
                                :action="
                                    draftPause({ league: league.slug }).url
                                "
                                method="post"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    variant="secondary"
                                    size="sm"
                                >
                                    {{
                                        processing
                                            ? 'Pausing...'
                                            : 'Pause Draft'
                                    }}
                                </Button>
                            </Form>

                            <!-- Paused state: Resume -->
                            <Form
                                v-if="sessionStatus === 'paused'"
                                :action="
                                    draftResume({ league: league.slug }).url
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
                                            ? 'Resuming...'
                                            : 'Resume Draft'
                                    }}
                                </Button>
                            </Form>

                            <!-- Restart draft (active, paused, or completed) -->
                            <template
                                v-if="
                                    sessionStatus === 'active' ||
                                    sessionStatus === 'paused' ||
                                    sessionStatus === 'completed'
                                "
                            >
                                <span
                                    v-if="sessionStatus !== 'completed'"
                                    class="text-muted-foreground"
                                    >|</span
                                >
                                <Form
                                    :action="
                                        draftRestart({ league: league.slug })
                                            .url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                    @submit="confirmRestart"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        variant="destructive"
                                        size="sm"
                                    >
                                        {{
                                            processing
                                                ? 'Restarting...'
                                                : 'Restart Draft'
                                        }}
                                    </Button>
                                </Form>
                            </template>
                        </div>
                    </CardContent>
                </Card>

                <!-- Draft completed -->
                <Alert
                    v-if="sessionStatus === 'completed'"
                    class="mb-6"
                >
                    <AlertDescription>
                        The draft is complete! Check the standings to see your
                        team.
                    </AlertDescription>
                </Alert>

                <!-- Pre-draft lobby -->
                <Card
                    v-else-if="sessionStatus === 'pending'"
                    class="mb-6"
                >
                    <CardContent class="py-6">
                        <div class="text-center">
                            <template v-if="scheduledAt">
                                <p class="text-sm text-muted-foreground">
                                    Draft starts in
                                </p>
                                <p
                                    class="mt-1 font-mono text-3xl font-bold text-primary"
                                >
                                    {{ lobbyCountdown }}
                                </p>
                            </template>
                            <template v-else>
                                <p
                                    class="text-lg font-medium text-muted-foreground"
                                >
                                    Waiting for the commissioner to start the
                                    draft...
                                </p>
                            </template>
                        </div>

                        <div class="mt-6">
                            <p
                                class="mb-3 text-sm font-semibold text-muted-foreground"
                            >
                                Teams ({{ teams.length }})
                            </p>
                            <p
                                v-if="isCommissioner"
                                class="mb-2 text-xs text-muted-foreground"
                            >
                                Set the first-round pick order, then generate
                                order.
                            </p>
                            <draggable
                                v-if="isCommissioner"
                                v-model="editableTeamOrder"
                                item-key="id"
                                tag="ul"
                                class="space-y-0.5"
                            >
                                <template #item="{ element: team, index }">
                                    <li
                                        class="flex cursor-grab items-center gap-2 rounded px-2 py-1 text-sm hover:bg-muted active:cursor-grabbing"
                                    >
                                        <span
                                            class="inline-block h-2 w-2 shrink-0 rounded-full"
                                            :class="
                                                isUserOnline(team.user.id)
                                                    ? 'bg-green-500'
                                                    : 'bg-red-500'
                                            "
                                        />
                                        <span
                                            class="w-4 shrink-0 text-center font-mono text-xs text-muted-foreground"
                                            >{{ index + 1 }}</span
                                        >
                                        <span class="font-medium">{{
                                            team.user.name
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ team.name }}</span
                                        >
                                    </li>
                                </template>
                            </draggable>
                            <ul
                                v-else
                                class="space-y-0.5"
                            >
                                <li
                                    v-for="(team, index) in teams"
                                    :key="team.id"
                                    class="flex items-center gap-2 rounded px-2 py-1 text-sm"
                                >
                                    <span
                                        class="inline-block h-2 w-2 shrink-0 rounded-full"
                                        :class="
                                            isUserOnline(team.user.id)
                                                ? 'bg-green-500'
                                                : 'bg-red-500'
                                        "
                                    />
                                    <span
                                        class="w-4 shrink-0 text-center font-mono text-xs text-muted-foreground"
                                        >{{ index + 1 }}</span
                                    >
                                    <span class="font-medium">{{
                                        team.user.name
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ team.name }}</span
                                    >
                                </li>
                            </ul>
                            <div
                                v-if="isCommissioner"
                                class="mt-3"
                            >
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    :disabled="orderForm.processing"
                                    @click="saveOrder"
                                >
                                    {{
                                        orderForm.processing
                                            ? 'Saving...'
                                            : 'Save & Regenerate Order'
                                    }}
                                </Button>
                                <InputError
                                    :message="
                                        (
                                            orderForm.errors as Record<
                                                string,
                                                string
                                            >
                                        ).draft
                                    "
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- It's your turn banner -->
                <Alert
                    v-else-if="isMyTurn"
                    class="mb-6 animate-pulse border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400"
                >
                    <AlertDescription class="flex items-center justify-between">
                        <span class="font-semibold">It's your pick!</span>
                        <span
                            v-if="secondsLeft !== null"
                            class="font-mono text-sm"
                            >{{ secondsLeft }}s</span
                        >
                    </AlertDescription>
                </Alert>

                <!-- You're next banner -->
                <Alert
                    v-else-if="isMyTurnNext"
                    class="mb-6 animate-pulse border-yellow-500/50 bg-yellow-500/10 text-yellow-700 dark:text-yellow-400"
                >
                    <AlertDescription class="font-semibold">
                        You're next! Get ready to pick.
                    </AlertDescription>
                </Alert>

                <!-- On deck -->
                <Alert
                    v-else-if="sessionStatus === 'active' && currentOrder"
                    class="mb-6"
                >
                    <AlertDescription>
                        On the clock:
                        <strong>{{
                            currentOrder.fantasy_team.user.name
                        }}</strong>
                        <span class="text-muted-foreground"
                            >({{ currentOrder.fantasy_team.name }})</span
                        >
                        <span
                            v-if="secondsLeft !== null"
                            class="ml-2 font-mono"
                            >{{ secondsLeft }}s</span
                        >
                    </AlertDescription>
                </Alert>

                <!-- Paused banner -->
                <Alert
                    v-else-if="sessionStatus === 'paused'"
                    class="mb-6"
                >
                    <AlertDescription>
                        Draft is paused by the commissioner.
                    </AlertDescription>
                </Alert>

                <div class="grid gap-6">
                    <!-- Pick board -->
                    <Card v-if="isMyTurn && sessionStatus === 'active'">
                        <CardHeader class="pb-0">
                            <CardTitle class="text-sm">
                                {{
                                    restriction === 'constructor'
                                        ? 'Pick a Constructor'
                                        : restriction === 'driver'
                                          ? 'Pick a Driver'
                                          : 'Make Your Pick'
                                }}
                            </CardTitle>
                        </CardHeader>

                        <!-- Search / filter -->
                        <CardContent class="pb-0">
                            <template v-if="activeTab === 'drivers'">
                                <Input
                                    v-model="driverSearch"
                                    type="text"
                                    placeholder="Search drivers..."
                                    class="h-8 text-xs"
                                />
                                <select
                                    v-if="uniqueConstructorNames.length > 1"
                                    v-model="constructorFilter"
                                    class="mt-1.5 flex h-8 w-full rounded-md border border-input bg-background px-2.5 text-xs shadow-xs ring-offset-background focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <option value="">All constructors</option>
                                    <option
                                        v-for="name in uniqueConstructorNames"
                                        :key="name"
                                        :value="name"
                                    >
                                        {{ name }}
                                    </option>
                                </select>
                            </template>
                            <template v-else>
                                <Input
                                    v-model="constructorSearch"
                                    type="text"
                                    placeholder="Search constructors..."
                                    class="h-8 text-xs"
                                />
                            </template>
                        </CardContent>

                        <CardContent class="max-h-80 overflow-y-auto pt-0">
                            <!-- Drivers list -->
                            <template
                                v-if="
                                    activeTab === 'drivers' &&
                                    restriction !== 'constructor'
                                "
                            >
                                <Form
                                    v-for="sd in filteredDrivers"
                                    :key="sd.id"
                                    :action="
                                        draftPick({ league: league.slug }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                >
                                    <input
                                        type="hidden"
                                        name="entity_type"
                                        value="driver"
                                    />
                                    <input
                                        type="hidden"
                                        name="entity_id"
                                        :value="sd.driver_id"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="processing"
                                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-accent disabled:opacity-60"
                                    >
                                        <span>
                                            <span class="font-medium">{{
                                                sd.driver.name
                                            }}</span>
                                            <span
                                                class="ml-2 text-xs text-muted-foreground"
                                                >{{ sd.constructor.name }}</span
                                            >
                                        </span>
                                        <span class="text-xs text-primary"
                                            >Pick</span
                                        >
                                    </button>
                                </Form>
                                <p
                                    v-if="!filteredDrivers.length"
                                    class="px-4 py-3 text-sm text-muted-foreground"
                                >
                                    No drivers available.
                                </p>
                            </template>

                            <!-- Constructors list -->
                            <template
                                v-if="
                                    activeTab === 'constructors' &&
                                    restriction !== 'driver'
                                "
                            >
                                <Form
                                    v-for="sc in filteredConstructors"
                                    :key="sc.id"
                                    :action="
                                        draftPick({ league: league.slug }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                >
                                    <input
                                        type="hidden"
                                        name="entity_type"
                                        value="constructor"
                                    />
                                    <input
                                        type="hidden"
                                        name="entity_id"
                                        :value="sc.constructor_id"
                                    />
                                    <button
                                        type="submit"
                                        :disabled="processing"
                                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-accent disabled:opacity-60"
                                    >
                                        <span class="font-medium">{{
                                            sc.constructor.name
                                        }}</span>
                                        <span class="text-xs text-primary"
                                            >Pick</span
                                        >
                                    </button>
                                </Form>
                                <p
                                    v-if="!filteredConstructors.length"
                                    class="px-4 py-3 text-sm text-muted-foreground"
                                >
                                    No constructors available.
                                </p>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Pick history -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm">Pick History</CardTitle>
                        </CardHeader>
                        <CardContent class="max-h-96 overflow-y-auto">
                            <div
                                v-if="picks.length"
                                class="divide-y"
                            >
                                <div
                                    v-for="pick in [...picks].reverse()"
                                    :key="pick.id"
                                    class="flex items-center justify-between py-2.5 text-sm"
                                >
                                    <div>
                                        <span class="font-medium">{{
                                            entityNameMap[
                                                `${pick.entity_type}:${pick.entity_id}`
                                            ] ?? pick.entity_type
                                        }}</span>
                                        <span
                                            class="ml-1 text-xs text-muted-foreground"
                                            >#{{ pick.pick_number }}</span
                                        >
                                        <span
                                            v-if="pick.is_auto_pick"
                                            class="ml-1 text-xs text-muted-foreground"
                                            >(auto)</span
                                        >
                                    </div>
                                    <div
                                        class="text-right text-muted-foreground"
                                    >
                                        <span class="text-xs font-bold">{{
                                            pick.fantasy_team.user.name
                                        }}</span>
                                        <span class="ml-1 text-xs">{{
                                            pick.fantasy_team.name
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-else
                                class="py-6 text-center text-sm text-muted-foreground"
                            >
                                No picks yet.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Team rosters -->
                <Card
                    v-if="picks.length"
                    class="mt-6"
                >
                    <CardHeader>
                        <CardTitle class="text-sm">Team Rosters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="roster in teamRosters"
                                :key="roster.fantasy_team.id"
                                class="rounded-lg border p-3"
                            >
                                <h4
                                    class="mb-2 text-sm font-semibold"
                                    :class="{
                                        'text-primary':
                                            myTeam &&
                                            roster.fantasy_team.id ===
                                                myTeam.id,
                                    }"
                                >
                                    {{ roster.fantasy_team.user.name }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >{{ roster.fantasy_team.name }}</span
                                    >
                                </h4>
                                <div class="space-y-1">
                                    <div
                                        v-for="pick in roster.picks"
                                        :key="pick.id"
                                        class="flex items-center justify-between text-xs text-muted-foreground"
                                    >
                                        <span>{{
                                            entityNameMap[
                                                `${pick.entity_type}:${pick.entity_id}`
                                            ] ?? 'Unknown'
                                        }}</span>
                                        <span class="capitalize">{{
                                            pick.entity_type
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Draft order -->
                <Card
                    v-if="remainingOrders.length"
                    class="mt-6"
                >
                    <CardHeader>
                        <CardTitle class="text-sm">Draft Order</CardTitle>
                    </CardHeader>
                    <CardContent class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-xs text-muted-foreground"
                                >
                                    <th class="px-4 py-2">Pick</th>
                                    <th class="px-4 py-2">Round</th>
                                    <th class="px-4 py-2">Team</th>
                                    <th class="px-4 py-2">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="order in remainingOrders"
                                    :key="order.id"
                                    :class="[
                                        'border-b',
                                        order.pick_number ===
                                            currentPickNumber &&
                                        sessionStatus === 'active'
                                            ? 'bg-accent'
                                            : '',
                                    ]"
                                >
                                    <td
                                        class="px-4 py-2 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ order.pick_number }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-xs text-muted-foreground"
                                    >
                                        {{ order.round }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            :class="{
                                                'font-semibold text-primary':
                                                    myTeam &&
                                                    order.fantasy_team.id ===
                                                        myTeam.id,
                                            }"
                                        >
                                            {{ order.fantasy_team.user.name }}
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >{{
                                                    order.fantasy_team.name
                                                }}</span
                                            >
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-2 text-xs text-muted-foreground capitalize"
                                    >
                                        {{
                                            order.entity_type_restriction ??
                                            'any'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
