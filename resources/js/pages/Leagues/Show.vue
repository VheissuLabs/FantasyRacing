<script setup lang="ts">
    import { Head, Form, useForm } from '@inertiajs/vue3'
    import { Link, usePage } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import { show as draftShow } from '@/actions/App/Http/Controllers/Leagues/DraftController'
    import {
        create as teamCreate,
        show as teamShow,
    } from '@/actions/App/Http/Controllers/Leagues/FantasyTeamController'
    import {
        store as inviteStore,
        destroy as inviteDestroy,
        resend as inviteResend,
    } from '@/actions/App/Http/Controllers/Leagues/InviteController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import {
        join as leagueJoin,
        request as joinRequestStore,
        cancel as joinRequestCancel,
        approve as joinRequestApprove,
        reject as joinRequestReject,
    } from '@/actions/App/Http/Controllers/Leagues/LeagueJoinController'
    import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Leagues/LeagueSettingsController'
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
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface League {
        id: number
        name: string
        slug: string
        description: string | null
        join_policy: 'open' | 'request' | 'invite_only'
        visibility: 'public' | 'private'
        max_teams: number | null
        members_count: number
        is_active: boolean
        franchise: { name: string }
        season: { name: string }
        commissioner: { name: string }
    }

    interface Membership {
        id: number
        role: string
        joined_at: string
    }

    interface PendingJoinRequest {
        id: number
        status: string
    }

    interface JoinRequest {
        id: number
        message: string | null
        created_at: string
        user: { id: number; name: string }
    }

    interface FantasyTeam {
        id: number
        name: string
    }

    interface Member {
        id: number
        role: string
        joined_at: string
        user: {
            id: number
            name: string
            fantasy_team: { id: number; name: string } | null
        }
    }

    interface Invite {
        id: number
        email: string
        status: string
        expires_at: string
        created_at: string
    }

    interface Standing {
        id: number
        name: string
        user_name: string
        total_points: number
    }

    const props = defineProps<{
        league: League
        members: Member[]
        membership: Membership | null
        pendingRequest: PendingJoinRequest | null
        fantasyTeam: FantasyTeam | null
        isCommissioner: boolean
        invites: Invite[]
        joinRequests: JoinRequest[]
        inviteCodeUrl: string | null
        standings: Standing[]
    }>()

    const page = usePage()
    const auth = page.props.auth as
        | { user: { id: number; name: string } | null }
        | undefined
    const isAuthenticated = !!auth?.user

    const copied = ref(false)

    function copyInviteLink() {
        if (props.inviteCodeUrl) {
            navigator.clipboard.writeText(props.inviteCodeUrl)
            copied.value = true
            setTimeout(() => (copied.value = false), 2000)
        }
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: leaguesIndex().url },
        { title: props.league.name, href: '#' },
    ]

    function joinPolicyLabel(policy: string) {
        return (
            { open: 'Open', request: 'Request', invite_only: 'Invite Only' }[
                policy
            ] ?? policy
        )
    }

    function joinPolicyVariant(
        policy: string,
    ): 'default' | 'secondary' | 'outline' {
        return (
            {
                open: 'default' as const,
                request: 'secondary' as const,
                invite_only: 'outline' as const,
            }[policy] ?? 'outline'
        )
    }

    const inviteForm = useForm({ email: '' })

    function sendInvite() {
        inviteForm.post(inviteStore({ league: props.league.slug }).url, {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset(),
        })
    }
</script>

<template>
    <Head :title="league.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
            <!-- Header -->
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <h1 class="text-2xl font-bold">{{ league.name }}</h1>
                        <Badge :variant="joinPolicyVariant(league.join_policy)">
                            {{ joinPolicyLabel(league.join_policy) }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ league.franchise.name }} · {{ league.season.name }} ·
                        Commissioner: {{ league.commissioner.name }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="text-sm text-muted-foreground">
                        {{ league.members_count
                        }}<span v-if="league.max_teams">
                            / {{ league.max_teams }}</span
                        >
                        members
                    </span>
                    <Button
                        v-if="isCommissioner"
                        variant="outline"
                        size="sm"
                        as-child
                    >
                        <Link :href="settingsEdit({ league: league.slug }).url">
                            Settings
                        </Link>
                    </Button>
                </div>
            </div>

            <p
                v-if="league.description"
                class="mb-6 text-muted-foreground"
            >
                {{ league.description }}
            </p>

            <!-- Members -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="text-sm">Members</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <div
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <div>
                                <span class="font-medium">{{
                                    member.user.name
                                }}</span>
                                <span
                                    v-if="member.user.fantasy_team"
                                    class="ml-2 text-muted-foreground"
                                >
                                    {{ member.user.fantasy_team.name }}
                                </span>
                            </div>
                            <Badge
                                v-if="member.role === 'commissioner'"
                                variant="secondary"
                            >
                                Commissioner
                            </Badge>
                            <span
                                v-else
                                class="text-muted-foreground"
                            >
                                Member
                            </span>
                        </div>
                    </div>
                    <p
                        v-if="!members.length"
                        class="text-sm text-muted-foreground"
                    >
                        No members yet.
                    </p>
                </CardContent>
            </Card>

            <!-- Standings -->
            <Card
                v-if="standings.length"
                class="mb-6"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Standings</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <div
                            v-for="(team, index) in standings"
                            :key="team.id"
                            class="flex items-center justify-between py-2 text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-5 shrink-0 text-center font-mono text-xs text-muted-foreground"
                                    >{{ index + 1 }}</span
                                >
                                <span class="font-medium">{{ team.user_name }}</span>
                                <span class="text-muted-foreground">{{
                                    team.name
                                }}</span>
                            </div>
                            <span class="font-mono font-medium">{{
                                team.total_points
                            }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Already a member -->
            <div
                v-if="membership"
                class="mb-6 space-y-4"
            >
                <Alert>
                    <AlertDescription>
                        {{
                            membership.role === 'commissioner'
                                ? 'You are the commissioner of this league.'
                                : 'You are a member of this league.'
                        }}
                    </AlertDescription>
                </Alert>

                <!-- Team link or create CTA -->
                <Card v-if="fantasyTeam">
                    <CardContent class="flex items-center justify-between py-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Your team:
                            </p>
                            <Link
                                :href="
                                    teamShow({
                                        league: league.slug,
                                        team: fantasyTeam.id,
                                    }).url
                                "
                                class="font-medium text-primary hover:underline"
                            >
                                {{ fantasyTeam.name }}
                            </Link>
                        </div>
                        <Button as-child>
                            <Link :href="draftShow({ league: league.slug }).url">
                                Draft Room
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
                <Card
                    v-else
                    class="border-dashed"
                >
                    <CardContent class="py-5 text-center">
                        <p class="mb-3 text-sm text-muted-foreground">
                            You haven't created a team yet.
                        </p>
                        <Button as-child>
                            <Link
                                :href="teamCreate({ league: league.slug }).url"
                            >
                                Create Your Team
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Commissioner: Shareable invite link -->
            <Card
                v-if="isCommissioner && inviteCodeUrl"
                class="mb-6"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Shareable Invite Link</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-2">
                        <Input
                            :model-value="inviteCodeUrl"
                            readonly
                            class="min-w-0 flex-1 font-mono text-xs"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            @click="copyInviteLink"
                        >
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </Button>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Share this link to let anyone join your league directly.
                    </p>
                </CardContent>
            </Card>

            <!-- Commissioner: Invite members -->
            <Card
                v-if="isCommissioner"
                class="mb-6"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Invite Members</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form
                        @submit.prevent="sendInvite"
                        class="flex gap-2"
                    >
                        <Input
                            v-model="inviteForm.email"
                            type="email"
                            required
                            placeholder="email@example.com"
                            class="min-w-0 flex-1"
                        />
                        <Button
                            :disabled="inviteForm.processing"
                            size="sm"
                        >
                            {{
                                inviteForm.processing
                                    ? 'Sending...'
                                    : 'Send Invite'
                            }}
                        </Button>
                    </form>
                    <InputError :message="inviteForm.errors.email" />

                    <!-- Pending invites -->
                    <div v-if="invites.length">
                        <p
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Pending Invites
                        </p>
                        <div class="divide-y">
                            <div
                                v-for="invite in invites"
                                :key="invite.id"
                                class="flex items-center justify-between py-2 text-sm"
                            >
                                <span class="truncate">{{ invite.email }}</span>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Form
                                        :action="
                                            inviteResend({
                                                league: league.slug,
                                                invite: invite.id,
                                            }).url
                                        "
                                        method="post"
                                        #default="{ processing }"
                                        class="inline"
                                    >
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                            variant="link"
                                            size="sm"
                                            class="h-auto p-0 text-xs"
                                        >
                                            Resend
                                        </Button>
                                    </Form>
                                    <Form
                                        :action="
                                            inviteDestroy({
                                                league: league.slug,
                                                invite: invite.id,
                                            }).url
                                        "
                                        method="delete"
                                        #default="{ processing }"
                                        class="inline"
                                    >
                                        <Button
                                            type="submit"
                                            :disabled="processing"
                                            variant="link"
                                            size="sm"
                                            class="h-auto p-0 text-xs text-destructive"
                                        >
                                            Cancel
                                        </Button>
                                    </Form>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Commissioner: Join Requests -->
            <Card
                v-if="isCommissioner && joinRequests.length"
                class="mb-6"
            >
                <CardHeader>
                    <CardTitle class="text-sm">Pending Join Requests</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="divide-y">
                        <div
                            v-for="req in joinRequests"
                            :key="req.id"
                            class="flex items-center justify-between gap-3 py-2 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <span class="font-medium">{{
                                    req.user.name
                                }}</span>
                                <p
                                    v-if="req.message"
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ req.message }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <Form
                                    :action="
                                        joinRequestApprove({
                                            league: league.slug,
                                            joinRequest: req.id,
                                        }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                    class="inline"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Approve
                                    </Button>
                                </Form>
                                <Form
                                    :action="
                                        joinRequestReject({
                                            league: league.slug,
                                            joinRequest: req.id,
                                        }).url
                                    "
                                    method="post"
                                    #default="{ processing }"
                                    class="inline"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="processing"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                    >
                                        Reject
                                    </Button>
                                </Form>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Join actions (non-members only) -->
            <div
                v-if="!membership && isAuthenticated"
                class="mb-6"
            >
                <!-- Open join -->
                <Form
                    v-if="league.join_policy === 'open'"
                    :action="leagueJoin({ league: league.slug }).url"
                    method="post"
                    #default="{ processing }"
                >
                    <Button
                        type="submit"
                        :disabled="processing"
                    >
                        {{ processing ? 'Joining...' : 'Join League' }}
                    </Button>
                </Form>

                <!-- Request to join -->
                <template v-else-if="league.join_policy === 'request'">
                    <Alert v-if="pendingRequest">
                        <AlertDescription class="flex items-center gap-3">
                            <span>Your join request is pending approval.</span>
                            <Form
                                :action="
                                    joinRequestCancel({
                                        league: league.slug,
                                        joinRequest: pendingRequest.id,
                                    }).url
                                "
                                method="delete"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    variant="link"
                                    size="sm"
                                    class="h-auto p-0 text-xs"
                                >
                                    Cancel request
                                </Button>
                            </Form>
                        </AlertDescription>
                    </Alert>
                    <Form
                        v-else
                        :action="joinRequestStore({ league: league.slug }).url"
                        method="post"
                        #default="{ processing }"
                    >
                        <textarea
                            name="message"
                            rows="2"
                            placeholder="Optional message to the commissioner..."
                            class="mb-2 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <Button
                            type="submit"
                            :disabled="processing"
                        >
                            {{ processing ? 'Sending...' : 'Request to Join' }}
                        </Button>
                    </Form>
                </template>

                <!-- Invite only -->
                <p
                    v-else-if="league.join_policy === 'invite_only'"
                    class="text-sm text-muted-foreground"
                >
                    This league is invite-only. You need an invite link to join.
                </p>
            </div>

            <!-- Not authenticated -->
            <div
                v-if="!membership && !isAuthenticated"
                class="text-sm text-muted-foreground"
            >
                <a
                    href="/login"
                    class="text-primary underline"
                    >Log in</a
                >
                to join this league.
            </div>
        </div>
    </AppLayout>
</template>
