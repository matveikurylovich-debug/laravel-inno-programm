import test from "node:test";
import assert from "node:assert/strict";
import { ApiError, readError, requestJson } from "../app/lib/api.ts";

test("readError shows the validation reason instead of the status title", async () => {
  const response = new Response(
    JSON.stringify({
      type: "urn:problem:validation",
      title: "Unprocessable Entity",
      status: 422,
      detail: "Данные не прошли проверку.",
      errors: { email: ["Email уже занят."] },
    }),
    {
      status: 422,
      headers: { "Content-Type": "application/problem+json" },
    },
  );

  assert.equal(await readError(response), "Email уже занят.");
});

test("readError shows detail for forbidden and legacy envelopes", async () => {
  const forbidden = new Response(
    JSON.stringify({
      type: "about:blank",
      title: "Forbidden",
      status: 403,
      detail: "Нельзя открыть чужой заказ",
    }),
    { status: 403 },
  );
  assert.equal(await readError(forbidden), "Нельзя открыть чужой заказ");

  const legacy = new Response(
    JSON.stringify({
      error: "Unprocessable",
      details: "Один или несколько товаров недоступны в этом магазине",
    }),
    { status: 422 },
  );
  assert.equal(await readError(legacy), "Один или несколько товаров недоступны в этом магазине");
});

test("readError falls back cleanly on non-JSON 4xx and 5xx responses", async () => {
  const badGateway = new Response("<html>502 Bad Gateway</html>", { status: 502 });
  assert.equal(await readError(badGateway), "Сервис временно недоступен");

  const badRequest = new Response("", { status: 400 });
  assert.equal(await readError(badRequest), "Запрос не выполнен");
});

test("requestJson throws ApiError with status and parsed problem message", async () => {
  globalThis.window = { location: { pathname: "/catalog", href: "/catalog" } };
  globalThis.fetch = async () =>
    new Response(
      JSON.stringify({
        type: "about:blank",
        status: 409,
        detail: "Заказ уже отменён",
      }),
      { status: 409, headers: { "Content-Type": "application/problem+json" } },
    );

  await assert.rejects(
    () => requestJson("/api/orders/123/cancel", { method: "POST" }),
    (error) =>
      error instanceof ApiError && error.status === 409 && error.message === "Заказ уже отменён",
  );
});
