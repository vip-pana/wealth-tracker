import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-react';
import type { Category } from '@/types/models';

export function DeleteCategoryButton({ category }: { category: Category & { assets_count: number } }) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (category.assets_count > 0) {
            alert(`Non puoi eliminare "${category.name}": ha ${category.assets_count} asset associati.`);
            return;
        }
        if (confirm(`Eliminare la categoria "${category.name}"?`)) {
            destroy(`/categories/${category.id}`);
        }
    };

    return (
        <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
            onClick={handleDelete}
            disabled={processing || category.assets_count > 0}
            title={category.assets_count > 0 ? 'Categoria in uso' : 'Elimina'}
        >
            <Trash2 className="w-4 h-4" />
        </Button>
    );
}
