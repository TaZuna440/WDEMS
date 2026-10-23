import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    LayoutGrid,
    ScrollText,
    UserRoundCog,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';

/**
 * Top-level navigation.
 *
 * Five items. Church categories, including Services, Fellowship,
 * and Discipleship, are reached through Events -> Church.
 * See docs/restructure/phase-1-navigation.md.
 */
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Events', href: '/events', icon: CalendarDays },
    { title: 'Registration', href: '/registrations', icon: ClipboardList },
    { title: 'Attendance', href: '/attendance', icon: ClipboardCheck },
    { title: 'Reports', href: '/reports', icon: BarChart3 },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const adminNavItems: NavItem[] =
        auth.user?.role === 'admin'
            ? [
                  {
                      title: 'User Management',
                      href: '/admin/users',
                      icon: UserRoundCog,
                  },
                  {
                      title: 'Audit Log',
                      href: '/admin/audit-log',
                      icon: ScrollText,
                  },
              ]
            : [];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem className="flex items-center justify-between gap-1">
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="min-w-0 flex-1"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <SidebarTrigger className="shrink-0" />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={[...mainNavItems, ...adminNavItems]} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
