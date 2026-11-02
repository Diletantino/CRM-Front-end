define([], function () {
    return {
        range(period, now = new Date(), timeZone = 'UTC') {
            const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
                timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
            }).formatToParts(now).map(part => [part.type, part.value]));
            const today = new Date(Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day)));
            const from = new Date(today);
            const to = new Date(today);
            if (period === 'week') {
                from.setUTCDate(from.getUTCDate() - (from.getUTCDay() + 6) % 7);
                to.setTime(from.getTime());
                to.setUTCDate(to.getUTCDate() + 6);
            } else if (period === 'month') {
                from.setUTCDate(1);
                to.setUTCMonth(to.getUTCMonth() + 1, 0);
            }
            return {from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10)};
        },
    };
});
