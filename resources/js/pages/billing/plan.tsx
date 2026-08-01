import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { checkout } from '@/routes/billing';

type Plan = {
  id: string;
  name: string;
  description: string;
  amount: number;
  currency: string;
  features: string[];
};

type Props = {
  plans: Plan[];
  subscribed: boolean;
};

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency.toUpperCase(),
    minimumFractionDigits: 2,
  }).format(amount / 100);
}

export default function PlanPage({ plans, subscribed }: Props) {
  const [selected, setSelected] = useState<string>(plans[0]?.id ?? '');

  if (subscribed) {
    return (
      <>
        <Head title="Billing" />
        <div className="mx-auto flex max-w-2xl flex-col items-center gap-6 px-4 py-16 text-center">
          <h1 className="text-2xl font-semibold tracking-tight">You&apos;re all set</h1>
          <p className="text-sm text-muted-foreground">
            Your subscription is active. Manage your plan, invoices and payment method from the
            billing portal.
          </p>
          <div className="flex gap-3">
            <Button asChild>
              <a href="/billing/portal">Billing portal</a>
            </Button>
            <Button asChild variant="outline">
              <a href={dashboard().url}>Go to dashboard</a>
            </Button>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <Head title="Choose a plan" />

      <div className="mx-auto w-full max-w-5xl px-4 py-12">
        <div className="mb-10 text-center">
          <h1 className="text-2xl font-semibold tracking-tight">Choose your plan</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            Pick the plan that fits your operation. You can upgrade or downgrade at any time.
          </p>
        </div>

        <Form {...checkout.form()} disableWhileProcessing className="grid gap-6 md:grid-cols-3">
          {({ processing, errors }) => (
            <>
              <input type="hidden" name="plan" value={selected} />

              {plans.map((plan) => (
                <Card
                  key={plan.id}
                  data-selected={selected === plan.id}
                  className="cursor-pointer transition-shadow data-[selected=true]:border-primary data-[selected=true]:ring-2 data-[selected=true]:ring-primary/40"
                  onClick={() => setSelected(plan.id)}
                >
                  <CardHeader>
                    <CardTitle>{plan.name}</CardTitle>
                    <CardDescription>{plan.description}</CardDescription>
                  </CardHeader>

                  <CardContent className="flex flex-col gap-6">
                    <div className="flex items-baseline gap-1">
                      <span className="text-3xl font-semibold tracking-tight">
                        {formatAmount(plan.amount, plan.currency)}
                      </span>
                      <span className="text-sm text-muted-foreground">/month</span>
                    </div>

                    <ul className="flex flex-col gap-2 text-sm">
                      {plan.features.map((feature) => (
                        <li key={feature} className="flex items-center gap-2 text-muted-foreground">
                          <span className="text-primary">✓</span>
                          {feature}
                        </li>
                      ))}
                    </ul>
                  </CardContent>

                  <CardFooter>
                    <Button
                      type="button"
                      variant={selected === plan.id ? 'default' : 'outline'}
                      className="w-full"
                      onClick={(event) => {
                        event.stopPropagation();
                        setSelected(plan.id);
                      }}
                    >
                      {selected === plan.id ? 'Selected' : 'Select'}
                    </Button>
                  </CardFooter>
                </Card>
              ))}

              {errors.plan && <p className="text-sm text-destructive">{errors.plan}</p>}

              <div className="md:col-span-3">
                <Button type="submit" className="w-full" size="lg" data-test="checkout-button">
                  {processing && <Spinner />}
                  Continue to payment
                </Button>
              </div>
            </>
          )}
        </Form>
      </div>
    </>
  );
}
