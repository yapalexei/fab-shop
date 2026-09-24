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
        can: {
            manageShop: boolean;
            viewFinancials: boolean;
            manageShopSettings: boolean;
        } | null;
    };
    shop: {
        id: number;
        name: string;
        timezone: string;
    } | null;
    enabledFeatures: string[];
    availableShops: { id: number; name: string }[];
    flash: {
        status: string | null;
    };
};
