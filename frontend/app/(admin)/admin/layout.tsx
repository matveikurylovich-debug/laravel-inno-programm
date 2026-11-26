import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import type { ReactNode } from "react";
import AdminShell from "@/app/(admin)/admin/AdminShell";
import { hasSessionAccessCookie } from "@/app/lib/proxy-guard";

export default async function AdminLayout({ children }: { children: ReactNode }) {
  const cookieStore = await cookies();
  const hasAccess = hasSessionAccessCookie((name) => cookieStore.get(name)?.value);

  if (!hasAccess) {
    redirect("/login?next=%2Fadmin%2Fusers");
  }

  return <AdminShell>{children}</AdminShell>;
}
