import { describe, expect, it } from 'vitest';
import { isScopePermission } from './permissionMatrix';

describe('isScopePermission', () => {
    it('recognises every known scope permission name', () => {
        const scopePermissions = [
            'task.view_own',
            'task.view_department',
            'task.view_all',
            'project.view_own',
            'project.view_department',
            'project.view_all',
        ];

        for (const permission of scopePermissions) {
            expect(isScopePermission(permission)).toBe(true);
        }
    });

    it('does not treat unrelated permissions as scope permissions', () => {
        expect(isScopePermission('task.view')).toBe(false);
        expect(isScopePermission('project.close')).toBe(false);
        expect(isScopePermission('system.manage_settings')).toBe(false);
        expect(isScopePermission('report.view_all')).toBe(false);
    });
});
