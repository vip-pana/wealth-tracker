import { useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import AuthShell from '@/Components/Auth/AuthShell';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function Login() {
    const [visible, setVisible] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    return (
        <AuthShell
            title="Accedi"
            heading="Bentornato"
            subheading="Inserisci le tue credenziali per continuare."
        >
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    // Never keep the password in client state after submit.
                    post('/login', { onFinish: () => setData('password', '') });
                }}
                className="flex flex-col gap-4"
            >
                <div className="flex flex-col gap-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoComplete="username"
                        autoFocus
                        required
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="password">Password</Label>
                    <div className="relative">
                        <Input
                            id="password"
                            type={visible ? 'text' : 'password'}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="current-password"
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
                </div>

                {/* The failed-credentials and rate-limit messages both arrive on
                    `email`, so one block covers every login failure. */}
                {errors.email && (
                    <p className="text-sm text-destructive" role="alert">{errors.email}</p>
                )}

                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="size-4 rounded border-input accent-primary"
                    />
                    Ricordami su questo dispositivo
                </label>

                <Button type="submit" disabled={processing} className="mt-2 w-full">
                    {processing ? 'Accesso…' : 'Accedi'}
                </Button>
            </form>
        </AuthShell>
    );
}
