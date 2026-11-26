"use client";

import { useState, type FormEvent } from "react";
import { loadStripe, type Stripe } from "@stripe/stripe-js";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";

const stripeByKey = new Map<string, Promise<Stripe | null>>();

function stripeFor(publishableKey: string): Promise<Stripe | null> {
  const cached = stripeByKey.get(publishableKey);
  if (cached) {
    return cached;
  }
  const promise = loadStripe(publishableKey);
  stripeByKey.set(publishableKey, promise);
  return promise;
}

function PaymentForm({ orderId, onPaid }: { orderId: string; onPaid: () => void }) {
  const stripe = useStripe();
  const elements = useElements();
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!stripe || !elements) {
      return;
    }

    setPending(true);
    setError("");

    const result = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: `${window.location.origin}/orders/${orderId}`,
      },
      redirect: "if_required",
    });

    setPending(false);

    if (result.error) {
      setError(result.error.message ?? "Оплата не прошла");
      return;
    }

    onPaid();
  }

  return (
    <form
      onSubmit={(event) => {
        void submit(event);
      }}
      className="mt-4 space-y-4"
    >
      <PaymentElement />
      {error ? (
        <p role="alert" className="alert alert-error">
          {error}
        </p>
      ) : null}
      <button type="submit" className="btn btn-primary" disabled={!stripe || pending}>
        {pending ? "Проводим оплату..." : "Оплатить"}
      </button>
    </form>
  );
}

export default function CardPayment({
  orderId,
  clientSecret,
  publishableKey,
  onPaid,
}: {
  orderId: string;
  clientSecret: string;
  publishableKey: string;
  onPaid: () => void;
}) {
  if (!publishableKey) {
    return (
      <p role="alert" className="mt-4 text-sm text-slate-500">
        Платёжная форма временно недоступна.
      </p>
    );
  }

  return (
    <Elements stripe={stripeFor(publishableKey)} options={{ clientSecret }}>
      <PaymentForm orderId={orderId} onPaid={onPaid} />
    </Elements>
  );
}
