import { describe, expect, it } from 'vitest';
import { documentTitle } from './documentTitle';

describe('documentTitle', () => {
    it('uses the DORMIDA WORK brand for titled pages', () => {
        expect(documentTitle('Công việc')).toBe('Công việc - DORMIDA WORK');
    });

    it('uses only the brand when a page has no title', () => {
        expect(documentTitle('')).toBe('DORMIDA WORK');
    });
});
