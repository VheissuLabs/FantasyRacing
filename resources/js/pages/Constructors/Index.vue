<script setup lang="ts">
    import { Head, Link, router } from '@inertiajs/vue3'
    import { ref } from 'vue'
    import {
        index as constructorsIndex,
        show as constructorShow,
    } from '@/actions/App/Http/Controllers/ConstructorProfileController'
    import { Card, CardContent } from '@/components/ui/card'
    import AppLayout from '@/layouts/AppLayout.vue'
    import { type BreadcrumbItem } from '@/types'

    interface Franchise {
        id: number
        name: string
        slug: string
    }

    interface ConstructorItem {
        id: number
        name: string
        slug: string
        logo_path: string | null
        is_active: boolean
        country: {
            id: number
            name: string
            emoji: string | null
        } | null
        franchise: { id: number; name: string }
    }

    interface Paginator<T> {
        data: T[]
        current_page: number
        last_page: number
        next_page_url: string | null
        prev_page_url: string | null
    }

    const props = defineProps<{
        constructors: Paginator<ConstructorItem>
        franchises: Franchise[]
        filters: { franchise: string | null }
    }>()

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Constructors', href: constructorsIndex().url },
    ]

    const franchiseFilter = ref(props.filters.franchise ?? '')

    function applyFilters() {
        router.get(
            constructorsIndex.url({
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
    <Head title="Constructors" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="mb-6 text-2xl font-bold">Constructors</h1>

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
                v-if="constructors.data.length > 0"
                class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
            >
                <Link
                    v-for="constructor in constructors.data"
                    :key="constructor.id"
                    :href="
                        constructorShow({ constructor: constructor.slug }).url
                    "
                    class="group"
                >
                    <Card
                        class="flex h-full flex-col transition hover:shadow-md"
                    >
                        <CardContent class="flex items-center gap-4 p-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-muted text-lg font-bold text-muted-foreground"
                            >
                                <img
                                    v-if="constructor.logo_path"
                                    :src="constructor.logo_path"
                                    :alt="constructor.name"
                                    class="h-full w-full rounded-lg object-contain p-1"
                                />
                                <span v-else>{{
                                    constructor.name.charAt(0)
                                }}</span>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold group-hover:text-primary"
                                >
                                    {{ constructor.name }}
                                </p>
                                <p
                                    v-if="constructor.country"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ constructor.country.emoji }}
                                    {{ constructor.country.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ constructor.franchise.name }}
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
                No constructors found.
            </div>

            <!-- Pagination -->
            <div
                v-if="constructors.last_page > 1"
                class="mt-8 flex justify-center gap-2"
            >
                <Link
                    v-if="constructors.prev_page_url"
                    :href="constructors.prev_page_url"
                    class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Previous
                </Link>
                <span class="px-3 py-1.5 text-sm text-muted-foreground">
                    Page {{ constructors.current_page }} of
                    {{ constructors.last_page }}
                </span>
                <Link
                    v-if="constructors.next_page_url"
                    :href="constructors.next_page_url"
                    class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
                >
                    Next
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
