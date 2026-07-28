import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export function usePermissions() {
    const page = usePage<PageProps>();

    const can = (permission: string): boolean => page.props.auth.permissions.includes(permission);
    const hasRole = (role: string): boolean => page.props.auth.roles.includes(role);

    return {
        can,
        hasRole,
    };
}
