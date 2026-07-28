export interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    code: string;
    is_active: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_system_admin: boolean;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
    };
};
