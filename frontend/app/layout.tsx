import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "InnoTrainne",
  description: "Личный кабинет и панель управления уведомлениями",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ru" className="h-full antialiased">
      <body className="min-h-full">{children}</body>
    </html>
  );
}
