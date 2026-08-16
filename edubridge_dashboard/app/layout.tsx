import type { Metadata } from "next";
import "./globals.css";
import { DashboardProvider } from "@/context/DashboardContext";
import ToastContainer from "@/components/ToastContainer";
import DashboardAuthGate from "@/components/DashboardAuthGate";

export const metadata: Metadata = {
  title: {
    template: "%s | EduBridge Pro",
    default: "EduBridge — لوحة تحكم الإدارة المدرسية والربط العائلي الذكي",
  },
  description: "نظام إدارة مدرسي شامل ومتطور يربط الإدارة المدرسية بالمعلمين وأولياء الأمور متوافق مع معايير نور ومدرستي.",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ar" dir="rtl">
      <body>
        <DashboardProvider>
          <DashboardAuthGate>{children}</DashboardAuthGate>
          <ToastContainer />
        </DashboardProvider>
      </body>
    </html>
  );
}
