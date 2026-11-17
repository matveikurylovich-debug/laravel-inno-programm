"use client";

import { currentUser } from "@/app/lib/auth";
import { useEffect } from "react";

export default function HomePage() {
  useEffect(() => {
    currentUser().then((user) => {
      window.location.href = !user ? "/login" : ["admin", "analyst"].includes(user.role) ? "/admin/users" : "/catalog";
    }).catch(() => undefined);
  }, []);

  return <main className="state-screen">Открываем магазин...</main>;
}
