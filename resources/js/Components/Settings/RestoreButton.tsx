import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { RotateCcw } from 'lucide-react';

export function RestoreButton({ url }: { url: string }) {
    const { post, processing } = useForm({});

    return (
        <Button
            variant="outline"
            size="sm"
            className="h-8 flex-shrink-0"
            onClick={() => post(url, { preserveScroll: true })}
            disabled={processing}
        >
            <RotateCcw className="w-3.5 h-3.5 mr-1" />
            Ripristina
        </Button>
    );
}
