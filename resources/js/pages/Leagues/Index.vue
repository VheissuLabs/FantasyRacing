<script setup lang="ts">
    import { Head, Link, router } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import { Badge } from '@/components/ui/badge'
    import { Button } from '@/components/ui/button'
    import { Card, CardContent } from '@/components/ui/card'
    import { Checkbox } from '@/components/ui/checkbox'
    import { Label } from '@/components/ui/label'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'
    import { create as leaguesCreate } from '@/actions/App/Http/Controllers/Leagues/LeagueController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import { show as leagueShow } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    interface Season {
        id: number
        name: string
        year: number
        franchise_id: number
    }

    interface LeagueItem {
        id: number
        name: string
        slug: string
        description: string | null
        join_policy: 'open' | 'request' | 'invite_only'
        visibility: 'public' | 'private'
        max_teams: number | null
        members_count: number
        franchise: { name: string }
        season: { name: string }
        commissioner: { name: string }
    }

    interface Paginator<T> {
        data: T[]
        current_page: number
        last_page: number
        next_page_url: string | null
        prev_page_url: string | null
    }

    const props = defineProps<{
        leagues: Paginator<LeagueItem>
        franchises: Franchise[]
        seasons: Season[]
        filters: {
            franchise: string | null
            season: string | null
            available: boolean
            join_policy: string | null
        }
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Leagues', href: leaguesIndex().url },
    ]

    const franchiseFilter = ref(props.filters.franchise ?? '')
    const seasonFilter = ref(props.filters.season ?? '')
    const availableFilter = ref(props.filters.available)
    const joinPolicyFilter = ref(props.filters.join_policy ?? '')

    const filteredSeasons = ref(
        props.filters.franchise
            ? props.seasons.filter(
                  (season) =>
                      props.franchises.find(
                          (franchise) =>
                              franchise.slug === props.filters.franchise,
                      )?.id === season.franchise_id,
              )
            : props.seasons,
    )

    function applyFilters() {
        router.get(
            leaguesIndex.url({
                query: {
                    franchise: franchiseFilter.value || undefined,
                    season: seasonFilter.value || undefined,
                    available: availableFilter.value ? '1' : undefined,
                    join_policy: joinPolicyFilter.value || undefined,
                },
            }),
            {},
            { preserveScroll: true, replace: true },
        )
    }

    function onFranchiseChange() {
        seasonFilter.value = ''
        const selectedFranchise = props.franchises.find(
            (franchise) => franchise.slug === franchiseFilter.value,
        )
        filteredSeasons.value = selectedFranchise
            ? props.seasons.filter(
                  (season) => season.franchise_id === selectedFranchise.id,
              )
            : props.seasons
        applyFilters()
    }

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
</script>

<template>
    <Head title="League Directory" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h1 class="text-2xl font-bold">League Directory</h1>
                <Button as-child>
                    <Link :href="leaguesCreate().url">Create League</Link>
                </Button>
            </div>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <select
                    v-model="franchiseFilter"
                    @change="onFranchiseChange"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="">All Franchises</option>
                    <option
                        v-for="franchise in franchises"
                        :key="franchise.id"
                        :value="franchise.slug"
                    >
                        {{ franchise.name }}
                    </option>
                </select>

                <select
                    v-model="seasonFilter"
                    @change="applyFilters"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="">All Seasons</option>
                    <option
                        v-for="season in filteredSeasons"
                        :key="season.id"
                        :value="season.year"
                    >
                        {{ season.name }}
                    </option>
                </select>

                <select
                    v-model="joinPolicyFilter"
                    @change="applyFilters"
                    class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs ring-offset-background placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="">All Join Policies</option>
                    <option value="open">Open</option>
                    <option value="request">Request</option>
                    <option value="invite_only">Invite Only</option>
                </select>

                <Label
                    class="flex cursor-pointer items-center gap-2 font-normal"
                >
                    <Checkbox
                        :checked="availableFilter"
                        @update:checked="
                            (v: boolean) => {
                                availableFilter = v
                                applyFilters()
                            }
                        "
                    />
                    Available only
                </Label>
            </div>

            <!-- Grid -->
            <div
                v-if="leagues.data.length > 0"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Link
                    v-for="league in leagues.data"
                    :key="league.id"
                    :href="leagueShow({ league: league.slug }).url"
                    class="group"
                >
                    <Card
                        class="flex h-full flex-col transition hover:shadow-md"
                    >
                        <CardContent class="flex flex-1 flex-col p-5">
                            <div class="mb-2 flex items-start justify-between">
                                <h2
                                    class="font-semibold group-hover:text-primary"
                                >
                                    {{ league.name }}
                                </h2>
                                <Badge
                                    :variant="
                                        joinPolicyVariant(league.join_policy)
                                    "
                                    class="ml-2 shrink-0"
                                >
                                    {{ joinPolicyLabel(league.join_policy) }}
                                </Badge>
                            </div>
                            <p
                                v-if="league.description"
                                class="mb-3 line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ league.description }}
                            </p>
                            <div
                                class="mt-auto flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span
                                    >{{ league.franchise.name }} ·
                                    {{ league.season.name }}</span
                                >
                                <span
                                    >{{ league.members_count
                                    }}<span v-if="league.max_teams">
                                        / {{ league.max_teams }}</span
                                    >
                                    members</span
                                >
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Commissioner: {{ league.commissioner.name }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <div
                v-else
                class="py-16 text-center text-muted-foreground"
            >
                No leagues found.
                <Link
                    :href="leaguesCreate().url"
                    class="text-primary underline"
                >
                    Create one!
                </Link>
            </div>

            <!-- Pagination -->
            <div
                v-if="leagues.last_page > 1"
                class="mt-8 flex justify-center gap-2"
            >
                <Button
                    v-if="leagues.prev_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="leagues.prev_page_url">← Previous</Link>
                </Button>
                <span class="px-3 py-1.5 text-sm text-muted-foreground">
                    Page {{ leagues.current_page }} of {{ leagues.last_page }}
                </span>
                <Button
                    v-if="leagues.next_page_url"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="leagues.next_page_url">Next →</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
