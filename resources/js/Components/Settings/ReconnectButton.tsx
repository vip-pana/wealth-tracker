import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { RotateCcw } from 'lucide-react';
import type { BankConnectionEntry } from '@/Components/Settings/types';

export function ReconnectButton({ connection }: { connection: BankConnectionEntry }) {
    const { post, processing } = useForm({ aspsp_name: connection.aspsp_name, aspsp_country: connection.aspsp_country });

    return (
        <Button
            variant="outline"
            size="sm"
            className="h-7 text-xs"
            onClick={() => post('/banking/connect')}
            disabled={processing}
        >
            <RotateCcw className="w-3.5 h-3.5 mr-1" />
            Riconnetti
        </Button>
    );
}
