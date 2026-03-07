<script setup lang="ts">
    import { Link } from '@inertiajs/vue3'
    import {
        BookOpen,
        Car,
        Folder,
        LayoutGrid,
        Trophy,
        Users,
    } from 'lucide-vue-next'
    import NavFooter from '@/components/NavFooter.vue'
    import NavMain from '@/components/NavMain.vue'
    import NavUser from '@/components/NavUser.vue'
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarGroup,
        SidebarGroupContent,
        SidebarGroupLabel,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar'
    import { type NavItem } from '@/types'
    import AppLogo from './AppLogo.vue'
    import { useGlobalFilters } from '@/composables/useGlobalFilters'
    import { index as constructorsIndex } from '@/actions/App/Http/Controllers/ConstructorProfileController'
    import { index as driversIndex } from '@/actions/App/Http/Controllers/DriverProfileController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import { dashboard, docs } from '@/routes'

    const {
        franchises,
        filters,
        availableSeasons,
        setFranchise,
        setSeason,
    } = useGlobalFilters()

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Drivers',
            href: driversIndex(),
            icon: Users,
        },
        {
            title: 'Constructors',
            href: constructorsIndex(),
            icon: Car,
        },
        {
            title: 'Leagues',
            href: leaguesIndex(),
            icon: Trophy,
        },
    ]

    const footerNavItems: NavItem[] = [
        {
            title: 'Github Repo',
            href: 'https://github.com/VheissuLabs/FantasyRacing',
            icon: Folder,
        },
        {
            title: 'Documentation',
            href: docs(),
            icon: BookOpen,
        },
    ]

    function onFranchiseChange(event: Event) {
        const value = (event.target as HTMLSelectElement).value
        setFranchise(value || null)
    }

    function onSeasonChange(event: Event) {
        const value = (event.target as HTMLSelectElement).value
        setSeason(value ? Number(value) : null)
    }
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Global Filters -->
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel>Filters</SidebarGroupLabel>
                <SidebarGroupContent class="space-y-2 px-2">
                    <select
                        :value="filters.franchise ?? ''"
                        @change="onFranchiseChange"
                        class="flex h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
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
                        v-if="availableSeasons.length > 0"
                        :value="filters.seasonId ?? ''"
                        @change="onSeasonChange"
                        class="flex h-8 w-full rounded-md border border-input bg-background px-2 text-xs shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <option value="">All Seasons</option>
                        <option
                            v-for="season in availableSeasons"
                            :key="season.id"
                            :value="season.id"
                        >
                            {{ season.name }}
                        </option>
                    </select>
                </SidebarGroupContent>
            </SidebarGroup>

            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
