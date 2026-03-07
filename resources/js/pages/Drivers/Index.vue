<script setup lang="ts">
    import { Head, Link, router } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import {
        index as driversIndex,
        show as driverShow,
    } from '@/actions/App/Http/Controllers/DriverProfileController'
    import { Card, CardContent } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    interface DriverItem {
        id: number
        name: string
        slug: string
        photo_path: string | null
        is_active: boolean
        country: {
            id: number
            name: string
            nationality: string
            emoji: string | null
        } | null
        franchise: { id: number; name: string }
        season_drivers: {
            id: number
            constructor: { id: number; name: string; slug: string }
        }[]
    }

    interface Paginator<T> {
        data: T[]
        current_page: number
        last_page: number
        next_page_url: string | null
        prev_page_url: string | null
    }

    const props = defineProps<{
        drivers: Paginator<DriverItem>
        franchises: Franchise[]
        filters: { franchise: string | null }
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Drivers', href: driversIndex().url },
    ]

    const franchiseFilter = ref(props.filters.franchise ?? '')

    function applyFilters() {
        router.get(
            driversIndex.url({
                query: {
                    franchise: franchiseFilter.value || undefined,
                },
            }),
            {},
            { preserveScroll: true, replace: true },
        )
    }
</script>

<template>
    <Head title="Drivers" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-2xl font-bold">Drivers</h1>

            <!-- Filters -->
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <select
                    v-model="franchiseFilter"
                    @change="applyFilters"
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
            </div>

            <!-- Grid -->
            <div
                v-if="drivers.data.length > 0"
                class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
            >
                <Link
                    v-for="driver in drivers.data"
                    :key="driver.id"
                    :href="driverShow({ driver: driver.slug }).url"
                    class="group"
                >
                    <Card
                        class="flex h-full flex-col transition hover:shadow-md"
                    >
                        <CardContent class="flex items-center gap-4 p-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-muted text-lg font-bold text-muted-foreground"
                            >
                                <img
                                    v-if="driver.photo_path"
                                    :src="driver.photo_path"
                                    :alt="driver.name"
                                    class="h-full w-full rounded-full object-cover"
                                />
                                <span v-else>{{ driver.name.charAt(0) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold group-hover:text-primary"
                                >
                                    {{ driver.name }}
                                </p>
                                <p
                                    v-if="driver.country"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ driver.country.emoji }}
                                    {{ driver.country.nationality }}
                                </p>
                                <p
                                    v-if="driver.season_drivers.length > 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ driver.season_drivers[0].constructor.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ driver.franchise.name }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <div
                v-else
                class="py-16 text-center text-muted-foreground"
            >
                No drivers found.
            </div>

            <!-- Pagination -->
            <div
                v-if="drivers.last_page > 1"
                class="mt-8 flex justify-center gap-2"
            >
                <Link
                    v-if="drivers.prev_page_url"
                    :href="drivers.prev_page_url"
                    class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Previous
                </Link>
                <span class="px-3 py-1.5 text-sm text-muted-foreground">
                    Page {{ drivers.current_page }} of {{ drivers.last_page }}
                </span>
                <Link
                    v-if="drivers.next_page_url"
                    :href="drivers.next_page_url"
                    class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Next
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
