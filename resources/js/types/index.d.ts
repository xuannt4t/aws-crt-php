export interface OrganizationUnit {
    id: number;
    parent_id: number | null;
    name: string;
    code: string;
    is_active: boolean;
}

export interface Role {
    id: number;
    name: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    organization_unit_id: number | null;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'> | null;
    is_system_admin?: boolean;
    is_active: boolean;
    employee_code?: string | null;
    phone?: string | null;
    job_title?: string | null;
    roles?: Role[];
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User;
        roles: string[];
        permissions: string[];
    };
};
