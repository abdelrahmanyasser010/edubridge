"use client";

import React from "react";
import Link from "next/link";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import {
  GraduationCap, Users, ClipboardCheck, Shield,
  TrendingUp, TrendingDown, Minus, AlertTriangle,
  Bus, MessageSquare, Eye, ArrowLeft,
} from "lucide-react";

// ── Attendance bar chart helper ────────────────────────────────
function AttendanceBar({ label, absent, rate }: { label: string; absent: number; rate: number }) {
  const color = rate >= 95 ? "var(--green)" : rate >= 85 ? "var(--warning)" : "var(--danger)";
  return (
    <div style={{ marginBottom: 14 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 5, alignItems: "center" }}>
        <span style={{ fontSize: 12.5, fontWeight: 600, color: "var(--text-dark)" }}>{label}</span>
        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          {absent > 0 && (
            <span style={{ fontSize: 11, color: "var(--danger)", fontWeight: 600 }}>
              {absent} غائب
            </span>
          )}
          <span style={{ fontSize: 12, fontWeight: 700, color }}>
            {rate.toFixed(1)}%
          </span>
        </div>
      </div>
      <div className="progress-bar">
        <div
          className="progress-fill"
          style={{ width: `${rate}%`, background: color }}
        />
      </div>
    </div>
  );
}

// ── Severity badge helper ──────────────────────────────────────
function SeverityBadge({ label }: { label: string }) {
  const map: Record<string, string> = {
    "عالي": "badge-red",
    "متوسط": "badge-orange",
    "منخفض": "badge-green",
  };
  return <span className={`badge ${map[label] || "badge-gray"}`}><span className="dot" />{label}</span>;
}

function StatusBadge({ label }: { label: string }) {
  const map: Record<string, string> = {
    "مفتوحة": "badge-red",
    "قيد المعالجة": "badge-orange",
    "محلولة": "badge-green",
  };
  return <span className={`badge ${map[label] || "badge-gray"}`}>{label}</span>;
}

function BusStatusBadge({ status }: { status: string }) {
  const map: Record<string, { cls: string; label: string }> = {
    in_school: { cls: "badge-blue", label: "في المدرسة" },
    on_route: { cls: "badge-orange", label: "في الطريق" },
    arrived: { cls: "badge-green", label: "وصلت للمدرسة" },
  };
  const { cls, label } = map[status] || { cls: "badge-gray", label: status };
  return <span className={`badge ${cls}`}><span className="dot" />{label}</span>;
}

// ── Page ───────────────────────────────────────────────────────
export default function DashboardPage() {
  const { students, teachers, behaviorNotes, busRoutes, attendance, dashboardSummary, apiStatus } = useDashboard();

  const today = new Date().toLocaleDateString("ar-SA", {
    weekday: "long", year: "numeric", month: "long", day: "numeric",
  });

  const pendingNotesCount = behaviorNotes.filter(n => n.statusLabel === "مفتوحة").length;
  const activeTeachersCount = teachers.filter(t => t.activeStatus === "active").length;
  const apiStatusLabel =
    apiStatus === "live"
      ? "متصل ببيانات المدرسة"
      : apiStatus === "loading"
        ? "جاري المزامنة"
        : apiStatus === "error"
          ? "تعذر الاتصال بالخادم"
          : "غير متصل";
  const attendanceRate = dashboardSummary?.attendance_today?.rate ?? (attendance.total > 0 ? (attendance.present / attendance.total) * 100 : 0);

  const kpiCards = [
    {
      icon: <GraduationCap />,
      value: dashboardSummary?.students ?? students.length,
      label: "إجمالي الطلاب والشعب",
      change: "إجمالي الطلاب المقيدين",
      trend: "up" as const,
      iconBg: "#EFF6FF",
      iconColor: "#176B9A",
      href: "/students",
    },
    {
      icon: <Users />,
      value: dashboardSummary?.teachers ?? teachers.length,
      label: "إجمالي المعلمين والكوادر",
      change: `${activeTeachersCount} معلماً نشطاً`,
      trend: "neutral" as const,
      iconBg: "#F0FDF4",
      iconColor: "#7CC341",
      href: "/teachers",
    },
    {
      icon: <ClipboardCheck />,
      value: `${Math.round(attendanceRate)}%`,
      label: "نسبة الحضور العام اليوم",
      change: "نسبة حضور اليوم",
      trend: "down" as const,
      iconBg: "#FFF7ED",
      iconColor: "#F59E0B",
      href: "/attendance",
    },
    {
      icon: <Shield />,
      value: pendingNotesCount,
      label: "ملاحظات سلوكية بانتظار المراجعة",
      change: "ملاحظات قيد المعالجة",
      trend: "down" as const,
      iconBg: "#FEF2F2",
      iconColor: "#EF4444",
      href: "/behavior",
    },
  ];

  const recentNotes = behaviorNotes.slice(0, 5);
  const topTeachers = apiStatus === "live" ? teachers.slice(0, 5) : [...teachers].sort((a, b) => b.kpiScore - a.kpiScore).slice(0, 5);

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="لوحة القيادة والمؤشرات العامة لإدارة المدرسة"
          subtitle={`${today}`}
        />
        <main className="page-body">

          {/* ── Alert Banner ── */}
          {pendingNotesCount > 0 && (
            <div
              style={{
                background: "rgba(239,68,68,0.06)",
                border: "1px solid rgba(239,68,68,0.2)",
                borderRadius: "var(--radius)",
                padding: "10px 16px",
                display: "flex",
                alignItems: "center",
                gap: 10,
                marginBottom: 20,
              }}
            >
              <AlertTriangle size={16} color="var(--danger)" />
              <span style={{ fontSize: 13, fontWeight: 600, color: "var(--danger)" }}>
                تنبيه إداري: يوجد {pendingNotesCount} ملاحظات سلوكية مرصودة من المعلمين بانتظار مراجعة وكيل شؤون الطلاب واعتماد إرسالها لولي الأمر.
              </span>
              <Link href="/behavior" style={{ marginRight: "auto", fontSize: 12, color: "var(--danger)", fontWeight: 700, display: "flex", alignItems: "center", gap: 4 }}>
                مراجعة الملاحظات الآن <ArrowLeft size={13} />
              </Link>
            </div>
          )}

          {/* ── KPI Grid ── */}
          <div className="kpi-grid">
            {kpiCards.map((card, i) => (
              <Link href={card.href} className="kpi-card" key={i} style={{ textDecoration: "none", cursor: "pointer", transition: "transform 0.15s, box-shadow 0.15s" }}>
                <div className="kpi-icon" style={{ background: card.iconBg, color: card.iconColor }}>
                  {card.icon}
                </div>
                <div className="kpi-content">
                  <div className="kpi-value">{card.value}</div>
                  <div className="kpi-label">{card.label}</div>
                  <div className={`kpi-change ${card.trend}`}>
                    {card.trend === "up" && <TrendingUp />}
                    {card.trend === "down" && <TrendingDown />}
                    {card.trend === "neutral" && <Minus />}
                    {card.change}
                  </div>
                </div>
              </Link>
            ))}
          </div>

          {/* ── Main Grid: Behavior Notes + Attendance ── */}
          <div className="dashboard-grid" style={{ marginBottom: 16 }}>

            {/* Recent Behavior Notes */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">آخر الملاحظات السلوكية المرصودة من المعلمين</div>
                  <div className="card-subtitle">متابعة تطبيق لائحة السلوك والمواظبة اليومية</div>
                </div>
                <div className="live-badge">
                  <span className="live-dot" />
                  مباشر
                </div>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>اسم الطالب والصف</th>
                      <th>الملاحظة والتصنيف</th>
                      <th>مستوى الخطورة</th>
                      <th>حالة المتابعة</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {recentNotes.map((note) => (
                      <tr key={note.id}>
                        <td>
                          <div style={{ fontWeight: 700, fontSize: 13 }}>{note.studentName}</div>
                          <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{note.studentSection}</div>
                        </td>
                        <td>
                          <div style={{ fontWeight: 600, fontSize: 13 }}>{note.title}</div>
                          <div style={{ fontSize: 11, color: "var(--text-light)", maxWidth: 220, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{note.excerpt}</div>
                        </td>
                        <td><SeverityBadge label={note.severityLabel} /></td>
                        <td><StatusBadge label={note.statusLabel} /></td>
                        <td>
                          <Link href="/behavior" className="btn btn-ghost btn-sm">
                            <Eye size={13} />
                          </Link>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Attendance Breakdown */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">إحصائيات الحضور والغياب اليومي</div>
                  <div className="card-subtitle">مربوط آلياً مع تقارير منصة صحتي الطبية</div>
                </div>
                <span className="badge badge-blue">{attendance.present} / {attendance.total}</span>
              </div>
              <div className="card-body">
                {/* Big circle stat */}
                <div style={{ textAlign: "center", marginBottom: 20 }}>
                  <div style={{
                    width: 100, height: 100,
                    borderRadius: "50%",
                    background: `conic-gradient(var(--green) ${attendanceRate * 3.6}deg, var(--border-light) 0deg)`,
                    display: "flex", alignItems: "center", justifyContent: "center",
                    margin: "0 auto 8px",
                    boxShadow: "0 0 0 8px var(--green-50)",
                  }}>
                    <div style={{
                      width: 72, height: 72, borderRadius: "50%",
                      background: "white", display: "flex", flexDirection: "column",
                      alignItems: "center", justifyContent: "center",
                    }}>
                      <div style={{ fontSize: 18, fontWeight: 800, color: "var(--green)", lineHeight: 1.1 }}>{Math.round(attendanceRate)}%</div>
                      <div style={{ fontSize: 10, color: "var(--text-muted)" }}>حضور</div>
                    </div>
                  </div>
                  <div style={{ display: "flex", gap: 16, justifyContent: "center", fontSize: 12, fontWeight: 600 }}>
                    <span style={{ color: "var(--green)" }}>✓ {attendance.present} حاضر</span>
                    <span style={{ color: "var(--danger)" }}>✗ {attendance.absent} غائب</span>
                    <span style={{ color: "var(--warning)" }}>⏱ {attendance.late} متأخر</span>
                  </div>
                </div>
                {/* Per-section bars */}
                {attendance.sectionBreakdown.map((s) => (
                  <AttendanceBar key={s.sectionName} label={s.sectionName} absent={s.absent} rate={s.rate} />
                ))}
              </div>
            </div>
          </div>

          {/* ── Secondary Grid: Teachers + Bus ── */}
          <div className="grid-2">

            {/* Top Teachers by KPI */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">متابعة أداء المعلمين والأنصِبة الأسبوعية</div>
                  <div className="card-subtitle">التزام المعلمين برصد الغياب وإدخال الملاحظات والدرجات</div>
                </div>
                <Link href="/teachers" className="btn btn-outline btn-sm">عرض شؤون المعلمين</Link>
              </div>
              <div>
                {topTeachers.map((teacher, idx) => (
                  <div
                    key={teacher.id}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: 12,
                      padding: "12px 20px",
                      borderBottom: idx < topTeachers.length - 1 ? "1px solid var(--border-light)" : "none",
                    }}
                  >
                    <div
                      style={{
                        width: 36, height: 36, borderRadius: "50%",
                        background: teacher.avatarColor + "20",
                        color: teacher.avatarColor,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        fontSize: 13, fontWeight: 800, flexShrink: 0,
                      }}
                    >
                      {teacher.avatarInitials}
                    </div>
                    <div style={{ flex: 1 }}>
                      <div style={{ fontSize: 13, fontWeight: 700, color: "var(--text-dark)" }}>{teacher.name}</div>
                      <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{teacher.specialization}{apiStatus === "live" ? "" : ` — ${teacher.lessonsThisWeek} حصة هذا الأسبوع`}</div>
                    </div>
                    <div style={{ textAlign: "center" }}>
                      <div style={{ fontSize: 14, fontWeight: 800, color: teacher.kpiScore >= 95 ? "var(--green)" : teacher.kpiScore >= 85 ? "var(--warning)" : "var(--danger)" }}>{teacher.kpiScore}%</div>
                      <div style={{ fontSize: 10, color: "var(--text-muted)" }}>التزام الرصد</div>
                    </div>
                    <span className={`badge ${teacher.activeStatus === "active" ? "badge-green" : "badge-orange"}`}>
                      <span className="dot" />
                      {teacher.activeStatus === "active" ? "منتظم" : "إجازة"}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* Bus Fleet Status */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">أسطول النقل المدرسي والحافلات</div>
                  <div className="card-subtitle">متابعة تسكين الطلاب والمسارات النشطة</div>
                </div>
                <Link href="/transport" className="btn btn-outline btn-sm">إدارة النقل</Link>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {busRoutes.map((bus, idx) => (
                  <div
                    key={bus.id}
                    style={{
                      display: "flex",
                      alignItems: "center",
                      gap: 12,
                      padding: "14px 20px",
                      borderBottom: idx < busRoutes.length - 1 ? "1px solid var(--border-light)" : "none",
                    }}
                  >
                    <div
                      style={{
                        width: 40, height: 40, borderRadius: "var(--radius)",
                        background: bus.status === "on_route" ? "var(--warning-50)" : bus.status === "arrived" ? "var(--green-50)" : "var(--primary-50)",
                        display: "flex", alignItems: "center", justifyContent: "center",
                        flexShrink: 0,
                      }}
                    >
                      <Bus size={18} color={bus.status === "on_route" ? "var(--warning)" : bus.status === "arrived" ? "var(--green)" : "var(--primary)"} />
                    </div>
                    <div style={{ flex: 1 }}>
                      <div style={{ fontSize: 13, fontWeight: 700, color: "var(--text-dark)" }}>{bus.routeName}</div>
                      <div style={{ fontSize: 11, color: "var(--text-muted)" }}>
                        {bus.plateNumber} — السائق: {bus.driverName} — {bus.assignedStudentsCount} طالب
                      </div>
                      {bus.estimatedArrival && (
                        <div style={{ fontSize: 11, color: "var(--warning)", fontWeight: 600 }}>
                          الوصول المتوقع: {bus.estimatedArrival}
                        </div>
                      )}
                    </div>
                    <BusStatusBadge status={bus.status} />
                  </div>
                ))}
              </div>
              <div
                style={{
                  padding: "12px 20px",
                  borderTop: "1px solid var(--border-light)",
                  display: "flex",
                  justifyContent: "center",
                }}
              >
                <Link href="/transport" className="btn btn-outline btn-sm">
                  <Bus size={13} />
                  إدارة النقل وتتبع الحافلات
                </Link>
              </div>
            </div>
          </div>

        </main>
        <Footer />
      </div>
    </div>
  );
}
