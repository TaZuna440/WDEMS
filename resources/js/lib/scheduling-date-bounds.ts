export function schedulingDateBounds(): { minDate: Date; maxDate: Date } {
    const minDate = new Date();
    minDate.setHours(0, 0, 0, 0);

    const maxDate = new Date(minDate);
    maxDate.setFullYear(minDate.getFullYear() + 2);

    return { minDate, maxDate };
}
