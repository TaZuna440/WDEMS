import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import WdemsLogo from '@/components/wdems-logo';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome to WDEMS" />
            <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-6 py-12">
                {/* Soft background accents — theme-adaptive */}
                <div className="pointer-events-none absolute inset-0" aria-hidden="true">
                    <div className="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-accent/10 blur-3xl" />
                </div>

                <main className="relative z-10 grid w-full max-w-6xl items-center gap-12 lg:grid-cols-2">
                    {/* Left: WD Monogram */}
                    <div className="flex items-center justify-center">

                        <WdemsLogo className="h-48 sm:h-64 lg:h-80" />
                        
                    </div>

                    {/* Right: Content */}
                    <div className="flex flex-col items-start text-left">
                        <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-border bg-secondary px-3 py-1 text-xs font-medium text-muted-foreground">
                            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
                            WDEMS
                        </span>

                        <h1 className="mb-4 text-4xl font-semibold leading-tight tracking-tight text-foreground sm:text-5xl">
                            Workflow-Driven
                            <br />
                            Event Management
                        </h1>

                        <p className="mb-8 max-w-md text-base text-muted-foreground sm:text-lg">
                            Manage Community Run events from registration setup
                            to attendance through a centralized workflow.
                            Secure access for authorized personnel only.
                        </p>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Sign In
                                </Link>
                            )}
                        </div>
                    </div>
                </main>

                <footer className="absolute bottom-6 left-0 right-0 text-center text-xs text-muted-foreground">
                    &copy; 2026 Workflow-Driven Event Management System
                </footer>
            </div>
        </>
    );
}
