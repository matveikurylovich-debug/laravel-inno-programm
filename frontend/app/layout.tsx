import type { Metadata } from "next";
import "./globals.css";
import SiteFooter from "@/app/components/SiteFooter";

export const metadata: Metadata = {
  title: "InnoTrainne",
  description: "Личный кабинет и панель управления уведомлениями",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ru" className="h-full antialiased">
      <body className="flex min-h-screen flex-col"><div className="flex-1">{children}</div><SiteFooter /></body>
    </html>
  );
}
