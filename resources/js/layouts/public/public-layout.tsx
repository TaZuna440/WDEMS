import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

type Props = {
    children: ReactNode;
};

/**
 * Layout for the anonymous public registration pages.
 *
 * Intentionally minimal: no sidebar, no app navigation, no auth
 * affordances. A visitor arriving from a share link should see the
 * event and the form, nothing else.
 *
 * Wired in resources/js/app.tsx for the two page names:
 *   - registrations/public  (the form)
 *   - registrations/closed  (the closed-state page)
 */
export default function PublicLayout({ children }: Props) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="border-b border-white/5">
                <div className="mx-auto flex w-full max-w-2xl items-center gap-3 px-6 py-4">
                    <AppLogoIcon className="h-6 w-6" />
                    <span className="text-sm font-semibold tracking-tight text-foreground">
                        WDEMS
                    </span>
                </div>
            </header>

            <main className="flex-1 py-10">
                <div className="mx-auto w-full max-w-2xl px-6">
                    {children}
                </div>
            </main>
        </div>
    );
}
