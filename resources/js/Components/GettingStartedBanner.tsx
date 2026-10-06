import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

export interface GettingStartedStep {
    key: string;
    title: string;
    description: string;
    href: string;
    list_href: string;
    done: boolean;
    primary: boolean;
}

/**
 * Dashboard nudge for a shop that's still setting up. Starting a quote is the
 * headline action; the rest are the lists the quote builder prices from. The
 * server only sends steps this user can act on, and sends nothing once
 * they're all done, so there's no dismiss state to keep.
 */
export default function GettingStartedBanner({
    steps,
}: {
    steps: GettingStartedStep[];
}) {
    const quote = steps.find((step) => step.primary);
    const others = steps.filter((step) => !step.primary);
    const remaining = others.filter((step) => !step.done).length;
    console.log(others);
    return (
        <Card className="space-y-4 p-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold text-foreground">
                        {quote?.done
                            ? 'Finish setting up your shop'
                            : 'Welcome — ready to send your first quote?'}
                    </h3>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {quote?.description ??
                            'A few quick setups make quoting accurate.'}
                        {remaining > 0 &&
                            ' Filling in the lists below first makes the numbers more accurate.'}
                    </p>
                </div>
                {quote && (
                    <div className="flex items-center gap-3">
                        <Link
                            href={quote.list_href}
                            className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            View quotes
                        </Link>
                        <Button asChild size="lg">
                            <Link href={quote.href}>
                                {quote.done ? 'New quote' : quote.title}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            {others.length > 0 && (
                <ul className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    {others.map((step) => (
                        <li
                            key={step.key}
                            className="flex h-full flex-col justify-between gap-2 rounded-md border p-3 text-sm"
                        >
                            <Link
                                href={step.href}
                                className="flex items-start gap-2 transition hover:opacity-80"
                            >
                                <span
                                    className={
                                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border ' +
                                        (step.done
                                            ? 'border-transparent bg-success text-on-status'
                                            : 'border-muted-foreground/40')
                                    }
                                >
                                    {step.done && <Check className="size-3" />}
                                </span>
                                <span>
                                    <span className="block font-medium text-foreground">
                                        {step.title}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {step.description}
                                    </span>
                                </span>
                            </Link>
                            <Link
                                href={step.list_href}
                                className="pl-6 text-xs text-muted-foreground underline-offset-4 hover:underline"
                            >
                                View list
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
