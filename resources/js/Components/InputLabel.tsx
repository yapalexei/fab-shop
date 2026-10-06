import { Label } from '@/Components/ui/label';
import { ComponentProps } from 'react';

export default function InputLabel({
    value,
    children,
    ...props
}: ComponentProps<typeof Label> & { value?: string }) {
    return <Label {...props}>{value ? value : children}</Label>;
}
