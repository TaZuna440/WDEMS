import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, CalendarDays, Church, Users } from 'lucide-react';
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
                    <div className="flex items-center justify-center">
                        <WdemsLogo className="h-48 sm:h-64 lg:h-80" />
                    </div>

                    <div className="flex flex-col items-start text-left">
                        <span className="border-border bg-secondary text-muted-foreground mb-6 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium">
                            <span className="bg-primary h-1.5 w-1.5 rounded-full" />
                            WDEMS
                        </span>

                        <h1 className="text-foreground mb-4 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                            Workflow-Driven
                            <br />
                            Event Management System
                        </h1>

                        <p className="text-muted-foreground mb-7 max-w-xl text-base sm:text-lg">
                            Plan events, manage registration, record attendance,
                            and review results in one secure workspace for
                            Community Runs and Church Event Management.
                        </p>

                        <div className="mb-8 grid w-full gap-3 sm:grid-cols-2">
                            <div className="border-border bg-card/70 flex items-start gap-3 rounded-xl border p-4">
                                <Activity className="text-lime-brand mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <h2 className="text-foreground font-medium">
                                        Community Runs
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        From event setup and public registration
                                        to attendance and reports.
                                    </p>
                                </div>
                            </div>
                            <div className="border-border bg-card/70 flex items-start gap-3 rounded-xl border p-4">
                                <Church className="text-lime-brand mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <h2 className="text-foreground font-medium">
                                        Church Events
                                    </h2>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Manage Services, Youth, Kids,
                                        Fellowship, Discipleship, and more.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="text-muted-foreground mb-8 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                            <span className="inline-flex items-center gap-2">
                                <CalendarDays className="text-lime-brand h-4 w-4" />
                                Event planning
                            </span>
                            <span className="inline-flex items-center gap-2">
                                <Users className="text-lime-brand h-4 w-4" />
                                Registration & attendance
                            </span>
                        </div>

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
                                    Log In
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
