import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import WdemsLogo from '@/components/wdems-logo';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome to WDEMS" />
            <div className="bg-background relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 py-12">
                {/* Soft background accents — theme-adaptive */}
                <div
                    className="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                >
                    <div className="bg-primary/10 absolute -top-40 -left-40 h-96 w-96 rounded-full blur-3xl" />
                    <div className="bg-accent/10 absolute -right-40 -bottom-40 h-96 w-96 rounded-full blur-3xl" />
                </div>

                <main className="relative z-10 grid w-full max-w-6xl items-center gap-12 lg:grid-cols-2">
                    {/* Left: WD Monogram */}
                    <div className="flex items-center justify-center">
                        <WdemsLogo className="h-48 sm:h-64 lg:h-80" />
                    </div>

                    {/* Right: Content */}
                    <div className="flex flex-col items-start text-left">
                        <span className="border-border bg-secondary text-muted-foreground mb-6 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium">
                            <span className="bg-primary h-1.5 w-1.5 rounded-full" />
                            WDEMS
                        </span>

                        <h1 className="text-foreground mb-4 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                            Workflow-Driven
                            <br />
                            Event Management
                        </h1>

                        <p className="text-muted-foreground mb-8 max-w-md text-base sm:text-lg">
                            Manage Community Run events from registration setup
                            to attendance through a centralized workflow. Secure
                            access for authorized personnel only.
                        </p>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-11 items-center justify-center rounded-md px-8 text-sm font-medium transition-colors"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex h-11 items-center justify-center rounded-md px-8 text-sm font-medium transition-colors"
                                >
                                    Sign In
                                </Link>
                            )}
                        </div>
                    </div>
                </main>

                <footer className="text-muted-foreground absolute right-0 bottom-6 left-0 text-center text-xs">
                    &copy; 2026 Workflow-Driven Event Management System
                </footer>
            </div>
        </>
    );
}
