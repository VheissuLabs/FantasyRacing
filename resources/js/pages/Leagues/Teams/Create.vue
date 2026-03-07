<script setup lang="ts">
    import { Head, useForm } from '@inertiajs/vue3'
    import InputError from '@/components/InputError.vue'
    import { Button } from '@/components/ui/button'
    import {
        Card,
        CardContent,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card'
    import { Input } from '@/components/ui/input'
    import { Label } from '@/components/ui/label'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import { store as teamStore } from '@/actions/App/Http/Controllers/Leagues/FantasyTeamController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface League {
        id: number
        name: string
        slug: string
        franchise: { name: string }
        season: { name: string }
    }

    const props = defineProps<{
        league: League
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: leaguesIndex().url },
        { title: props.league.name, href: `/leagues/${props.league.slug}` },
        { title: 'Create Team', href: '#' },
    ]

    const form = useForm({
        name: '',
    })

    function submit() {
        form.post(teamStore({ league: props.league.slug }).url)
    }
</script>

<template>
    <Head title="Create Team" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6">
            <form @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Your Team</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ league.franchise.name }} &middot;
                            {{ league.season.name }} &middot; {{ league.name }}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-2">
                            <Label for="name">Team Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                maxlength="255"
                                placeholder="e.g. Turbo Racing"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                    </CardContent>
                    <CardFooter class="justify-end">
                        <Button :disabled="form.processing">
                            {{ form.processing ? 'Creating…' : 'Create Team' }}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
