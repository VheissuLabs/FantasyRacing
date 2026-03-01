<script setup lang="ts">
    import { Head, Form } from '@inertiajs/vue3'
    import { usePage } from '@inertiajs/vue3'
    import { accept as inviteAccept } from '@/actions/App/Http/Controllers/Leagues/InviteController'
    import { Button } from '@/components/ui/button'
    import { Card, CardContent } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'

    interface Invite {
        token: string
        email: string
        status: string
        expires_at: string
        league: {
            name: string
            slug: string
            franchise: { name: string }
        }
        inviter: { name: string } | null
    }

    defineProps<{
        invite: Invite
        expired: boolean
        alreadyUsed: boolean
    }>()

    const page = usePage()
    const auth = page.props.auth as
        | { user: { id: number; name: string } | null }
        | undefined
    const isAuthenticated = !!auth?.user
</script>

<template>
    <Head title="League Invite" />

    <AppLayout>
        <div class="mx-auto max-w-md px-4 py-16 text-center sm:px-6">
            <Card>
                <CardContent class="p-8">
                    <!-- Expired / used states -->
                    <template v-if="expired">
                        <div class="mb-4 text-4xl">⏰</div>
                        <h1 class="mb-2 text-xl font-bold">Invite Expired</h1>
                        <p class="text-sm text-muted-foreground">
                            This invite link has expired. Ask the commissioner
                            to send a new one.
                        </p>
                    </template>

                    <template v-else-if="alreadyUsed">
                        <div class="mb-4 text-4xl">✅</div>
                        <h1 class="mb-2 text-xl font-bold">Already Accepted</h1>
                        <p class="text-sm text-muted-foreground">
                            This invite has already been used.
                        </p>
                    </template>

                    <template v-else>
                        <div class="mb-4 text-4xl">🏁</div>
                        <h1 class="mb-2 text-xl font-bold">You're Invited!</h1>
                        <p class="mb-1 text-muted-foreground">
                            <span v-if="invite.inviter"
                                >{{ invite.inviter.name }} has invited you to
                                join</span
                            >
                            <span v-else>You've been invited to join</span>
                        </p>
                        <p class="mb-6 text-lg font-semibold">
                            {{ invite.league.name }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >({{ invite.league.franchise.name }})</span
                            >
                        </p>

                        <div v-if="isAuthenticated">
                            <Form
                                :action="
                                    inviteAccept({ token: invite.token }).url
                                "
                                method="post"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="w-full"
                                >
                                    {{
                                        processing
                                            ? 'Joining…'
                                            : 'Accept Invite & Join League'
                                    }}
                                </Button>
                            </Form>
                        </div>

                        <div
                            v-else
                            class="flex flex-col gap-3"
                        >
                            <Button as-child>
                                <a
                                    :href="`/register?redirect=/invites/${invite.token}`"
                                >
                                    Create Account & Join
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                as-child
                            >
                                <a
                                    :href="`/login?redirect=/invites/${invite.token}`"
                                >
                                    Log In to Accept
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
