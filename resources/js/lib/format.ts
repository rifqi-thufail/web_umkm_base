const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export function formatRupiah(value: number): string {
    return rupiah.format(value);
}

export function formatDate(iso: string | null | undefined, withTime = false): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(iso));
}

export function timeAgo(iso: string | null | undefined): string {
    if (!iso) return '—';
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' });
    const steps: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [60, 'minute'],
        [24, 'hour'],
        [30, 'day'],
        [12, 'month'],
    ];
    let value = -seconds;
    for (const [size, unit] of steps) {
        if (Math.abs(value) < size) return rtf.format(value, unit);
        value = Math.round(value / size);
    }
    return rtf.format(value, 'year');
}

/** 0x1234…abcd */
export function shortHash(hash: string | null | undefined, head = 6, tail = 4): string {
    if (!hash) return '—';
    return hash.length <= head + tail + 2 ? hash : `${hash.slice(0, head + 2)}…${hash.slice(-tail)}`;
}

/** ORD-9f1c2a… → ORD-9F1C2A */
export function shortOrder(number: string): string {
    return number.replace(/^ORD-/, '').slice(0, 8).toUpperCase();
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0]!.toUpperCase())
        .join('');
}
