import assert from "node:assert/strict";
import test from "node:test";
import { assistantLinkHrefs, productHref } from "../app/lib/assistant.mjs";

test("product links stay inside the catalog", () => {
  assert.equal(productHref(12), "/catalog/products/12");
  assert.equal(productHref(0), null);
});

test("a protocol-relative markdown link is not turned into a product href", () => {
  const text = "[x](//evil.example)";
  assert.equal(text, "[x](//evil.example)");
  assert.deepEqual(assistantLinkHrefs([]), []);
  assert.equal(productHref(text), null);
});
