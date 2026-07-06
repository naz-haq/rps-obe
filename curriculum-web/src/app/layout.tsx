import type { Metadata } from "next";
import { Inter } from "next/font/google";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import "./globals.css";
import { Shell } from "@/components/shell";
import { getCurrentUser } from "@/lib/auth";

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "Curriculum Service · OBE RPS Generator",
  description: "Peta kurikulum, generator RPS OBE berbantuan AI, dan pengelolaan capaian pembelajaran.",
};

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const pathname = (await headers()).get("x-pathname") ?? "";
  const isLogin = pathname === "/login";
  const user = await getCurrentUser();

  // Token ada tapi tidak valid (user null) di halaman terproteksi → paksa login.
  if (!user && !isLogin) {
    redirect("/login");
  }

  return (
    <html lang="id" className={`${inter.variable} h-full`}>
      <body className="min-h-full">
        {user ? <Shell user={user}>{children}</Shell> : children}
      </body>
    </html>
  );
}

