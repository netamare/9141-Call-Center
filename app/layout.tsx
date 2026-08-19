import type { Metadata } from "next";
import Script from "next/script";
import "./globals.css";
import "./enhancements.css";
import "./portal-fixes.css";
import "./login-theme.css";
export const metadata: Metadata = { title: "Call Center 9141", description: "Adama emergency response monitoring" };
export default function RootLayout({children}:{children:React.ReactNode}) { return <html lang="en" suppressHydrationWarning><body><Script id="portal-preferences" strategy="beforeInteractive">{`try{var theme=localStorage.getItem('cc9141-theme')||'light';var language=localStorage.getItem('cc9141-language')||'en';document.documentElement.dataset.theme=theme;document.documentElement.lang=language==='am'?'am':language==='om'?'om':'en';}catch(e){}`}</Script>{children}</body></html> }
