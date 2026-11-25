import type { NextConfig } from "next";

const securityHeaders = [
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Content-Security-Policy",
    value: [
      "frame-ancestors 'none'",
      "frame-src 'self' https://js.stripe.com https://hooks.stripe.com",
      "script-src 'self' 'unsafe-inline' https://js.stripe.com",
      "connect-src 'self' https://api.stripe.com",
    ].join("; "),
  },
];

const nextConfig: NextConfig = {
  poweredByHeader: false,
  allowedDevOrigins: ["127.0.0.1"],
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
  async rewrites() {
    return [
      {
        source: "/api/auth/:path*",
        destination: `${process.env.AUTH_INTERNAL_URL ?? "http://127.0.0.1:8000"}/api/:path*`,
      },
      {
        source: "/api/:path*",
        destination: `${process.env.AUTH_INTERNAL_URL ?? "http://127.0.0.1:8000"}/api/:path*`,
      },
    ];
  },
};

export default nextConfig;
