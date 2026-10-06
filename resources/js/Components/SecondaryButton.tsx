import { Button } from '@/Components/ui/button';
import { ComponentProps } from 'react';

export default function SecondaryButton({
    type = 'button',
    ...props
}: ComponentProps<typeof Button>) {
    return <Button variant="outline" type={type} {...props} />;
}
