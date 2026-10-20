// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

import { FileText, MessageCircle } from 'lucide-react';
import { type Kind } from '@/Components/Advisor/types';

export function KindIcon({ kind, className }: { kind: Kind; className?: string }) {
    const Icon = kind === 'report' ? FileText : MessageCircle;
    return <Icon className={className} />;
}
