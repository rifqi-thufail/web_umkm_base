import { MinusIcon, PlusIcon } from 'lucide-react';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';

export function QtyStepper({ value, max, onChange, disabled }: { value: number; max: number; onChange: (v: number) => void; disabled?: boolean }) {
    // Clamp instead of disabling the buttons: a disabled child dims the whole InputGroup.
    const set = (v: number) => {
        const next = Math.min(Math.max(1, v), Math.max(max, 1));
        if (next !== value) onChange(next);
    };
    return (
        <InputGroup className="h-8 w-28">
            <InputGroupAddon>
                <InputGroupButton size="icon-xs" aria-label="Kurangi" onClick={() => set(value - 1)} disabled={disabled}>
                    <MinusIcon />
                </InputGroupButton>
            </InputGroupAddon>
            <InputGroupInput aria-label="Jumlah" inputMode="numeric" className="text-center tabular" value={value} readOnly />
            <InputGroupAddon align="inline-end">
                <InputGroupButton size="icon-xs" aria-label="Tambah" onClick={() => set(value + 1)} disabled={disabled}>
                    <PlusIcon />
                </InputGroupButton>
            </InputGroupAddon>
        </InputGroup>
    );
}
