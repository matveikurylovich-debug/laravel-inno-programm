import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  allowedDevOrigins: ["127.0.0.1"],
  async rewrites() {
    return [
      {
        source: "/api/auth/:path*",
        destination: "http://127.0.0.1:8000/api/:path*",
      },
      {
        source: "/api/notifications/:path*",
        destination: "http://127.0.0.1:8001/api/notifications/:path*",
      },
      {
        source: "/api/catalog/:path*",
        destination: "http://127.0.0.1:8084/api/:path*",
      },
    ];
  },
};

export default nextConfig;
