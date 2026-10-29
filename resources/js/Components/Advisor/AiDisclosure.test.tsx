// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AiDisclosure } from '@/Components/Advisor/AiDisclosure';

// The point of this component is that the statement exists without the model
// having agreed to make it, so the test is on the words themselves: the WEA-10
// review found none of them anywhere in resources/js.
describe('AiDisclosure', () => {
    it('states the user is talking to an AI, in the present tense, on the advisor surface', () => {
        render(<AiDisclosure />);

        const text = screen.getByTestId('ai-disclosure').textContent ?? '';
        expect(text).toContain('Stai interagendo con un sistema di intelligenza artificiale');
    });

    it('puts the statement in the past tense where the text was written earlier', () => {
        render(<AiDisclosure where="derived" />);

        const text = screen.getByTestId('ai-disclosure').textContent ?? '';
        expect(text).toContain('scritte da un sistema di intelligenza artificiale');
    });

    it('carries the MiFID disclaimer in both placements', () => {
        for (const where of ['advisor', 'derived'] as const) {
            const { unmount } = render(<AiDisclosure where={where} />);

            const text = screen.getByTestId('ai-disclosure').textContent ?? '';
            expect(text).toContain('strumento informativo');
            expect(text).toContain('non è consulenza finanziaria');
            expect(text).toContain('OCF');

            unmount();
        }
    });
});
