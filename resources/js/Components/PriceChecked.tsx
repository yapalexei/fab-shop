import { Button } from '@/Components/ui/button';
import { useShopDateTime } from '@/lib/datetime';
import { ExternalLink } from 'lucide-react';

/**
 * "Price checked Oct 6, 2026" with a way to confirm it again, plus the link
 * back to the supplier's page. The server stamps the date when a price
 * changes or when `marked` is sent, so this only shows what's coming.
 */
export default function PriceChecked({
    checkedAt,
    marked,
    onMark,
    url,
}: {
    checkedAt: string | null | undefined;
    marked: boolean;
    onMark: () => void;
    url?: string | null;
}) {
    const format = useShopDateTime({ dateStyle: 'medium' });

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span>
                {marked
                    ? 'Price will be marked checked today'
                    : checkedAt
                      ? `Price checked ${format(checkedAt)}`
                      : 'Price not checked yet'}
            </span>
            {!marked && (
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto p-0 text-xs"
                    onClick={onMark}
                >
                    Mark checked
                </Button>
            )}
            {url && (
                <a
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                >
                    Open at supplier <ExternalLink className="size-3" />
                </a>
            )}
        </div>
    );
}
