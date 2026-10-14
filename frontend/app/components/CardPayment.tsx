"use client";

import { FormEvent, useState } from "react";
import { loadStripe } from "@stripe/stripe-js";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";

const publishableKey = process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY ?? "";
const stripePromise = publishableKey ? loadStripe(publishableKey) : null;

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
    <form onSubmit={submit} className="mt-4 space-y-4">
      <PaymentElement />
      {error ? <p className="alert alert-error">{error}</p> : null}
      <button type="submit" className="btn btn-primary" disabled={!stripe || pending}>
        {pending ? "Проводим оплату..." : "Оплатить"}
      </button>
    </form>
  );
}

export default function CardPayment({
  orderId,
  clientSecret,
  onPaid,
}: {
  orderId: string;
  clientSecret: string;
  onPaid: () => void;
}) {
  if (!stripePromise) {
    return <p className="mt-4 text-sm text-slate-500">Не задан NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY.</p>;
  }

  return (
    <Elements stripe={stripePromise} options={{ clientSecret }}>
      <PaymentForm orderId={orderId} onPaid={onPaid} />
    </Elements>
  );
}
