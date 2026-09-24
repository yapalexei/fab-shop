export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    current_shop_id: number | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
        isOwner: boolean;
    };
    shop: {
        id: number;
        name: string;
        timezone: string;
        job_board_drag_enabled: boolean;
    } | null;
    enabledFeatures: string[];
    availableShops: { id: number; name: string }[];
    flash: {
        status: string | null;
        error: string | null;
    };
};
