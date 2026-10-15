import { SidebarTrigger } from '@/components/ui/sidebar';

/**
 * Floating sidebar trigger. Absolutely positioned at the top-left
 * of the main content area. Takes no layout space so it sits
 * inline with each page's own heading rather than in a separate
 * bar above it.
 *
 * Styled as a small glass button with an explicit border and icon
 * color per theme so it reads clearly against the page image in
 * both light and dark modes. The default SidebarTrigger ghost
 * treatment was near-invisible on the light glass.
 */
export function AppSidebarHeader() {
    return (
        <div className="pointer-events-none absolute left-6 top-6 z-20 md:left-6">
            <SidebarTrigger className="pointer-events-auto -ml-1 h-9 w-9 rounded-md border border-[#CBD7E3] bg-white/70 text-[#13253B] shadow-sm backdrop-blur-sm transition-colors hover:bg-white/90 hover:text-[#13253B] dark:border-white/15 dark:bg-white/10 dark:text-white dark:hover:bg-white/20 dark:hover:text-white" />
        </div>
    );
}
