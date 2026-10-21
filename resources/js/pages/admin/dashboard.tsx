import { Head } from '@inertiajs/react';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin Dashboard" />

            <div className="p-6">
                <h1 className="text-2xl font-semibold">Admin Dashboard</h1>

                <p className="text-muted-foreground mt-2">Welcome to WDEMS.</p>
            </div>
        </>
    );
}
