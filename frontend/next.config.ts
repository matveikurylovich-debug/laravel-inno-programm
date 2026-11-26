import type { NextConfig } from "next";

const contentSecurityPolicy = [
  "frame-ancestors 'none'",
  "frame-src 'self' https://js.stripe.com https://hooks.stripe.com",
  "script-src 'self' 'unsafe-inline' https://js.stripe.com",
  "connect-src 'self' https://api.stripe.com",
].join("; ");

const nextConfig: NextConfig = {
  output: "standalone",
  images: {
    unoptimized: true,
  },
  poweredByHeader: false,
  allowedDevOrigins: ["127.0.0.1"],
  headers() {
    return Promise.resolve([
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Content-Security-Policy", value: contentSecurityPolicy },
        ],
      },
    ]);
  },
  rewrites() {
    const authInternalUrl = process.env.AUTH_INTERNAL_URL ?? "http://127.0.0.1:8000";
    return Promise.resolve([
      {
        source: "/api/auth/:path*",
        destination: `${authInternalUrl}/api/v1/:path*`,
      },
      {
        source: "/api/:path*",
        destination: `${authInternalUrl}/api/:path*`,
      },
    ]);
  },
};

export default nextConfig;
