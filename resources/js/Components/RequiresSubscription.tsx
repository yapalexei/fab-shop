import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { TriangleAlert } from 'lucide-react';

/**
 * Warning triangle for a menu item the shop's plan doesn't include (and it
 * hasn't bought as an add-on). Hover or keyboard focus shows the tooltip.
 */
export default function RequiresSubscription() {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className="ms-1.5 inline-flex items-center"
                    tabIndex={0}
                    aria-label="Requires a subscription"
                >
                    <TriangleAlert
                        className="size-4 text-amber-500"
                        aria-hidden="true"
                    />
                </span>
            </TooltipTrigger>
            <TooltipContent>Requires a subscription</TooltipContent>
        </Tooltip>
    );
}
