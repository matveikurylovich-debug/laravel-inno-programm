import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  allowedDevOrigins: ["127.0.0.1"],
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
