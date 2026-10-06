import { Button } from '@/Components/ui/button';
import { ComponentProps } from 'react';

export default function DangerButton(props: ComponentProps<typeof Button>) {
    return <Button variant="destructive" {...props} />;
}
