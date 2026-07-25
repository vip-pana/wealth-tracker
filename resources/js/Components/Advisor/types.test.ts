import { describe, it, expect } from 'vitest';
import { pickQuestions, SUGGESTED_QUESTIONS } from '@/Components/Advisor/types';

describe('pickQuestions', () => {
    it('returns the requested count', () => {
        expect(pickQuestions(1, 3)).toHaveLength(3);
        expect(pickQuestions(42, 5)).toHaveLength(5);
    });

    it('returns distinct questions', () => {
        const picks = pickQuestions(7, 5);
        expect(new Set(picks).size).toBe(picks.length);
    });

    it('only draws from the known pool', () => {
        for (const q of pickQuestions(3, 4)) {
            expect(SUGGESTED_QUESTIONS).toContain(q);
        }
    });

    it('is stable for the same seed', () => {
        expect(pickQuestions(9, 3)).toEqual(pickQuestions(9, 3));
    });

    it('varies by seed', () => {
        // Different seeds should not (in general) yield the same ordered draw.
        expect(pickQuestions(1, 5)).not.toEqual(pickQuestions(2, 5));
    });

    it('never returns more than the pool size even if asked', () => {
        const picks = pickQuestions(1, SUGGESTED_QUESTIONS.length + 5);
        expect(picks).toHaveLength(SUGGESTED_QUESTIONS.length);
        expect(new Set(picks).size).toBe(SUGGESTED_QUESTIONS.length);
    });
});
