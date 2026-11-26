import test from "node:test";
import assert from "node:assert/strict";
import {
  applyPhoneChange,
  formatBelarusPhone,
  phoneFromProfile,
  phoneToSubmit,
} from "../app/lib/phone.ts";

test("loaded profile phone is not reformatted", () => {
  assert.equal(phoneFromProfile("+375291112233"), "+375291112233");
  assert.equal(phoneFromProfile("+48123456789"), "+48123456789");
  assert.equal(phoneFromProfile("+79001234567"), "+79001234567");
  assert.equal(phoneFromProfile(null), "");
  assert.equal(phoneFromProfile(undefined), "");
});

test("unchanged stored phone is submitted as-is without corruption", () => {
  for (const stored of ["+375291112233", "+48123456789", "+79001234567"]) {
    assert.equal(phoneToSubmit(stored, phoneFromProfile(stored), false), stored);
  }
  assert.equal(phoneToSubmit("+375291112233", "+375 (29) 999-88-77", true), "+375 (29) 999-88-77");
});

test("blur formats belarus numbers, preserves leading plus, and does not corrupt foreign numbers", () => {
  assert.equal(formatBelarusPhone(""), "");
  assert.equal(formatBelarusPhone("   "), "");
  assert.equal(formatBelarusPhone("+"), "+");
  assert.equal(formatBelarusPhone("+4"), "+4");
  assert.equal(formatBelarusPhone("+375291234567"), "+375 (29) 123-45-67");
  assert.equal(formatBelarusPhone("+375 29 123 45 67"), "+375 (29) 123-45-67");
  assert.equal(formatBelarusPhone("80291234567"), "+375 (29) 123-45-67");
  assert.equal(formatBelarusPhone("291234567"), "+375 (29) 123-45-67");
  assert.equal(formatBelarusPhone("+48123456789"), "+48123456789");
  assert.equal(formatBelarusPhone("+48 123 456 789"), "+48123456789");
  assert.equal(formatBelarusPhone("+37061234567"), "+37061234567");
  assert.equal(formatBelarusPhone("+79001234567"), "+79001234567");
  assert.equal(formatBelarusPhone("+7900"), "+7900");
});

test("editing allows clearing, keeps leading plus, and backspace after ) deletes bracket and digit cleanly", () => {
  assert.equal(applyPhoneChange("+375 (29) 123-45-67", ""), "");
  assert.equal(applyPhoneChange("+375 (29)", ""), "");

  const afterBracketBackspace = applyPhoneChange("+375 (29)", "+375 (29");
  assert.equal(afterBracketBackspace, "+375 (29");
  assert.notEqual(afterBracketBackspace, formatBelarusPhone("+375 (29"));

  const afterDigitBackspace = applyPhoneChange(afterBracketBackspace, "+375 (2");
  assert.equal(afterDigitBackspace, "+375 (2");
  assert.equal(formatBelarusPhone(afterDigitBackspace), "+375 (2");

  assert.equal(applyPhoneChange("+375 (29) 123-45-67", "+375 (29 123-45-67"), "+375 (29 123-45-67");

  assert.equal(applyPhoneChange("", "+"), "+");
  assert.equal(applyPhoneChange("+", "+4"), "+4");
  assert.equal(applyPhoneChange("+4", "+48"), "+48");
  assert.equal(applyPhoneChange("+375 (29) ", "+"), "+");
});
