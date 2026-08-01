import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

type Props = {
  subscribed: boolean;
};

export default function BillingSuccess({ subscribed }: Props) {
  const [isSubscribed, setIsSubscribed] = useState(subscribed);

  useEffect(() => {
    if (isSubscribed) {
      return;
    }

    const interval = window.setInterval(async () => {
      const response = await fetch('/billing/status', {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        return;
      }

      const data = (await response.json()) as { subscribed: boolean };

      if (data.subscribed) {
        setIsSubscribed(true);
        window.clearInterval(interval);
      }
    }, 2000);

    return () => window.clearInterval(interval);
  }, [isSubscribed]);

  return (
    <>
      <Head title="Payment successful" />

      <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 px-4 py-16 text-center">
        {isSubscribed ? (
          <>
            <h1 className="text-2xl font-semibold tracking-tight">Payment successful</h1>
            <p className="text-sm text-muted-foreground">
              Your subscription is active. You can now access your dashboard.
            </p>
            <Button asChild>
              <a href={dashboard().url}>Go to dashboard</a>
            </Button>
          </>
        ) : (
          <>
            <Spinner className="size-6" />
            <h1 className="text-2xl font-semibold tracking-tight">Confirming your payment</h1>
            <p className="text-sm text-muted-foreground">
              Waiting for Stripe to confirm your subscription. This usually takes a few seconds.
            </p>
          </>
        )}
      </div>
    </>
  );
}
