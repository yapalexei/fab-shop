import { ReactNode } from 'react';

export interface ResourceAction {
    action: string;
    permission: string;
    description: string;
}

export interface ResourceOption {
    value: string;
    label: string;
    actions: ResourceAction[];
}

const actionLabels: Record<string, string> = {
    view: 'View',
    create: 'Create',
    update: 'Edit',
    delete: 'Delete',
    cancel: 'Cancel',
    'manage-access': 'Manage access',
};

/** Actions that destroy or irreversibly change data - flagged in red. */
export function isDestructive(action: string): boolean {
    return action === 'delete' || action === 'cancel';
}

export function actionLabel(action: string): string {
    return actionLabels[action] ?? action;
}

/**
 * One row per shop resource with a cell per action it supports. The cell
 * itself (checkbox, allow/deny control) is up to the page; the grid keeps
 * both permission pages laid out the same way, with every action's exact
 * meaning spelled out under the row.
 */
export default function PermissionGrid({
    resources,
    renderCell,
}: {
    resources: ResourceOption[];
    renderCell: (action: ResourceAction) => ReactNode;
}) {
    return (
        <ul className="divide-y rounded-md border border-gray-200">
            {resources.map((resource) => (
                <li
                    key={resource.value}
                    className="flex flex-col gap-3 p-4 lg:flex-row lg:items-start lg:gap-6"
                >
                    <div className="lg:w-56 lg:shrink-0">
                        <p className="text-sm font-medium text-gray-900">
                            {resource.label}
                        </p>
                        <ul className="mt-1 space-y-0.5">
                            {resource.actions.map((action) => (
                                <li
                                    key={action.action}
                                    className={`text-xs ${isDestructive(action.action) ? 'text-red-600' : 'text-gray-500'}`}
                                >
                                    <span className="font-medium">
                                        {actionLabel(action.action)}:
                                    </span>{' '}
                                    {action.description}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="flex flex-1 flex-wrap gap-2">
                        {resource.actions.map((action) => (
                            <div key={action.permission}>
                                {renderCell(action)}
                            </div>
                        ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}
