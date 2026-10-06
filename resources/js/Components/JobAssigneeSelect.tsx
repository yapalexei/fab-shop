import { UserAvatar } from '@/Components/Avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { User } from 'lucide-react';

export interface Member {
    id: number;
    name: string;
    avatar_url?: string | null;
}

/** The assignee's picture, or a generic person when the job has no one. */
function AssigneeAvatar({
    name,
    src,
}: {
    name: string | null;
    src?: string | null;
}) {
    return name ? (
        <UserAvatar name={name} src={src} className="size-5" />
    ) : (
        <span className="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <User className="size-3" aria-hidden />
        </span>
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
    job: {
        id: number;
        assigned_to: number | null;
        assignee: string | null;
        assignee_avatar_url?: string | null;
    };
    members: Member[];
    editable: boolean;
}) {
    const label = job.assignee ?? 'Unassigned';

    const badgeClasses =
        'inline-flex max-w-full items-center gap-1.5 truncate rounded-full border border-gray-200 py-0.5 pe-2 ps-0.5 text-xs text-gray-600';

    if (!editable) {
        return (
            <span className={badgeClasses}>
                <AssigneeAvatar
                    name={job.assignee}
                    src={job.assignee_avatar_url}
                />
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
                    <AssigneeAvatar
                        name={job.assignee}
                        src={job.assignee_avatar_url}
                    />
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
                        <UserAvatar
                            name={member.name}
                            src={member.avatar_url}
                            className="size-5"
                        />
                        {member.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
