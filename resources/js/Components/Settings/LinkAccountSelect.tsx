import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import type { BankAccountEntry, LinkableAsset } from '@/Components/Settings/types';

export function LinkAccountSelect({ account, assets }: { account: BankAccountEntry; assets: LinkableAsset[] }) {
    const [processing, setProcessing] = useState(false);

    const link = (value: string) => {
        if (value.startsWith('__pending__')) return;
        setProcessing(true);
        router.post(
            `/banking/accounts/${account.id}/link`,
            { asset_id: value === '__none__' ? null : value },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    // The account is linked but this month's row doesn't exist yet (created on
    // the next sync), so there is no concrete asset id to select — show that as
    // the current value rather than collapsing to "Non collegato".
    const pendingThisMonth = account.linked_name !== null && account.linked_asset_id === null;
    const pendingValue = `__pending__${account.id}`;
    const currentValue = account.linked_asset_id
        ? String(account.linked_asset_id)
        : pendingThisMonth ? pendingValue : '__none__';

    return (
        <Select value={currentValue} onValueChange={link} disabled={processing}>
            <SelectTrigger className="h-8 w-full text-xs sm:w-48">
                <SelectValue placeholder="Collega a un asset" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="__none__">Non collegato</SelectItem>
                {pendingThisMonth && (
                    <SelectItem value={pendingValue} disabled>
                        {account.linked_name} (sync in attesa)
                    </SelectItem>
                )}
                {assets.length === 0 ? (
                    <div className="px-2 py-1.5 text-xs text-muted-foreground">
                        Nessun asset di liquidità questo mese — creane uno in Input.
                    </div>
                ) : (
                    assets.map((a) => (
                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                    ))
                )}
            </SelectContent>
        </Select>
    );
}
