import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import type { AuthLayoutProps } from '@/types';
import { home } from '@/routes';
import WdemsLogo from '@/components/wdems-logo';

export default function AuthLoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    // Opt this page into the photographic background. The image is
    // removed from the default body::before stack — see app.css
    // "login-only background image". Adding the class on mount and
    // removing it on unmount keeps every other page on the plain
    // gradient backdrop.
    useEffect(() => {
        document.body.classList.add('wdems-with-bg');
        return () => {
            document.body.classList.remove('wdems-with-bg');
        };
    }, []);

    return (
        <div className="bg-background grid min-h-svh lg:grid-cols-2">
            {/* Left: Brand panel with background image (hidden on mobile) */}
            <div className="relative hidden flex-col items-center justify-center overflow-hidden lg:flex">
                {/* Background image */}
                <div
                    className="absolute inset-0 bg-cover bg-center bg-no-repeat"
                    style={{
                        backgroundImage: "url('/brand/login-background.png')",
                    }}
                />

                {/* Slight blur for depth */}
                <div className="absolute inset-0 backdrop-blur-sm" />

                {/* Dark overlay so the logo and tagline read clearly */}
                <div className="bg-navy-900/75 absolute inset-0" />

                {/* Soft theme-adaptive accent glows */}
                <div
                    className="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                >
                    <div className="bg-primary/20 absolute -top-40 -left-40 h-96 w-96 rounded-full blur-3xl" />
                    <div className="bg-accent/20 absolute -right-40 -bottom-40 h-96 w-96 rounded-full blur-3xl" />
                </div>

                {/* Content */}
                <div className="relative z-10 flex max-w-lg flex-col items-center px-12 text-center">
                    <Link href={home()} className="flex items-center gap-4">
                        <WdemsLogo className="h-20" variant="on-dark" />

                        <div className="text-left">
                            <div className="text-2xl leading-tight font-semibold text-white">
                                Workflow-Driven
                            </div>
                            <div className="text-lime-brand text-lg leading-tight">
                                Event Management System
                            </div>
                        </div>
                    </Link>

                    <p className="mt-10 text-base leading-relaxed text-white/85">
                        Manage Community Run events from registration setup to
                        attendance through a centralized workflow.
                    </p>
                    <p className="mt-2 text-sm text-white/60">
                        Secure access for authorized personnel only.
                    </p>
                </div>
            </div>

            {/* Right: Form panel (adapts to theme) */}
            <div className="flex flex-col items-center justify-center px-6 py-12 md:px-10">
                <div className="w-full max-w-md">
                    {/* Logo shown on mobile only */}
                    <Link
                        href={home()}
                        className="mb-8 flex flex-col items-center justify-center gap-2 lg:hidden"
                    >
                        <WdemsLogo className="h-14" />

                        <div className="text-center">
                            <div className="text-foreground text-base leading-tight font-semibold">
                                Workflow-Driven
                            </div>
                            <div className="text-lime-brand text-sm leading-tight">
                                Event Management System
                            </div>
                        </div>
                    </Link>

                    <div className="glass-panel rounded-xl p-8 shadow-xl">
                        <div className="mb-6">
                            <h1 className="text-foreground text-2xl font-semibold">
                                {title}
                            </h1>
                            {description && (
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {description}
                                </p>
                            )}
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
