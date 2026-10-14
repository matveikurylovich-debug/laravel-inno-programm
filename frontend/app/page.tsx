"use client";

import { useEffect } from "react";

export default function HomePage() {
  useEffect(() => {
    const token = localStorage.getItem("access_token");
    const role = localStorage.getItem("user_role");

    if (!token) {
      window.location.href = "/login";
      return;
    }

    if (role === "admin" || role === "analyst") {
      window.location.href = "/admin/products";
      return;
    }

    window.location.href = "/catalog";
  }, []);

  return <main className="state-screen">Открываем магазин...</main>;
}
