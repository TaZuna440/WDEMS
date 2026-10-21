/** Keep partial date input in month/day/year order; submit only a real date. */
export function formatBirthdateInput(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 8);
    return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4)].filter(Boolean).join('/');
}

export function birthdateInputError(value: string): string | null {
    const digits = value.replace(/\D/g, '');
    const month = Number(digits.slice(0, 2));
    const day = Number(digits.slice(2, 4));
    if (digits.length >= 2 && (month < 1 || month > 12)) return 'Enter a month from 01 to 12.';
    if (digits.length >= 4 && (day < 1 || day > 31 || ([4, 6, 9, 11].includes(month) && day > 30) || (month === 2 && day > 29))) return 'Enter a valid day for this month.';
    if (digits.length === 8) {
        const year = Number(digits.slice(4));
        const date = new Date(year, month - 1, day);
        if (year < 1000 || date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return 'Enter a valid date of birth.';
    }
    return null;
}

export function birthdateToIso(value: string): string {
    const digits = value.replace(/\D/g, '');
    if (digits.length !== 8 || birthdateInputError(value)) return '';
    return `${digits.slice(4)}-${digits.slice(0, 2)}-${digits.slice(2, 4)}`;
}

export function phMobileDigits(value: string): string {
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('63')) digits = digits.slice(2);
    else if (digits.startsWith('0')) digits = digits.slice(1);
    return digits.slice(0, 10);
}
