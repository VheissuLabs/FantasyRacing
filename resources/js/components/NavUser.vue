<script setup lang="ts">
    import { Link, usePage } from '@inertiajs/vue3'
    import { ChevronsUpDown, LogIn } from 'lucide-vue-next'
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu'
    import {
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
        useSidebar,
    } from '@/components/ui/sidebar'
    import UserInfo from '@/components/UserInfo.vue'
    import UserMenuContent from './UserMenuContent.vue'

    const page = usePage()
    const user = page.props.auth?.user ?? null
    const { isMobile, state } = useSidebar()
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <!-- Authenticated: user dropdown -->
            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Guest: login link -->
            <SidebarMenuButton
                v-else
                size="lg"
                as-child
            >
                <Link href="/login">
                    <LogIn class="size-4" />
                    <span>Log in</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
