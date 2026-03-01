<script setup lang="ts">
    import { Head, Form } from '@inertiajs/vue3'
    import { usePage } from '@inertiajs/vue3'
    import { Button } from '@/components/ui/button'
    import { Card, CardContent } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { joinViaCode } from '@/actions/App/Http/Controllers/Leagues/LeagueJoinController'

    interface League {
        id: number
        name: string
        slug: string
        invite_code: string
        franchise: { name: string }
        season: { name: string }
        commissioner: { name: string }
        members_count: number
        is_full: boolean
    }

    defineProps<{
        league: League
    }>()

    const page = usePage()
    const auth = page.props.auth as
        | { user: { id: number; name: string } | null }
        | undefined
    const isAuthenticated = !!auth?.user
</script>

<template>
    <Head :title="`Join ${league.name}`" />
    <AppLayout>
        <div class="mx-auto max-w-md px-4 py-16 text-center sm:px-6">
            <Card>
                <CardContent class="p-8">
                    <template v-if="league.is_full">
                        <h1 class="mb-2 text-xl font-bold">League Full</h1>
                        <p class="text-sm text-muted-foreground">
                            This league has reached its maximum number of
                            members.
                        </p>
                    </template>

                    <template v-else>
                        <h1 class="mb-2 text-xl font-bold">Join League</h1>
                        <p class="mb-1 text-muted-foreground">
                            You've been invited to join
                        </p>
                        <p class="mb-1 text-lg font-semibold">
                            {{ league.name }}
                        </p>
                        <p class="mb-4 text-sm text-muted-foreground">
                            {{ league.franchise.name }} ·
                            {{ league.season.name }} ·
                            {{ league.members_count }} member{{
                                league.members_count !== 1 ? 's' : ''
                            }}
                        </p>

                        <div v-if="isAuthenticated">
                            <Form
                                :action="
                                    joinViaCode({
                                        inviteCode: league.invite_code,
                                    }).url
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
                                            ? 'Joining...'
                                            : 'Join League'
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
                                    :href="`/register?redirect=/join/${league.invite_code}`"
                                >
                                    Create Account & Join
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                as-child
                            >
                                <a
                                    :href="`/login?redirect=/join/${league.invite_code}`"
                                >
                                    Log In to Join
                                </a>
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
