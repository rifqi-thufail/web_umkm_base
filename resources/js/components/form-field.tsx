import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

type Props = Omit<React.ComponentProps<typeof Input>, 'onChange' | 'value'> & {
    id: string;
    label: React.ReactNode;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    description?: React.ReactNode;
    labelAside?: React.ReactNode;
};

/** Label + input + description/error, following shadcn's Field pattern. */
export function TextField({ id, label, value, onChange, error, description, labelAside, className, ...input }: Props) {
    return (
        <Field data-invalid={!!error || undefined} className={className}>
            {labelAside ? (
                <div className="flex items-center justify-between gap-2">
                    <FieldLabel htmlFor={id}>{label}</FieldLabel>
                    {labelAside}
                </div>
            ) : (
                <FieldLabel htmlFor={id}>{label}</FieldLabel>
            )}
            <Input id={id} name={id} value={value} onChange={(e) => onChange(e.target.value)} aria-invalid={!!error || undefined} {...input} />
            {error ? <FieldError>{error}</FieldError> : description ? <FieldDescription>{description}</FieldDescription> : null}
        </Field>
    );
}
