import { useForm } from '@inertiajs/react';
import { Check, Eye, EyeOff, X } from 'lucide-react';
import { useState } from 'react';
import AuthShell from '@/Components/Auth/AuthShell';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

// Mirrors SetupRequest's rules. Shown live so the requirements are visible
// while typing rather than only after a rejected submit.
const rules = [
    { label: 'Almeno 12 caratteri', test: (p: string) => p.length >= 12 },
    { label: 'Almeno una lettera', test: (p: string) => /\p{L}/u.test(p) },
    { label: 'Almeno un numero', test: (p: string) => /\d/.test(p) },
];

export default function Setup() {
    const [visible, setVisible] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const met = rules.map((r) => r.test(data.password));
    const mismatch =
        data.password_confirmation !== '' && data.password !== data.password_confirmation;
    const ready = met.every(Boolean) && !mismatch && data.name !== '' && data.email !== '';

    return (
        <AuthShell
            title="Primo accesso"
            heading="Crea il tuo accesso"
            subheading="Nessun account esiste ancora. Questa password protegge tutti i tuoi dati."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post('/setup', {
                        onFinish: () => {
                            setData('password', '');
                            setData('password_confirmation', '');
                        },
                    });
                }}
                className="flex flex-col gap-4"
            >
                <div className="flex flex-col gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoComplete="name"
                        autoFocus
                        required
                    />
                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        required
                    />
                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="password">Password</Label>
                    <div className="relative">
                        <Input
                            id="password"
                            type={visible ? 'text' : 'password'}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                            className="pr-10"
                            required
                        />
                        <button
                            type="button"
                            onClick={() => setVisible((v) => !v)}
                            className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring rounded-md"
                            aria-label={visible ? 'Nascondi password' : 'Mostra password'}
                        >
                            {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>

                    <ul className="mt-1 flex flex-col gap-1">
                        {rules.map((rule, i) => (
                            <li
                                key={rule.label}
                                className={
                                    met[i]
                                        ? 'flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400'
                                        : 'flex items-center gap-2 text-xs text-muted-foreground'
                                }
                            >
                                {met[i] ? <Check className="size-3.5" /> : <X className="size-3.5" />}
                                {rule.label}
                            </li>
                        ))}
                    </ul>

                    {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="password_confirmation">Conferma password</Label>
                    <Input
                        id="password_confirmation"
                        type={visible ? 'text' : 'password'}
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                        required
                    />
                    {mismatch && <p className="text-sm text-destructive">Le password non coincidono.</p>}
                </div>

                <div className="mt-1 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-300">
                    Non esiste un recupero password: se la dimentichi, l&apos;unico modo
                    per rientrare è dal terminale. Conservala in un password manager.
                </div>

                <Button type="submit" disabled={processing || !ready} className="mt-1 w-full">
                    {processing ? 'Creazione…' : 'Crea accesso'}
                </Button>
            </form>
        </AuthShell>
    );
}
