import { Button } from '@/Components/ui/button';
import { ComponentProps } from 'react';

export default function PrimaryButton(props: ComponentProps<typeof Button>) {
    return <Button {...props} />;
}
