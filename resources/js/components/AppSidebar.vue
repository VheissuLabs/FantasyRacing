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
    import { index as constructorsIndex } from '@/actions/App/Http/Controllers/ConstructorProfileController'
    import { index as driversIndex } from '@/actions/App/Http/Controllers/DriverProfileController'
    import { index as leaguesIndex } from '@/actions/App/Http/Controllers/Leagues/LeagueDirectoryController'
    import NavFooter from '@/components/NavFooter.vue'
    import NavMain from '@/components/NavMain.vue'
    import NavUser from '@/components/NavUser.vue'
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar'
    import { dashboard, docs } from '@/routes'
    import { type NavItem } from '@/types'
    import AppLogo from './AppLogo.vue'

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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
