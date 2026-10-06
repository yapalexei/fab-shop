import { cn } from '@/lib/utils';
import { Building2, Package } from 'lucide-react';
import { ReactNode, useState } from 'react';

type Shape = 'circle' | 'square';

/**
 * A square picture (or a fallback) clipped to a circle or rounded square.
 * The server always sends square crops, so `object-cover` never distorts
 * anything. A picture that fails to load falls back too, rather than
 * leaving a broken-image icon in a list.
 */
function Picture({
    src,
    fallback,
    shape,
    className,
}: {
    src?: string | null;
    fallback: ReactNode;
    shape: Shape;
    className?: string;
}) {
    const [failed, setFailed] = useState<string | null>(null);
    const showImage = !!src && failed !== src;

    return (
        <span
            className={cn(
                'inline-flex size-8 shrink-0 items-center justify-center overflow-hidden bg-muted text-muted-foreground',
                shape === 'circle' ? 'rounded-full' : 'rounded-md',
                className,
            )}
        >
            {showImage ? (
                <img
                    src={src}
                    alt=""
                    loading="lazy"
                    onError={() => setFailed(src)}
                    className="size-full object-cover"
                />
            ) : (
                fallback
            )}
        </span>
    );
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

/** A person's profile picture, or their initials when they have none. */
export function UserAvatar({
    name,
    src,
    className,
}: {
    name: string;
    src?: string | null;
    className?: string;
}) {
    return (
        <Picture
            src={src}
            shape="circle"
            className={className}
            fallback={
                <span className="text-[0.65em] font-medium leading-none">
                    {initials(name)}
                </span>
            }
        />
    );
}

/**
 * A customer's or business partner's logo in a circle, with a building icon
 * when none has been uploaded.
 */
export function LogoAvatar({
    src,
    className,
}: {
    src?: string | null;
    className?: string;
}) {
    return (
        <Picture
            src={src}
            shape="circle"
            className={className}
            fallback={<Building2 className="size-1/2" aria-hidden />}
        />
    );
}

/** A material's photo as a cropped square, or a generic box when none. */
export function SquareImage({
    src,
    className,
}: {
    src?: string | null;
    className?: string;
}) {
    return (
        <Picture
            src={src}
            shape="square"
            className={className}
            fallback={<Package className="size-1/2" aria-hidden />}
        />
    );
}
