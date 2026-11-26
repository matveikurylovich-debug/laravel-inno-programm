import http from "k6/http";
import { check, sleep } from "k6";

export const options = {
  stages: [
    { duration: "1m", target: 100 },
    { duration: "3m", target: 100 },
    { duration: "30s", target: 0 },
  ],
  thresholds: {
    http_req_failed: ["rate<0.01"],
    http_req_duration: ["p(95)<150", "p(99)<300"],
  },
};

const queries = ["phone", "tablet", "iphone", "cable", ""];
const categories = ["", "1", "2", "5"];

export default function () {
  const q = queries[Math.floor(Math.random() * queries.length)];
  const category = categories[Math.floor(Math.random() * categories.length)];
  const priceMin = Math.floor(Math.random() * 500);
  const params = [
    `q=${encodeURIComponent(q)}`,
    category ? `category_id=${category}` : "",
    `price_min=${priceMin}`,
    `price_max=${priceMin + 1000}`,
    `in_stock=1`,
    "page=1",
    "per_page=20",
  ].filter(Boolean).join("&");

  const response = http.get(`${__ENV.SEARCH_URL || "https://localhost:8446"}/api/search?${params}`, {
    tls_skip_verify: true,
  });

  check(response, {
    "status is 200": (result) => result.status === 200,
    "has facets": (result) => result.json("facets") !== undefined,
  });

  sleep(1);
}
