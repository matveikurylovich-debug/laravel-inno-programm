import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async rewrites() {
    return [
      // Запросы авторизации перенаправляются в auth-service
      {
        source: "/api/auth/:path*",
        destination: "http://localhost:8000/api/:path*",
      },
      // Запросы уведомлений перенаправляются в notification-service
      {
        source: "/api/notifications/:path*",
        destination: "http://localhost:8001/api/notifications/:path*",
      },
    ];
  },
};

export default nextConfig;