export const CHURCH_PARTICIPANT_TYPES = [
    { value: 'Member', label: 'Member' },
    { value: 'First-Timer', label: 'First-Timer' },
    { value: 'Guest', label: 'Guest' },
] as const;

export function churchParticipantTypeLabel(value: string | null): string {
    const labels: Record<string, string> = {
        Member: 'Member',
        'First-Timer': 'First-Timer',
        Guest: 'Guest',
        Graduate: 'Graduate',
        'Ninong/Ninang': 'Ninong / Ninang',
    };

    return (value ? labels[value] : undefined) ?? '—';
}
