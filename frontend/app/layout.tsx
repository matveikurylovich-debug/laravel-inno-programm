import type { Metadata } from "next";
import SiteFooter from "@/app/components/SiteFooter";
import { SessionProvider } from "@/app/providers/SessionProvider";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "InnoTrainne — интернет-магазин",
    template: "%s · InnoTrainne",
  },
  description:
    "Интернет-магазин InnoTrainne: каталог товаров, умный консультант, заказы и панель управления.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ru" className="h-full antialiased">
      <body className="min-h-full">
        <SessionProvider>
          <div className="flex min-h-screen flex-col">
            <div className="flex flex-1 flex-col">{children}</div>
            <SiteFooter />
          </div>
        </SessionProvider>
      </body>
    </html>
  );
}
