"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { CheckCircle, AlertTriangle, Clock, Calendar, FileCheck, RefreshCw } from "lucide-react";

export default function AttendancePage() {
  const router = useRouter();
  const {
    students,
    sections,
    attendance,
    sendParentWarning,
    medicalExcuses,
    dailyAttendance,
    attendanceAtRisk,
    fetchDailyAttendanceByDateAndSection,
  } = useDashboard();

  const [selectedSection, setSelectedSection] = useState<string>("");
  const [selectedDate, setSelectedDate] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [isLoadingSection, setIsLoadingSection] = useState(false);

  // Set initial selected section
  useEffect(() => {
    if (!selectedSection && sections.length > 0) {
      setSelectedSection(sections[0].id);
    }
  }, [sections, selectedSection]);

  // Load section daily attendance when section or date changes
  useEffect(() => {
    if (selectedSection) {
      setIsLoadingSection(true);
      void fetchDailyAttendanceByDateAndSection(selectedDate, selectedSection)
        .finally(() => setIsLoadingSection(false));
    }
  }, [selectedSection, selectedDate, fetchDailyAttendanceByDateAndSection]);

  const pendingExcuses = medicalExcuses.filter(e => e.status === "pending").length;

  const handleSectionChange = (secId: string) => {
    setSelectedSection(secId);
  };

  const handleRefresh = () => {
    if (selectedSection) {
      setIsLoadingSection(true);
      void fetchDailyAttendanceByDateAndSection(selectedDate, selectedSection)
        .finally(() => setIsLoadingSection(false));
    }
  };

  // Status mapping helper
  const getStudentStatusBadge = (summaryStatus: string, absent: number, late: number, present: number, recorded: number, expected: number) => {
    switch (summaryStatus) {
      case "full_day_absence":
      case "has_absence":
        return <span className="badge badge-red"><span className="dot" />غائب ({absent} حصص)</span>;
      case "late":
        return <span className="badge badge-orange"><span className="dot" />متأخر ({late} حصص)</span>;
      case "excused":
        return <span className="badge badge-blue"><span className="dot" />عذر طبي معتمد</span>;
      case "complete":
        return <span className="badge badge-green"><span className="dot" />حاضر ({present} حصص)</span>;
      default:
        return <span className="badge badge-gray">قيد الرصد ({recorded}/{expected})</span>;
    }
  };

  const atRiskList = attendanceAtRisk?.students ?? [];
  const dailyStudents = dailyAttendance?.students ?? [];

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="الحضور والغياب" subtitle="رصد غياب الطلاب اليومي ومتابعة الإنذار المبكر للأعذار والغياب" />
        <main className="page-body">

          {/* Quick stats */}
          <div className="kpi-grid" style={{ marginBottom: 20 }}>
            {[
              { label: "إجمالي الحضور اليوم", value: `${attendance.present} طالب`, icon: <CheckCircle size={22} />, bg: "#F0FDF4", color: "#16A34A" },
              { label: "الغياب المرصود اليوم", value: `${attendance.absent} طالب`, icon: <AlertTriangle size={22} />, bg: "#FEF2F2", color: "#EF4444" },
              { label: "حالات التأخر الصباحي", value: `${attendance.late} طالب`, icon: <Clock size={22} />, bg: "#FFF7ED", color: "#F59E0B" },
              { label: "أعذار طبية بانتظار المراجعة", value: `${pendingExcuses} تقارير`, icon: <FileCheck size={22} />, bg: "#EFF6FF", color: "#176B9A", link: "/operations" },
            ].map((s, i) => (
              <div className="kpi-card" key={i} onClick={() => s.link && router.push(s.link)} style={{ cursor: s.link ? "pointer" : "default" }}>
                <div className="kpi-icon" style={{ background: s.bg, color: s.color }}>{s.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 20 }}>{s.value}</div>
                  <div className="kpi-label">{s.label}</div>
                </div>
              </div>
            ))}
          </div>

          <div className="grid-2">
            {/* Absentee students needing intervention */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">قائمة الإنذار المبكر (حرمان من دخول الاختبارات)</div>
                  <div className="card-subtitle">الطلاب الذين تجاوزت نسبة غيابهم الحد المسموح به دون أعذار رسمية</div>
                </div>
                <span className={`badge ${atRiskList.length > 0 ? "badge-red" : "badge-green"}`}>
                  {atRiskList.length} طلاب بحاجة لتنبيه
                </span>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الطالب والشعبة</th>
                      <th>نسبة الحضور</th>
                      <th>حصص الغياب</th>
                      <th>إجراء</th>
                    </tr>
                  </thead>
                  <tbody>
                    {atRiskList.length === 0 ? (
                      <tr>
                        <td colSpan={4} style={{ textAlign: "center", padding: 30, color: "var(--text-muted)" }}>
                          ✓ ممتاز! لا يوجد طلاب تجاوزوا الحد الأقصى للغياب في هذا الفصل الدراسي.
                        </td>
                      </tr>
                    ) : (
                      atRiskList.map((item) => (
                        <tr key={item.student.id}>
                          <td>
                            <div style={{ fontWeight: 700 }}>{item.student.full_name}</div>
                            <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{item.section?.name ?? "—"}</div>
                          </td>
                          <td>
                            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                              <div className="progress-bar" style={{ width: 60 }}>
                                <div
                                  className="progress-fill"
                                  style={{
                                    width: `${item.attendance_percentage ?? 0}%`,
                                    background: (item.attendance_percentage ?? 0) < 80 ? "var(--danger)" : "var(--warning)",
                                  }}
                                />
                              </div>
                              <span style={{ fontWeight: 800, color: "var(--danger)" }}>
                                {item.attendance_percentage !== null ? `${item.attendance_percentage}%` : "—"}
                              </span>
                            </div>
                          </td>
                          <td>
                            <span className="badge badge-red">
                              <span className="dot" />
                              {item.unexcused_absent_periods} حصص بدون عذر
                            </span>
                          </td>
                          <td>
                            <button
                              onClick={() => sendParentWarning(item.student.id, "تجاوز الحد الأقصى للغياب بدون عذر رسمي (إنذار حرمان)")}
                              className="btn btn-primary btn-sm"
                              style={{ background: "var(--danger)", border: "none" }}
                            >
                              إرسال إنذار حرمان ⚠️
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Daily attendance sheet by section */}
            <div className="card">
              <div className="card-header" style={{ flexWrap: "wrap", gap: 12 }}>
                <div>
                  <div className="card-title">كشف رصد المعلمين اليومي</div>
                  <div className="card-subtitle">معاينة الرصد المباشر للحصص من تطبيق المعلم</div>
                </div>
                <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
                  <input
                    type="date"
                    value={selectedDate}
                    onChange={(e) => setSelectedDate(e.target.value)}
                    style={{
                      height: 32,
                      border: "1px solid var(--border)",
                      borderRadius: "var(--radius)",
                      padding: "0 8px",
                      fontSize: 12,
                      fontFamily: "Cairo, sans-serif",
                      background: "var(--bg-page)",
                      color: "var(--text-dark)",
                    }}
                  />
                  <button onClick={handleRefresh} className="btn btn-ghost btn-sm" title="تحديث الكشف">
                    <RefreshCw size={14} className={isLoadingSection ? "spin" : ""} />
                  </button>
                </div>
              </div>

              {/* Sections Selector */}
              <div style={{ padding: "0 20px 14px", display: "flex", gap: 6, flexWrap: "wrap", borderBottom: "1px solid var(--border-light)" }}>
                {sections.map((sec) => (
                  <button
                    key={sec.id}
                    onClick={() => handleSectionChange(sec.id)}
                    className={`btn ${selectedSection === sec.id ? "btn-primary" : "btn-ghost"} btn-sm`}
                  >
                    {sec.name}
                  </button>
                ))}
              </div>

              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الطالب</th>
                      <th>حالة الرصد اليوم</th>
                      <th>تفاصيل الحصص</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dailyStudents.length === 0 ? (
                      <tr>
                        <td colSpan={3} style={{ textAlign: "center", padding: 30, color: "var(--text-muted)" }}>
                          {isLoadingSection ? "جاري تحميل كشف الرصد..." : "لا توجد حصص مرصودة لهذه الشعبة في التاريخ المحدد."}
                        </td>
                      </tr>
                    ) : (
                      dailyStudents.map((item) => {
                        const s = item.student;
                        return (
                          <tr key={s.id}>
                            <td>
                              <div style={{ fontWeight: 700 }}>{s.full_name}</div>
                              <div style={{ fontSize: 11, color: "var(--text-muted)", fontFamily: "monospace" }}>{s.admission_number}</div>
                            </td>
                            <td>
                              {getStudentStatusBadge(
                                item.summary_status,
                                item.absent_periods,
                                item.late_periods,
                                item.present_periods,
                                item.recorded_periods,
                                item.expected_periods
                              )}
                            </td>
                            <td>
                              <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                                {item.periods.map((p, pIdx) => {
                                  const pBg =
                                    p.status === "present"
                                      ? "var(--green)"
                                      : p.status === "absent"
                                        ? "var(--danger)"
                                        : p.status === "late"
                                          ? "var(--warning)"
                                          : p.status === "excused"
                                            ? "var(--primary)"
                                            : "var(--text-muted)";
                                  return (
                                    <span
                                      key={pIdx}
                                      style={{
                                        display: "inline-block",
                                        padding: "2px 6px",
                                        borderRadius: 4,
                                        fontSize: 10,
                                        fontWeight: 700,
                                        background: pBg + "20",
                                        color: pBg,
                                        border: `1px solid ${pBg}40`,
                                      }}
                                      title={`${p.subject_name ?? "حصة"}: ${p.starts_at}-${p.ends_at} (${p.status})`}
                                    >
                                      {p.subject_name ? p.subject_name.slice(0, 8) : `حصة ${pIdx + 1}`}
                                    </span>
                                  );
                                })}
                              </div>
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </main>
        <Footer />
      </div>
    </div>
  );
}
