"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import Image from "next/image";
import {
  LayoutDashboard, Users, GraduationCap, Calendar, Shield,
  ClipboardCheck, BookOpen, Bus, MessageSquare, BarChart3,
  ChevronRight, LogOut, FileCheck, Settings, Sparkles, X, Network, Wallet,
} from "lucide-react";
import { useDashboard } from "@/context/DashboardContext";

export default function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const { behaviorNotes, medicalExcuses, leavePermits, currentRole, currentUser, logoutDashboard, hasApiPermission, dashboardSummary, mobileMenuOpen, setMobileMenuOpen } = useDashboard();

  const pendingNotes = behaviorNotes.filter(n => n.statusLabel === "مفتوحة").length;
  const pendingExcuses = medicalExcuses.filter(e => e.status === "pending").length;
  const pendingPermits = leavePermits.filter(p => p.status === "waiting_gate").length;

  const navItems = [
    { label: "الرئيسية", href: "/", icon: LayoutDashboard, permissions: [] as string[] },
    { label: "مُنشئ هيكل المدرسة", href: "/configurator", icon: Network, special: true, permissions: ["settings.view"] },
    { label: "الطلبات والأعذار اليومية", href: "/operations", icon: FileCheck, badge: pendingExcuses + pendingPermits, badgeGreen: false, permissions: ["attendance.review_excuse", "operations.leave_review", "operations.summons_manage", "operations.substitution_manage"] },
    { label: "شؤون الطلاب وأولياء الأمور", href: "/students", icon: GraduationCap, permissions: ["people.view"] },
    { label: "شؤون المعلمين وتوزيع الحصص", href: "/teachers", icon: Users, permissions: ["people.view"] },
    { label: "الفصول والمواد الدراسية", href: "/academic", icon: BookOpen, permissions: ["academic.view"] },
    { label: "الجداول وحصص الانتظار", href: "/schedule", icon: Calendar, permissions: ["schedule.view"] },
    { label: "السلوك والمواظبة", href: "/behavior", icon: Shield, badge: pendingNotes, badgeGreen: false, permissions: ["behavior.view"] },
    { label: "الحضور والغياب", href: "/attendance", icon: ClipboardCheck, permissions: ["attendance.view"] },
    { label: "الدرجات والاختبارات", href: "/grades", icon: BarChart3, permissions: ["grade.view"] },
    { label: "المالية والفواتير", href: "/finance", icon: Wallet, permissions: ["finance.view"] },
    { label: "النقل المدرسي والحافلات", href: "/transport", icon: Bus, badge: dashboardSummary?.transport?.delayed ?? 0, badgeGreen: false, permissions: ["transport.view"] },
    { label: "الرسائل والتقويم المدرسي", href: "/messages", icon: MessageSquare, permissions: ["broadcasts.view", "schedule.view", "message.view"] },
    { label: "التحليلات والتقارير", href: "/analytics", icon: Sparkles, permissions: ["report.view"] },
    { label: "إعدادات النظام والصلاحيات", href: "/settings", icon: Settings, permissions: ["settings.view", "integrations.view", "rbac.view", "audit.view"] },
  ].filter((item) => item.permissions.length === 0 || item.permissions.some((permission) => hasApiPermission(permission)));

  const handleLogout = async () => {
    await logoutDashboard();
    router.replace("/login");
  };

  return (
    <>
      {/* Backdrop overlay for mobile */}
      {mobileMenuOpen && (
        <div
          onClick={() => setMobileMenuOpen(false)}
          style={{
            position: "fixed", top: 0, left: 0, right: 0, bottom: 0,
            background: "rgba(0,0,0,0.4)", backdropFilter: "blur(3px)",
            zIndex: 99, transition: "opacity 0.2s",
          }}
        />
      )}

      <aside className={`sidebar ${mobileMenuOpen ? "open" : ""}`}>
        {/* Logo */}
        <div className="sidebar-logo" style={{ justifyContent: "space-between" }}>
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <Image
              src="/logo_new.png"
              alt="EduBridge Logo"
              width={40}
              height={40}
              style={{ borderRadius: 10, objectFit: "contain", background: "var(--primary-50)", padding: 4 }}
            />
            <div className="sidebar-logo-text">
              <span>EduBridge</span>
              <span>لوحة الإدارة المدرسية</span>
            </div>
          </div>
          {/* Close button for mobile */}
          <button
            onClick={() => setMobileMenuOpen(false)}
            className="mobile-close-btn"
            style={{
              background: "transparent", border: "none", color: "var(--text-muted)",
              cursor: "pointer", padding: 4, display: "none",
            }}
          >
            <X size={20} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="sidebar-nav">
          <div className="nav-section-label">القوائم الإدارية والخدمات</div>
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href;
            const isSpecial = (item as { special?: boolean }).special;
            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setMobileMenuOpen(false)}
                className={`nav-item${isActive ? " active" : ""}`}
                style={isSpecial && !isActive ? {
                  background: "linear-gradient(135deg, #EFF6FF, #F5F3FF)",
                  border: "1px solid #BFDBFE",
                  borderRadius: "var(--radius)",
                  marginBottom: 4,
                } : undefined}
              >
                <Icon style={isSpecial ? { color: "#2563EB" } : undefined} />
                <span style={{ flex: 1, color: isSpecial && !isActive ? "#2563EB" : undefined, fontWeight: isSpecial ? 800 : undefined }}>{item.label}</span>
                {(item as { badge?: number }).badge && (item as { badge?: number }).badge! > 0 ? (
                  <span className={`nav-badge${(item as { badgeGreen?: boolean }).badgeGreen ? " green" : ""}`}>
                    {(item as { badge?: number }).badge}
                  </span>
                ) : null}
                {isActive && <ChevronRight size={13} style={{ color: "var(--primary)", opacity: 0.5 }} />}
              </Link>
            );
          })}
        </nav>

        {/* Footer — Dynamic Role Badge from RBAC Context */}
        <div className="sidebar-footer">
          <div className="sidebar-role-badge">
            <div className="role-avatar">{currentRole.label.charAt(0)}</div>
            <div className="role-info">
              <div className="role-name">{currentUser?.name || "مستخدم EduBridge"}</div>
              <div className="role-title" style={{ color: "var(--primary)", fontWeight: 700 }}>
                {currentRole.label}
              </div>
            </div>
          </div>
          <button
            onClick={() => void handleLogout()}
            className="btn btn-ghost btn-sm"
            style={{ width: "100%", justifyContent: "center", gap: 6 }}
          >
            <LogOut size={14} />
            تسجيل الخروج
          </button>
        </div>
      </aside>
    </>
  );
}
