export type Status = 'pending' | 'done' | 'failed';
export type Kind = 'report' | 'chat' | string;

export interface SessionSummary {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    created_at: string | null;
}

export interface Message {
    id: number;
    role: 'assistant' | 'user';
    content: string;
    created_at: string | null;
}

export interface ActiveSession {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    error: string | null;
    messages: Message[];
}

// Pool of conversation starters; 3 are drawn per session. Phrased as things to
// understand/evaluate (never "buy X"), matching the advisor's ethical boundary.
export const SUGGESTED_QUESTIONS = [
    'La mia liquidità ferma è troppa?',
    'Quanto sono concentrato e dovrei preoccuparmi?',
    'Come sta andando davvero il mio rendimento?',
    'Il mio portafoglio è coerente col mio profilo di rischio?',
    'Quanto incidono i costi di gestione sul lungo periodo?',
    'Sono in linea con il mio obiettivo?',
    'Il mio PAC è abbastanza per raggiungere l’obiettivo?',
    'Quali sono i rischi principali del mio portafoglio?',
    'Cosa dovrei controllare questo mese?',
    'La mia esposizione a Bitcoin è troppo alta?',
];

/** Pick `count` distinct questions, varied by the session id so they're stable per session. */
export function pickQuestions(seed: number, count: number): string[] {
    const pool = [...SUGGESTED_QUESTIONS];
    const out: string[] = [];
    let s = seed + 1;
    while (out.length < count && pool.length > 0) {
        s = (s * 1103515245 + 12345) & 0x7fffffff; // deterministic LCG, varies by seed
        out.push(pool.splice(s % pool.length, 1)[0]);
    }
    return out;
}
