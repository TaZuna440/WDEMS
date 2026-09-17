import { Link } from '@inertiajs/react';
import type { AuthLayoutProps } from '@/types';
import { home } from '@/routes';
import WdemsLogo from '@/components/wdems-logo';

export default function AuthLoginLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-2">
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
                <div className="absolute inset-0 bg-navy-900/75" />

                {/* Soft theme-adaptive accent glows */}
                <div
                    className="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                >
                    <div className="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-primary/20 blur-3xl" />
                    <div className="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-accent/20 blur-3xl" />
                </div>

                {/* Content */}
                <div className="relative z-10 flex max-w-lg flex-col items-center px-12 text-center">
                    <Link href={home()} className="flex items-center gap-4">

                        <WdemsLogo className="h-20" variant="on-dark" />

                        <div className="text-left">
                            <div className="text-2xl font-semibold leading-tight text-white">
                                Workflow-Driven
                            </div>
                            <div className="text-lg leading-tight text-lime-brand">
                                Event Management System
                            </div>
                        </div>
                    </Link>

                    <p className="mt-10 text-base leading-relaxed text-white/85">
                        Centralized event operations from inquiry to
                        completion.
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
                            <div className="text-base font-semibold leading-tight text-foreground">
                                Workflow-Driven
                            </div>
                            <div className="text-sm leading-tight text-lime-brand">
                                Event Management System
                            </div>
                        </div>
                    </Link>

                    <div className="glass-panel rounded-xl p-8 shadow-xl">
                        <div className="mb-6">
                            <h1 className="text-2xl font-semibold text-foreground">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-1 text-sm text-muted-foreground">
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