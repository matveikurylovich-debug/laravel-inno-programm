"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import { listStores } from "@/app/lib/api/catalog";
import { postRecommendationChat, type SuggestedProduct } from "@/app/lib/api/recommendations";
import { productHref } from "@/app/lib/assistant.mjs";
import { formatMoney } from "@/app/lib/format";
import { useAsyncQuery } from "@/app/lib/use-async-query";
import { SessionUnavailableScreen, useSession } from "@/app/providers/SessionProvider";

type ChatTurn = {
  role: "user" | "assistant";
  text: string;
  products?: SuggestedProduct[];
};

export default function AssistantPage() {
  const router = useRouter();
  const { user, status, error: sessionError, refresh } = useSession();
  const [selectedStoreId, setSelectedStoreId] = useState<number | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [draft, setDraft] = useState("");
  const [turns, setTurns] = useState<ChatTurn[]>([]);
  const [pending, setPending] = useState(false);
  const [chatError, setChatError] = useState<string | null>(null);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (status === "guest") {
      router.replace("/login?next=%2Fassistant");
    }
  }, [router, status]);

  const storesQuery = useAsyncQuery((signal) => listStores(signal), "assistant:stores", {
    enabled: status === "authenticated",
    fallbackError: "Не удалось загрузить магазины",
  });

  const stores = storesQuery.data ?? [];
  const storeId = selectedStoreId ?? stores[0]?.id ?? null;

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
    setChatError(null);
    setTurns((current) => [...current, { role: "user", text: message }]);
    setPending(true);

    try {
      const body = await postRecommendationChat({
        message,
        store_id: storeId,
        conversation_id: conversationId,
      });

      setConversationId(body.conversation_id);
      setTurns((current) => [
        ...current,
        { role: "assistant", text: body.reply, products: body.products ?? [] },
      ]);
    } catch (reason: unknown) {
      setChatError(reason instanceof Error ? reason.message : "Не удалось получить рекомендацию");
    } finally {
      setPending(false);
    }
  }

  function resetChat() {
    setConversationId(null);
    setTurns([]);
    setChatError(null);
  }

  if (status === "unavailable") {
    return (
      <SessionUnavailableScreen
        message={sessionError}
        onRetry={() => {
          void refresh();
        }}
      />
    );
  }

  if (status !== "authenticated" || !user) {
    return <main className="state-screen">Открываем консультанта...</main>;
  }

  const activeError = chatError ?? (storesQuery.error || null);

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
                setSelectedStoreId(Number(event.target.value));
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
              <article
                key={`${turn.role}-${index}`}
                className={turn.role === "user" ? "ml-8" : "mr-8"}
              >
                <p
                  className={`text-xs font-medium ${
                    turn.role === "user" ? "text-right text-brand-700" : "text-slate-500"
                  }`}
                >
                  {turn.role === "user" ? "Вы" : "Консультант"}
                </p>
                <div
                  className={`mt-1 rounded-2xl px-4 py-3 text-sm leading-6 ${
                    turn.role === "user" ? "bg-brand-600 text-white" : "bg-slate-100 text-slate-800"
                  }`}
                >
                  <span className={turn.role === "assistant" ? "whitespace-pre-line" : undefined}>
                    {turn.text}
                  </span>
                </div>
                {turn.products && turn.products.length > 0 ? (
                  <ul className="mt-2 space-y-2">
                    {turn.products.map((product) => (
                      <li
                        key={product.product_id}
                        className="rounded-xl border border-slate-200 bg-white px-3 py-2"
                      >
                        <Link
                          href={productHref(product.product_id) ?? "#"}
                          className="link text-sm"
                        >
                          {product.name}
                        </Link>
                        <p className="mt-1 text-xs text-slate-500">
                          {product.category ? `${product.category} · ` : ""}
                          {formatMoney(product.price, product.currency ?? "USD")}
                          {product.similarity !== undefined
                            ? ` · сходство ${product.similarity}`
                            : ""}
                        </p>
                      </li>
                    ))}
                  </ul>
                ) : null}
              </article>
            ))}

            {pending ? <p className="text-sm text-slate-500">Подбираю товары...</p> : null}
            {activeError ? (
              <p role="alert" className="alert alert-error">
                {activeError}
              </p>
            ) : null}
            <div ref={bottomRef} />
          </div>

          <form
            onSubmit={(event) => {
              void submit(event);
            }}
            className="flex gap-2 border-t border-slate-200 p-3"
          >
            <input
              aria-label="Сообщение консультанту"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              maxLength={400}
              placeholder="Опишите, что ищете"
              className="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm"
              disabled={pending || storeId === null}
            />
            <button
              type="submit"
              className="btn btn-primary btn-sm"
              disabled={pending || storeId === null || draft.trim() === ""}
            >
              Отправить
            </button>
          </form>
        </section>
      </main>
    </>
  );
}
