import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ChurchCategoryCards from '@/components/church-category-cards';

export default function ChurchCreateCards() {
    return (
        <>
            <Head title="Create Church Event" />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-6">
                <Link
                    href="/events/create"
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-2 text-sm transition-colors"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Back
                </Link>

                <div className="pl-10">
                    <h1 className="text-foreground text-2xl font-semibold">
                        Church Event
                    </h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Pick a category to create.
                    </p>
                </div>

                <ChurchCategoryCards mode="create" />
            </div>
        </>
    );
}

ChurchCreateCards.layout = {
    breadcrumbs: [
        { title: 'Events', href: '/events' },
        { title: 'Create', href: '/events/create' },
        { title: 'Church', href: '/events/create/church' },
    ],
};
