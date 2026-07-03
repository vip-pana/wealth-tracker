import { FileText, MessageCircle } from 'lucide-react';
import { type Kind } from '@/Components/Advisor/types';

export function KindIcon({ kind, className }: { kind: Kind; className?: string }) {
    const Icon = kind === 'report' ? FileText : MessageCircle;
    return <Icon className={className} />;
}
