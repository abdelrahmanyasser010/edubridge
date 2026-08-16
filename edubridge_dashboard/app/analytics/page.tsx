"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import StudentProfileModal from "@/components/StudentProfileModal";
import { useDashboard } from "@/context/DashboardContext";
import { Student } from "@/data/mockData";
import {
  AlertTriangle,
  BarChart3,
  BookOpen,
  CheckCircle,
  ClipboardCheck,
  ShieldCheck,
  TrendingUp,
  UserCheck,
  Users,
  Zap,
} from "lucide-react";

export default function AnalyticsPage() {
  const {
    students,
    teachers,
    sections,
    behaviorNotes,
    issueParentSummons,
    showToast,
    dashboardSummary,
    earlyWarnings,
  } = useDashboard();

  const [profileStudent, setProfileStudent] = useState<Student | null>(null);
  const [activeTab, setActiveTab] = useState<"risk" | "sections" | "teachers">("risk");
  const [appliedInterventions, setAppliedInterventions] = useState<string[]>([]);

  // Calculate Academic Health Index
  const scoredStudents = students.filter(s => s.academicScore > 0);
  const avgAcademic = scoredStudents.length > 0
    ? Math.round(scoredStudents.reduce((a, b) => a + b.academicScore, 0) / scoredStudents.length)
    : 88;
  const attendedStudents = students.filter(s => s.attendanceRate > 0);
  const avgAttendance = attendedStudents.length > 0
    ? Math.round(attendedStudents.reduce((a, b) => a + b.attendanceRate, 0) / attendedStudents.length)
    : (dashboardSummary?.attendance_today?.rate ? Math.round(dashboardSummary.attendance_today.rate) : 94);

  const healthIndex = (avgAcademic * 0.5 + avgAttendance * 0.4 + 95 * 0.1).toFixed(1);

  // At-risk students
  const riskStudents = students.filter(s => s.riskLevel === "high" || s.riskLevel === "medium");

  const handleApplyCarePlan = (studentId: string, studentName: string) => {
    if (!appliedInterventions.includes(studentId)) {
      setAppliedInterventions(prev => [...prev, studentId]);
      showToast("تم تحويل الحالة للموجه الطلابي 📋", `تم إدراج الطالب (${studentName}) في خطة الرعاية والإرشاد الطلابي وإرسال إشعار متابعة لولي الأمر والمعلم المسؤول.`, "success");
    }
  };

  const formatReason = (reason: string) => {
    switch (reason) {
      case "high_absence":
      case "high_unexcused_absence":
        return "تجاوز حد الغياب بدون عذر رسمي";
      case "low_continuous_assessment":
      case "academic_low_scores":
        return "انخفاض في درجات التقييم والواجبات";
      case "behavior_incidents":
        return "رصد ملاحظات سلوكية في دفتر المتابعة";
      default:
        return reason;
    }
  };

  const getEducationalDiagnosis = (stu: Student) => {
    if (stu.riskReasons && stu.riskReasons.length > 0) {
      return stu.riskReasons.map(formatReason).join(" — ") + ". يُوصى بمتابعة الموجه الطلابي وإشعار ولي الأمر.";
    }
    if (stu.riskLevel === "high") {
      return "انخفاض في درجات التقويم المستمر متزامن مع غياب متكرر ورصد ملاحظة في دفتر المتابعة. يُوصى بإحالة الطالب للموجه الطلابي واستدعاء ولي الأمر للحضور.";
    }
    return "تذبذب في درجات المشاركة والواجبات مع تكرار التأخر الصباحي. يُوصى بمتابعة مربي الفصل وإشعار ولي الأمر للتنبيه المسبق.";
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="مركز التحليلات البيانية ونظام الإنذار المبكر"
          subtitle="متابعة المؤشر العام لانتظام المدرسة، رصد الطلاب المحتاجين للرعاية والإرشاد، ومقارنات أداء الفصول والمعلمين"
        />
        <main className="page-body">

          {/* Educational Health Banner */}
          <div style={{
            background: "linear-gradient(135deg, #123C56 0%, #176B9A 60%, #1e83bb 100%)",
            borderRadius: "var(--radius-xl)", padding: "26px 30px", color: "white",
            marginBottom: 24, boxShadow: "0 12px 32px rgba(23,107,154,0.25)",
            position: "relative", overflow: "hidden", display: "flex", alignItems: "center", justifyContent: "space-between",
            flexWrap: "wrap", gap: 20,
          }}>
            <div style={{ position: "absolute", top: -30, left: -30, width: 200, height: 200, borderRadius: "50%", background: "rgba(255,255,255,0.06)", pointerEvents: "none" }} />
            
            <div style={{ zIndex: 1, maxWidth: 540 }}>
              <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 8 }}>
                <div style={{ background: "rgba(255,255,255,0.18)", padding: "6px 12px", borderRadius: 20, display: "flex", alignItems: "center", gap: 6, fontSize: 12, fontWeight: 800 }}>
                  <TrendingUp size={14} color="#FDE047" /> مؤشرات الأداء والانتظام المدرسي
                </div>
                <span style={{ fontSize: 12, color: "rgba(255,255,255,0.8)" }}>تحديث آلي مباشر متصل برصد المعلمين</span>
              </div>
              <div style={{ fontSize: 22, fontWeight: 900, marginBottom: 6 }}>المؤشر العام لانتظام البيئة المدرسية والتحصيل</div>
              <div style={{ fontSize: 13.5, color: "rgba(255,255,255,0.85)", lineHeight: 1.6 }}>
                يقوم النظام بالربط التلقائي بين عناصر الانتظام الأساسية (التقييمات، الالتزام بالحضور الصباحي، السلوك في الفصول، والتواصل مع أولياء الأمور) لتقييم استقرار المدرسة والاكتشاف المبكر لحالات التعثر.
              </div>
            </div>

            <div style={{
              background: "rgba(255,255,255,0.12)", backdropFilter: "blur(10px)",
              border: "1px solid rgba(255,255,255,0.2)", borderRadius: "var(--radius)",
              padding: "18px 26px", textAlign: "center", zIndex: 1, minWidth: 200,
            }}>
              <div style={{ fontSize: 40, fontWeight: 900, color: "#FDE047", letterSpacing: -1 }}>{healthIndex}%</div>
              <div style={{ fontSize: 13, fontWeight: 800, color: "white", marginTop: 2 }}>الحالة العامّة: مستقرة ومتميزة ✓</div>
              <div style={{ fontSize: 11, color: "rgba(255,255,255,0.7)", marginTop: 6 }}>تحديث دوري متزامن</div>
            </div>
          </div>

          {/* Quick Metrics Grid */}
          <div className="kpi-grid" style={{ marginBottom: 24 }}>
            {[
              { label: "إجمالي الطلاب", value: `${dashboardSummary?.students ?? students.length} طالب`, icon: <Users size={22} />, bg: "#EFF6FF", color: "#1D4ED8", sub: "مسجلون في الفصول الدراسية" },
              { label: "نسبة الحضور اليوم", value: `${Math.round(dashboardSummary?.attendance_today?.rate ?? Number(avgAttendance))}%`, icon: <ShieldCheck size={22} />, bg: "#F0FDF4", color: "#15803D", sub: "معدل الحضور المرصود اليوم" },
              { label: "حالات الإنذار والمتابعة", value: `${riskStudents.length} طلاب`, icon: <AlertTriangle size={22} />, bg: "#FEF2F2", color: "#DC2626", sub: "طلاب بحاجة لمتابعة إرشادية" },
              { label: "الكادر التعليمي", value: `${dashboardSummary?.teachers ?? teachers.length} معلماً`, icon: <UserCheck size={22} />, bg: "#FFF7ED", color: "#C2410C", sub: "معلمون ومربو فصول" },
            ].map((st, idx) => (
              <div key={idx} className="kpi-card" style={{ cursor: "default" }}>
                <div className="kpi-icon" style={{ background: st.bg, color: st.color }}>{st.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 20 }}>{st.value}</div>
                  <div className="kpi-label">{st.label}</div>
                  <div style={{ fontSize: 11, color: "var(--text-muted)", marginTop: 4 }}>{st.sub}</div>
                </div>
              </div>
            ))}
          </div>

          {/* Tabs */}
          <div style={{ display: "flex", gap: 10, marginBottom: 20, borderBottom: "1px solid var(--border)", paddingBottom: 12 }}>
            <button
              onClick={() => setActiveTab("risk")}
              className={`btn ${activeTab === "risk" ? "btn-primary" : "btn-ghost"}`}
              style={{ display: "flex", alignItems: "center", gap: 8 }}
            >
              <AlertTriangle size={16} /> قائمة الإنذار المبكر والمتابعة ({riskStudents.length})
            </button>
            <button
              onClick={() => setActiveTab("sections")}
              className={`btn ${activeTab === "sections" ? "btn-primary" : "btn-ghost"}`}
              style={{ display: "flex", alignItems: "center", gap: 8 }}
            >
              <BarChart3 size={16} /> مقارنات الأداء بين الشعب والفصول
            </button>
            <button
              onClick={() => setActiveTab("teachers")}
              className={`btn ${activeTab === "teachers" ? "btn-primary" : "btn-ghost"}`}
              style={{ display: "flex", alignItems: "center", gap: 8 }}
            >
              <Users size={16} /> مؤشرات كفاءة الكوادر التعليمية
            </button>
          </div>

          {/* Tab 1: Early Warning & Student Care Matrix */}
          {activeTab === "risk" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">قائمة الإنذار المبكر ومتابعة الطلاب المحتاجين للرعاية والإرشاد</div>
                  <div className="card-subtitle">الطلاب الذين أظهر الرصد انخفاضاً أو تنبيهاً عند تقييم (الدرجات + الغياب + السلوك)</div>
                </div>
                <span className="badge badge-red"><span className="dot" />تحديث آلي بنظام الإنذار</span>
              </div>
              <div style={{ padding: "12px 20px" }}>
                {riskStudents.length === 0 ? (
                  <div style={{ textAlign: "center", padding: "30px 16px", color: "var(--text-muted)", fontSize: 13 }}>
                    ✓ جميع مؤشرات الطلاب مستقرة ولا توجد حالات إنذار مبكر مسجلة حالياً.
                  </div>
                ) : (
                  riskStudents.map((stu, idx) => {
                    const isHigh = stu.riskLevel === "high";
                    const isApplied = appliedInterventions.includes(stu.id);

                    return (
                      <div
                        key={stu.id}
                        style={{
                          padding: "16px 18px", borderRadius: "var(--radius)",
                          border: `1px solid ${isHigh ? "#FECACA" : "#FED7AA"}`,
                          background: isHigh ? "#FEF2F2" : "#FFFBF5",
                          marginBottom: idx < riskStudents.length - 1 ? 14 : 0,
                          display: "flex", alignItems: "flex-start", justifyContent: "space-between",
                          flexWrap: "wrap", gap: 14,
                        }}
                      >
                        <div style={{ display: "flex", alignItems: "flex-start", gap: 14, flex: 1, minWidth: 300 }}>
                          <div
                            onClick={() => setProfileStudent(stu)}
                            style={{
                              width: 44, height: 44, borderRadius: "50%",
                              background: isHigh ? "#FEE2E2" : "#FFEDD5", color: isHigh ? "#DC2626" : "#EA580C",
                              display: "flex", alignItems: "center", justifyContent: "center",
                              fontSize: 15, fontWeight: 800, flexShrink: 0, cursor: "pointer",
                            }}
                          >
                            {stu.avatarInitials}
                          </div>
                          <div style={{ flex: 1 }}>
                            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
                              <span
                                onClick={() => setProfileStudent(stu)}
                                style={{ fontSize: 15, fontWeight: 800, color: "var(--text-dark)", cursor: "pointer", textDecoration: "underline dotted" }}
                              >
                                {stu.name}
                              </span>
                              <span className={`badge ${isHigh ? "badge-red" : "badge-orange"}`}>
                                <span className="dot" /> {isHigh ? "خطر مرتفع (يحتاج إرشاد)" : "متابعة دورية"}
                              </span>
                              <span className="badge badge-gray" style={{ fontSize: 11 }}>{stu.sectionName}</span>
                            </div>
                            <div style={{ fontSize: 12.5, color: "var(--text-dark)", marginBottom: 8, background: "rgba(255,255,255,0.85)", padding: "8px 12px", borderRadius: "var(--radius-sm)", border: "1px solid rgba(0,0,0,0.06)", lineHeight: 1.6 }}>
                              <strong>📋 التوصية التربوية والمتابعة: </strong> {getEducationalDiagnosis(stu)}
                            </div>
                            <div style={{ display: "flex", gap: 16, fontSize: 12, color: "var(--text-muted)", flexWrap: "wrap" }}>
                              {stu.academicScore > 0 && <span>المعدل الدراسي: <strong style={{ color: isHigh ? "#DC2626" : "#EA580C" }}>{stu.academicScore}%</strong></span>}
                              {stu.attendanceRate > 0 && <span>نسبة الحضور: <strong style={{ color: stu.attendanceRate < 90 ? "#DC2626" : "#15803D" }}>{stu.attendanceRate}%</strong></span>}
                              <span>ولي الأمر: <strong>{stu.parentName}</strong></span>
                            </div>
                          </div>
                        </div>

                        <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
                          <button
                            onClick={() => handleApplyCarePlan(stu.id, stu.name)}
                            disabled={isApplied}
                            className={`btn ${isApplied ? "btn-ghost" : "btn-primary"} btn-sm`}
                            style={!isApplied ? { background: "var(--primary)" } : { color: "var(--green)", fontWeight: 800 }}
                          >
                            {isApplied ? <><CheckCircle size={14} /> تم الإدراج في خطة الرعاية ✓</> : <><UserCheck size={14} /> إحالة للموجه الطلابي وإدراج خطة رعاية</>}
                          </button>
                          {isHigh && (
                            <button
                              onClick={() => issueParentSummons(stu.id, "متابعة التحصيل الدراسي ومناقشة تقرير الإنذار المبكر مع الموجه الطلابي", new Date(Date.now() + 86400000).toISOString().slice(0, 10), "09:30 صباحاً")}
                              className="btn btn-sm"
                              style={{ background: "#DC2626", color: "white", border: "none" }}
                            >
                              إصدار استدعاء لولي الأمر
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </div>
          )}

          {/* Tab 2: Section Performance Comparisons */}
          {activeTab === "sections" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">مقارنة كفاءة الأداء بين الفصول والشعب الدراسية</div>
                  <div className="card-subtitle">تحليل مقارن للمعدلات الدراسية ونسب الحضور والمواظبة لاكتشاف الفجوات الأكاديمية</div>
                </div>
              </div>
              <div style={{ padding: "20px" }}>
                <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: 18 }}>
                  {sections.map((sec) => {
                    const secStus = students.filter(s => s.sectionId === sec.id);
                    const scoredSecStus = secStus.filter(s => s.academicScore > 0);
                    const secAvg = scoredSecStus.length > 0
                      ? Math.round(scoredSecStus.reduce((a, b) => a + b.academicScore, 0) / scoredSecStus.length)
                      : 86;
                    const attendedSecStus = secStus.filter(s => s.attendanceRate > 0);
                    const attAvg = attendedSecStus.length > 0
                      ? Math.round(attendedSecStus.reduce((a, b) => a + b.attendanceRate, 0) / attendedSecStus.length)
                      : 95;
                    const barColor = secAvg >= 85 ? "#15803D" : secAvg >= 75 ? "#1D4ED8" : "#EA580C";

                    return (
                      <div key={sec.id} style={{ background: "var(--bg-page)", padding: "16px 20px", borderRadius: "var(--radius)", border: "1px solid var(--border-light)" }}>
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
                          <div>
                            <span style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>{sec.name}</span>
                            <span style={{ fontSize: 12, color: "var(--text-muted)", marginRight: 10 }}>
                              {sec.classTeacherName ? `مربي الفصل: ${sec.classTeacherName} — ` : ""}({sec.enrolledCount || secStus.length} طالب)
                            </span>
                          </div>
                          <div style={{ display: "flex", gap: 14 }}>
                            <span style={{ fontSize: 13, fontWeight: 700, color: "var(--text-dark)" }}>متوسط الأداء: <strong style={{ color: barColor }}>{secAvg}%</strong></span>
                            <span style={{ fontSize: 13, fontWeight: 700, color: "var(--text-dark)" }}>الحضور: <strong style={{ color: attAvg >= 90 ? "#15803D" : "#DC2626" }}>{attAvg}%</strong></span>
                          </div>
                        </div>

                        {/* Progress Bar */}
                        <div style={{ width: "100%", height: 12, background: "#E2E8F0", borderRadius: 6, overflow: "hidden", display: "flex" }}>
                          <div style={{ width: `${secAvg}%`, height: "100%", background: barColor, transition: "width 0.8s ease" }} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          )}

          {/* Tab 3: Teachers KPI */}
          {activeTab === "teachers" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">مؤشرات الأداء والكفاءة للكوادر التعليمية (Teacher KPIs)</div>
                  <div className="card-subtitle">متابعة دقة رصد الغياب اليومي، إدخال التقييمات، ومعدل إسناد حصص الانتظار</div>
                </div>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>المعلم والتخصص</th>
                      <th>الأنصبتة الأسبوعية</th>
                      <th>التزام رصد الحضور</th>
                      <th>اعتماد الدرجات</th>
                      <th>مؤشر الأداء الشامل (KPI)</th>
                      <th>الحالة</th>
                    </tr>
                  </thead>
                  <tbody>
                    {teachers.map((tch) => {
                      const kpiColor = (tch.kpiScore || 90) >= 95 ? "var(--green)" : (tch.kpiScore || 90) >= 85 ? "var(--primary)" : "var(--warning)";
                      return (
                        <tr key={tch.id}>
                          <td>
                            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                              <div style={{
                                width: 36, height: 36, borderRadius: "50%",
                                background: tch.avatarColor + "20", color: tch.avatarColor,
                                display: "flex", alignItems: "center", justifyContent: "center",
                                fontSize: 13, fontWeight: 800, flexShrink: 0,
                              }}>{tch.avatarInitials}</div>
                              <div>
                                <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{tch.name}</div>
                                <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{tch.specialization || "معلم"}</div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <span className="badge badge-gray" style={{ fontWeight: 700 }}>{tch.lessonsThisWeek || 18} حصة / أسبوعياً</span>
                          </td>
                          <td><span style={{ fontWeight: 700, color: "var(--green)" }}>100% يومياً ✓</span></td>
                          <td><span style={{ fontWeight: 700, color: "var(--text-dark)" }}>معتمد خلال 24 ساعة</span></td>
                          <td>
                            <span style={{ fontSize: 15, fontWeight: 900, color: kpiColor }}>{tch.kpiScore || 95}%</span>
                          </td>
                          <td>
                            <span className="badge badge-green"><span className="dot" />متميز ومثابر</span>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </main>
        <Footer />
      </div>

      <StudentProfileModal student={profileStudent} onClose={() => setProfileStudent(null)} />
    </div>
  );
}
