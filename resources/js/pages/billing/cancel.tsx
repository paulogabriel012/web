import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { plan } from '@/routes/billing';

export default function BillingCancel() {
  return (
    <>
      <Head title="Checkout cancelled" />

      <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 px-4 py-16 text-center">
        <h1 className="text-2xl font-semibold tracking-tight">Checkout cancelled</h1>
        <p className="text-sm text-muted-foreground">
          You haven&apos;t been charged. You can pick another plan whenever you&apos;re ready.
        </p>
        <Button asChild variant="outline">
          <a href={plan().url}>Back to plans</a>
        </Button>
      </div>
    </>
  );
}
