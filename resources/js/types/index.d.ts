export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_shop_id: number | null;
    avatar_url: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    /** True on a self-hosted install: no plans, trials, add-ons or legal pages. */
    selfHosted: boolean;
    auth: {
        user: User;
        permissions: string[];
        isOwner: boolean;
        isPlatformAdmin: boolean;
    };
    shop: {
        id: number;
        name: string;
        timezone: string;
        job_board_drag_enabled: boolean;
        on_trial: boolean;
        trial_ends_at: string | null;
        show_trial_ended_notice: boolean;
        plan_name: string | null;
        can_upgrade: boolean;
        deletion_scheduled_for: string | null;
    } | null;
    twoFactorNotice: {
        deadline: string;
        days_left: number;
        overdue: boolean;
    } | null;
    enabledFeatures: string[];
    readOnlyFeatures: string[];
    availableShops: { id: number; name: string }[];
    flash: {
        status: string | null;
        error: string | null;
    };
};
