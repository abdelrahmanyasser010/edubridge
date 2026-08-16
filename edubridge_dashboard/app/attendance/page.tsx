"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { CheckCircle, AlertTriangle, Clock, Calendar, FileCheck } from "lucide-react";

export default function AttendancePage() {
  const router = useRouter();
  const { students, sections, attendance, sendParentWarning, medicalExcuses, apiStatus } = useDashboard();
  const [selectedSection, setSelectedSection] = useState("s1");

  const sectionStudents = students.filter(s => s.sectionId === selectedSection);
  const absentStudents = apiStatus === "live" ? [] : students.filter(s => s.attendanceRate < 90);
  const pendingExcuses = medicalExcuses.filter(e => e.status === "pending").length;

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="الحضور والغياب" subtitle="رصد غياب الطلاب اليومي وإرسال الإنذارات لأولياء الأمور" />
        <main className="page-body">

          {/* Quick stats */}
          <div className="kpi-grid" style={{ marginBottom: 20 }}>
            {[
              { label: "إجمالي الحضور اليوم", value: `${attendance.present} طالب`, icon: <CheckCircle size={22} />, bg: "#F0FDF4", color: "#16A34A" },
              { label: "الغياب المرصود اليوم", value: `${attendance.absent} طالب`, icon: <AlertTriangle size={22} />, bg: "#FEF2F2", color: "#EF4444" },
              { label: "حالات التأخر الصباحي", value: `${attendance.late} طالب`, icon: <Clock size={22} />, bg: "#FFF7ED", color: "#F59E0B" },
              { label: "أعذار صحتي بانتظار المراجعة", value: `${pendingExcuses} تقارير`, icon: <FileCheck size={22} />, bg: "#EFF6FF", color: "#176B9A", link: "/operations" },
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

          {apiStatus === "live" && (
            <div className="card" style={{ marginBottom: 20, padding: "14px 18px", borderRight: "4px solid var(--primary)" }}>
              <div style={{ fontWeight: 800, color: "var(--text-dark)", marginBottom: 4 }}>تفاصيل حضور كل طالب غير متاحة في Dashboard API الحالي</div>
              <div style={{ fontSize: 12.5, color: "var(--text-light)", lineHeight: 1.7 }}>الإجماليات أعلاه حقيقية من <code>/admin/dashboard/summary</code>. لم يتم عرض أو توليد حالات طلاب وهمية؛ يلزم Endpoint مخصص لكشف حضور الطلاب اليومي إذا أردت هذه الجداول تفصيلياً.</div>
            </div>
          )}

          <div className="grid-2">
            {/* Absentee students needing intervention */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">قائمة الإنذار المبكر (حرمان من دخول الاختبارات)</div>
                  <div className="card-subtitle">الطلاب الذين تجاوزت نسبة غيابهم الحد المسموح به دون أعذار في التطبيق</div>
                </div>
                <span className="badge badge-red">{absentStudents.length} طلاب بحاجة لتنبيه</span>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الطالب والشعبة</th>
                      <th>نسبة الحضور</th>
                      <th>مستوى الغياب</th>
                      <th>إجراء</th>
                    </tr>
                  </thead>
                  <tbody>
                    {apiStatus === "live" ? (
                      <tr><td colSpan={4} style={{ textAlign: "center", padding: 24, color: "var(--text-muted)" }}>الإنذار الفردي يحتاج بيانات حضور تراكمية لكل طالب من Backend؛ لذلك لم يتم احتسابها من بيانات تجريبية.</td></tr>
                    ) : absentStudents.map((stu) => (
                      <tr key={stu.id}>
                        <td>
                          <div style={{ fontWeight: 700 }}>{stu.name}</div>
                          <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{stu.sectionName}</div>
                        </td>
                        <td>
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <div className="progress-bar" style={{ width: 60 }}>
                              <div className="progress-fill" style={{ width: `${stu.attendanceRate}%`, background: "var(--danger)" }} />
                            </div>
                            <span style={{ fontWeight: 800, color: "var(--danger)" }}>{stu.attendanceRate}%</span>
                          </div>
                        </td>
                        <td><span className="badge badge-red"><span className="dot" />عالي جداً</span></td>
                        <td>
                          <button
                            onClick={() => sendParentWarning(stu.id, "الحد الأقصى للغياب بدون عذر (إنذار حرمان)")}
                            className="btn btn-primary btn-sm"
                            style={{ background: "var(--danger)", border: "none" }}
                          >
                            إرسال إنذار حرمان آلي ⚠️
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Daily attendance sheet by section */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">كشف رصد المعلمين اليومي</div>
                  <div className="card-subtitle">اختر الشعبة لمعاينة الرصد المرسل من تطبيق المعلم</div>
                </div>
                <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                  {sections.map((sec) => (
                    <button
                      key={sec.id}
                      onClick={() => setSelectedSection(sec.id)}
                      className={`btn ${selectedSection === sec.id ? "btn-primary" : "btn-ghost"} btn-sm`}
                    >
                      {sec.name}
                    </button>
                  ))}
                </div>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الطالب</th>
                      <th>حالة الرصد اليوم</th>
                      <th>ولي الأمر</th>
                    </tr>
                  </thead>
                  <tbody>
                    {apiStatus === "live" ? (
                      <tr>
                        <td colSpan={3} style={{ textAlign: "center", padding: 24, color: "var(--text-muted)" }}>لا يوجد API تفصيلي لحالة حضور كل طالب في لوحة الإدارة حالياً.</td>
                      </tr>
                    ) : sectionStudents.map((stu, i) => {
                      const status = i % 7 === 0 ? "غائب" : i % 5 === 0 ? "متأخر" : "حاضر";
                      const cls = status === "حاضر" ? "badge-green" : status === "متأخر" ? "badge-orange" : "badge-red";
                      return (
                        <tr key={stu.id}>
                          <td style={{ fontWeight: 700 }}>{stu.name}</td>
                          <td><span className={`badge ${cls}`}><span className="dot" />{status}</span></td>
                          <td style={{ fontSize: 12, color: "var(--text-light)" }}>{stu.parentName}</td>
                        </tr>
                      );
                    })}
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
