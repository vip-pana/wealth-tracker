import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, Mail } from 'lucide-react';
import { useState } from 'react';
import { ConnectionRow } from '@/Components/Settings/ConnectionRow';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

// Mirrors UpdatePasswordRequest, and SetupRequest before it: the settings form
// must not be a way to set a weaker password than first-run setup accepts.
const rules = [
    { label: 'Almeno 12 caratteri', test: (p: string) => p.length >= 12 },
    { label: 'Almeno una lettera', test: (p: string) => /\p{L}/u.test(p) },
    { label: 'Almeno un numero', test: (p: string) => /\d/.test(p) },
];

function RevealInput({
    id,
    value,
    onChange,
    autoComplete,
}: {
    id: string;
    value: string;
    onChange: (v: string) => void;
    autoComplete: string;
}) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                autoComplete={autoComplete}
                className="pr-10"
                required
            />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                className="absolute inset-y-0 right-0 flex items-center rounded-md px-3 text-muted-foreground hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                aria-label={visible ? 'Nascondi password' : 'Mostra password'}
            >
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
        </div>
    );
}

export function AccountCard({ email }: { email: string }) {
    const emailForm = useForm({ email, current_password: '' });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const met = rules.map((r) => r.test(passwordForm.data.password));
    const mismatch =
        passwordForm.data.password_confirmation !== '' &&
        passwordForm.data.password !== passwordForm.data.password_confirmation;

    const emailReady =
        emailForm.data.email !== '' &&
        emailForm.data.email !== email &&
        emailForm.data.current_password !== '';
    const passwordReady =
        met.every(Boolean) && !mismatch && passwordForm.data.current_password !== '';

    return (
        <>
            <ConnectionRow
                icon={Mail}
                title="Email"
                tone="idle"
                status={email}
                defaultOpen={false}
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        emailForm.patch('/account/email', {
                            preserveScroll: true,
                            // Never leave the password in client state after submit.
                            onFinish: () => emailForm.setData('current_password', ''),
                        });
                    }}
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="account-email">Nuova email</Label>
                        <Input
                            id="account-email"
                            type="email"
                            value={emailForm.data.email}
                            onChange={(e) => emailForm.setData('email', e.target.value)}
                            autoComplete="username"
                            required
                        />
                        {emailForm.errors.email && (
                            <p className="text-sm text-destructive">{emailForm.errors.email}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="email-current-password">Password attuale</Label>
                        <RevealInput
                            id="email-current-password"
                            value={emailForm.data.current_password}
                            onChange={(v) => emailForm.setData('current_password', v)}
                            autoComplete="current-password"
                        />
                        {emailForm.errors.current_password && (
                            <p className="text-sm text-destructive">{emailForm.errors.current_password}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            L&apos;email è il tuo identificativo di accesso: serve la password
                            attuale per cambiarla.
                        </p>
                    </div>

                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={emailForm.processing || !emailReady}
                        className="self-start"
                    >
                        {emailForm.processing ? 'Aggiornamento…' : 'Aggiorna email'}
                    </Button>
                </form>
            </ConnectionRow>

            <ConnectionRow
                icon={KeyRound}
                title="Password"
                tone="idle"
                status="Cambiala per disconnettere gli altri dispositivi"
                defaultOpen={false}
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        passwordForm.patch('/account/password', {
                            preserveScroll: true,
                            onSuccess: () => passwordForm.reset(),
                            onFinish: () => {
                                passwordForm.setData('current_password', '');
                                passwordForm.setData('password', '');
                                passwordForm.setData('password_confirmation', '');
                            },
                        });
                    }}
                    className="flex flex-col gap-3"
                >
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="current-password">Password attuale</Label>
                        <RevealInput
                            id="current-password"
                            value={passwordForm.data.current_password}
                            onChange={(v) => passwordForm.setData('current_password', v)}
                            autoComplete="current-password"
                        />
                        {passwordForm.errors.current_password && (
                            <p className="text-sm text-destructive">{passwordForm.errors.current_password}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="new-password">Nuova password</Label>
                        <RevealInput
                            id="new-password"
                            value={passwordForm.data.password}
                            onChange={(v) => passwordForm.setData('password', v)}
                            autoComplete="new-password"
                        />
                        <ul className="mt-1 flex flex-col gap-1">
                            {rules.map((rule, i) => (
                                <li
                                    key={rule.label}
                                    className={
                                        met[i]
                                            ? 'text-xs text-emerald-600 dark:text-emerald-400'
                                            : 'text-xs text-muted-foreground'
                                    }
                                >
                                    {met[i] ? '✓' : '○'} {rule.label}
                                </li>
                            ))}
                        </ul>
                        {passwordForm.errors.password && (
                            <p className="text-sm text-destructive">{passwordForm.errors.password}</p>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="confirm-password">Conferma nuova password</Label>
                        <RevealInput
                            id="confirm-password"
                            value={passwordForm.data.password_confirmation}
                            onChange={(v) => passwordForm.setData('password_confirmation', v)}
                            autoComplete="new-password"
                        />
                        {mismatch && (
                            <p className="text-sm text-destructive">Le password non coincidono.</p>
                        )}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Cambiando la password ogni altra sessione viene chiusa: è il modo
                        di revocare l&apos;accesso a un dispositivo smarrito. Non esiste un
                        recupero password, quindi conservala in un password manager.
                    </p>

                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={passwordForm.processing || !passwordReady}
                        className="self-start"
                    >
                        {passwordForm.processing ? 'Aggiornamento…' : 'Aggiorna password'}
                    </Button>
                </form>
            </ConnectionRow>
        </>
    );
}
