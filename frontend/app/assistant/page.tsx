"use client";

import Link from "next/link";
import { FormEvent, ReactNode, useEffect, useRef, useState } from "react";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string };

type SuggestedProduct = {
  product_id: number;
  name: string;
  price: string | number;
  category?: string;
  similarity?: string | number;
};

type ChatTurn = {
  role: "user" | "assistant";
  text: string;
  products?: SuggestedProduct[];
};

function productHref(url: string): string {
  const match = url.match(/\/products\/(\d+)/);
  if (match) {
    return `/catalog/products/${match[1]}`;
  }

  return url.startsWith("/") ? url : `/${url}`;
}

function plainChunk(chunk: string): string {
  return chunk.replace(/(^|\s)\*(?=\s|$)/g, "$1").replace(/[ \t]{2,}/g, " ");
}

function ReplyText({ text }: { text: string }) {
  const pattern = /\*\*\[([^\]]+)\]\(([^)]+)\)\*\*|\[([^\]]+)\]\(([^)]+)\)|\*\*([^*]+)\*\*/g;
  const nodes: ReactNode[] = [];
  let last = 0;
  let key = 0;

  for (const match of text.matchAll(pattern)) {
    const index = match.index ?? 0;
    if (index > last) {
      nodes.push(plainChunk(text.slice(last, index)));
    }

    if (match[1] && match[2]) {
      nodes.push(
        <Link key={key} href={productHref(match[2])} className="font-medium underline">
          {match[1]}
        </Link>,
      );
    } else if (match[3] && match[4]) {
      nodes.push(
        <Link key={key} href={productHref(match[4])} className="font-medium underline">
          {match[3]}
        </Link>,
      );
    } else if (match[5]) {
      nodes.push(<strong key={key}>{match[5]}</strong>);
    }

    key += 1;
    last = index + match[0].length;
  }

  if (last < text.length) {
    nodes.push(plainChunk(text.slice(last)));
  }

  return <>{nodes}</>;
}

function money(value: string | number): string {
  const number = Number(value);
  if (Number.isNaN(number)) {
    return String(value);
  }

  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    minimumFractionDigits: 0,
  }).format(number);
}

export default function AssistantPage() {
  const [ready, setReady] = useState(false);
  const [stores, setStores] = useState<Store[]>([]);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [draft, setDraft] = useState("");
  const [turns, setTurns] = useState<ChatTurn[]>([]);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!localStorage.getItem("access_token")) {
      window.location.href = "/login";
      return;
    }

    setReady(true);
    apiFetch("/api/catalog/v1/stores")
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(await readError(response));
        }
        const body = (await response.json()) as { data?: Store[] } | Store[];
        const list = Array.isArray(body) ? body : (body.data ?? []);
        setStores(list);
        setStoreId(list[0]?.id ?? null);
      })
      .catch((reason: unknown) => {
        setError(reason instanceof Error ? reason.message : "Не удалось загрузить магазины");
      });
  }, []);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [turns, pending]);

  async function submit(event: FormEvent) {
    event.preventDefault();
    const message = draft.trim();
    if (!message || storeId === null || pending) {
      return;
    }

    setDraft("");
    setError(null);
    setTurns((current) => [...current, { role: "user", text: message }]);
    setPending(true);

    try {
      const response = await apiFetch("/api/recommendations/chat", {
        method: "POST",
        body: JSON.stringify({
          message,
          store_id: storeId,
          conversation_id: conversationId,
        }),
      });

      if (!response.ok) {
        throw new Error(await readError(response));
      }

      const body = (await response.json()) as {
        conversation_id: string;
        reply: string;
        products: SuggestedProduct[];
      };

      setConversationId(body.conversation_id);
      setTurns((current) => [
        ...current,
        { role: "assistant", text: body.reply, products: body.products ?? [] },
      ]);
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Не удалось получить рекомендацию");
    } finally {
      setPending(false);
    }
  }

  function resetChat() {
    setConversationId(null);
    setTurns([]);
    setError(null);
  }

  if (!ready) {
    return <main className="state-screen">Открываем консультанта...</main>;
  }

  return (
    <>
      <AppHeader />
      <main className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-3xl flex-col px-4 py-6 sm:px-6">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="eyebrow">Рекомендации</p>
            <h1 className="page-title">Консультант</h1>
            <p className="page-subtitle">Подберёт товары по описанию, а не по точному названию.</p>
          </div>
          <div className="flex items-center gap-2">
            <label className="text-sm text-slate-500" htmlFor="store">
              Магазин
            </label>
            <select
              id="store"
              className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
              value={storeId ?? ""}
              onChange={(event) => {
                setStoreId(Number(event.target.value));
                resetChat();
              }}
            >
              {stores.length === 0 ? <option value="">Нет магазинов</option> : null}
              {stores.map((store) => (
                <option key={store.id} value={store.id}>
                  {store.name}
                </option>
              ))}
            </select>
            <button type="button" className="btn btn-secondary btn-sm" onClick={resetChat}>
              Новый диалог
            </button>
          </div>
        </div>

        <section className="card flex min-h-[28rem] flex-1 flex-col overflow-hidden">
          <div className="flex-1 space-y-4 overflow-y-auto p-4 sm:p-5">
            {turns.length === 0 ? (
              <p className="text-sm text-slate-500">
                Например: «хочу побегать по заснеженному парку в мороз».
              </p>
            ) : null}

            {turns.map((turn, index) => (
              <article key={`${turn.role}-${index}`} className={turn.role === "user" ? "ml-8" : "mr-8"}>
                <p className={`text-xs font-medium ${turn.role === "user" ? "text-right text-brand-700" : "text-slate-500"}`}>
                  {turn.role === "user" ? "Вы" : "Консультант"}
                </p>
                <div
                  className={`mt-1 rounded-2xl px-4 py-3 text-sm leading-6 ${
                    turn.role === "user" ? "bg-brand-600 text-white" : "bg-slate-100 text-slate-800"
                  }`}
                >
                  {turn.role === "assistant" ? <ReplyText text={turn.text} /> : turn.text}
                </div>
                {turn.products && turn.products.length > 0 ? (
                  <ul className="mt-2 space-y-2">
                    {turn.products.map((product) => (
                      <li key={product.product_id} className="rounded-xl border border-slate-200 bg-white px-3 py-2">
                        <Link href={`/catalog/products/${product.product_id}`} className="link text-sm">
                          {product.name}
                        </Link>
                        <p className="mt-1 text-xs text-slate-500">
                          {product.category ? `${product.category} · ` : ""}
                          {money(product.price)}
                          {product.similarity !== undefined ? ` · сходство ${product.similarity}` : ""}
                        </p>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </article>
            ))}

            {pending ? <p className="text-sm text-slate-500">Подбираю товары...</p> : null}
            {error ? <p className="text-sm text-rose-600">{error}</p> : null}
            <div ref={bottomRef} />
          </div>

          <form onSubmit={submit} className="flex gap-2 border-t border-slate-200 p-3">
            <input
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              maxLength={1000}
              placeholder="Опишите, что ищете"
              className="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm"
              disabled={pending || storeId === null}
            />
            <button type="submit" className="btn btn-primary btn-sm" disabled={pending || storeId === null || draft.trim() === ""}>
              Отправить
            </button>
          </form>
        </section>
      </main>
    </>
  );
}
