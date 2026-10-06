import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { router } from '@inertiajs/react';

export interface Member {
    id: number;
    name: string;
}

function PersonIcon({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 20 20" fill="currentColor" className={className}>
            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z" />
        </svg>
    );
}

/**
 * Who's working the job - purely informational (not a workflow step like a
 * stage move), so picking someone in the popover applies immediately rather
 * than going through a confirmation dialog. Read-only badge (no popover) for
 * anyone without jobs.update - they can still see who's on it.
 */
export default function JobAssigneeSelect({
    job,
    members,
    editable,
}: {
    job: { id: number; assigned_to: number | null; assignee: string | null };
    members: Member[];
    editable: boolean;
}) {
    const label = job.assignee ?? 'Unassigned';

    const badgeClasses =
        'inline-flex max-w-full items-center gap-1 truncate rounded-full border border-gray-200 py-0.5 pe-2 ps-1 text-xs text-gray-600';

    if (!editable) {
        return (
            <span className={badgeClasses}>
                <PersonIcon className="h-4 w-4 shrink-0 text-gray-400" />
                <span className="truncate">{label}</span>
            </span>
        );
    }

    const assign = (assignedTo: number | null) => {
        router.patch(
            route('jobs.assignee.update', job.id),
            { assigned_to: assignedTo },
            { preserveScroll: true },
        );
    };

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={`${badgeClasses} hover:bg-accent`}
                    aria-label="Assigned to"
                >
                    <PersonIcon className="h-4 w-4 shrink-0 text-gray-400" />
                    <span className="truncate">{label}</span>
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" className="w-48">
                <DropdownMenuItem
                    onSelect={() => assign(null)}
                    className={job.assigned_to === null ? 'font-medium' : ''}
                >
                    Unassigned
                </DropdownMenuItem>
                {members.map((member) => (
                    <DropdownMenuItem
                        key={member.id}
                        onSelect={() => assign(member.id)}
                        className={
                            job.assigned_to === member.id ? 'font-medium' : ''
                        }
                    >
                        {member.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
