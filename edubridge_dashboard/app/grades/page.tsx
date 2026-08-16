"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { Student } from "@/data/mockData";
import { CheckCircle, Clock, FileText, Check, AlertTriangle, Filter, Award, BookOpen } from "lucide-react";

function scoreColor(scorePct: number) {
  if (scorePct >= 90) return "var(--green)";
  if (scorePct >= 75) return "var(--warning)";
  return "var(--danger)";
}

function gradeLabel(scorePct: number) {
  if (scorePct >= 90) return { text: "ممتاز", cls: "badge-green" };
  if (scorePct >= 80) return { text: "جيد جداً", cls: "badge-green" };
  if (scorePct >= 70) return { text: "جيد", cls: "badge-blue" };
  if (scorePct >= 60) return { text: "مقبول", cls: "badge-orange" };
  return { text: "ضعيف (يحتاج دعم)", cls: "badge-red" };
}

interface GradeTemplate {
  id: string;
  name: string;
  maxScore: number;
  weight: string;
  type: "اختبار فصلي" | "أعمال سنة" | "اختبار نهائي" | "اختبار قصير";
  date: string;
}

const templates: GradeTemplate[] = [
  { id: "midterm", name: "اختبار منتصف الفصل الدراسي الأول", maxScore: 100, weight: "30%", type: "اختبار فصلي", date: "يوليو 2026" },
  { id: "coursework", name: "أعمال السنة والمشاركة والواجبات", maxScore: 50, weight: "20%", type: "أعمال سنة", date: "تقييم مستمر" },
  { id: "final", name: "الاختبار النهائي المركزي للفصل الأول", maxScore: 100, weight: "40%", type: "اختبار نهائي", date: "أغسطس 2026" },
  { id: "unit2", name: "اختبار الوحدة الثانية (قياس أداء مرحلي)", maxScore: 20, weight: "10%", type: "اختبار قصير", date: "يونيو 2026" },
];

export default function GradesPage() {
  const {
    students,
    sections,
    subjects,
    dashboardAssessments,
    reportExports,
    approveSectionGrades,
    requestGradeSheetExport,
    updateAssessmentGradesFromDashboard,
    refreshReportExport,
    showToast,
    apiStatus,
  } = useDashboard();
  const [selectedSectionIdx, setSelectedSectionIdx] = useState(0);
  const [selectedTemplateId, setSelectedTemplateId] = useState("midterm");
  const [approvedMap, setApprovedMap] = useState<Record<string, string[]>>({
    midterm: ["s1"],
    coursework: ["s1", "s2"],
    final: [],
    unit2: ["s1", "s2", "s3"],
  });

  const section = sections[selectedSectionIdx] || sections[0];
  const sectionStudents = section ? students.filter(s => s.sectionId === section.id) : [];
  const gradeSubjects = subjects.slice(0, 5);
  const template = templates.find(t => t.id === selectedTemplateId) || templates[0];
  const visibleAssessments = dashboardAssessments.filter((assessment) =>
    !section?.id ||
    assessment.section?.id === section.id ||
    assessment.section?.name === section.name,
  );

  const approvedList = approvedMap[selectedTemplateId] || [];
  const isApproved = section ? approvedList.includes(section.id) : false;
  const visibleReportExports = Object.values(reportExports).slice(0, 3);

  function getScore(student: Student, subjectIdx: number, maxScore: number) {
    // Realistic score anchored to student's actual academic profile from mockData
    const seed = (student.studentCode.charCodeAt(student.studentCode.length - 1) * 13) + (subjectIdx * 17) + (selectedTemplateId.charCodeAt(0) * 7);
    const variance = ((seed % 15) - 7); // -7% to +7% variance per subject
    const basePct = Math.min(100, Math.max(35, student.academicScore + variance));
    return Math.round((basePct / 100) * maxScore);
  }

  const handleApprove = () => {
    if (!isApproved) {
      if (!section) return;
      setApprovedMap(prev => ({
        ...prev,
        [selectedTemplateId]: [...(prev[selectedTemplateId] || []), section.id]
      }));
    }
    if (section) approveSectionGrades(`${section.name} (${template.name})`);
  };

  const handleExportPDF = () => {
    const assessment = visibleAssessments.find((item) => /^\d+$/.test(item.id));
    if (assessment) {
      void requestGradeSheetExport(assessment.id);
      return;
    }

    showToast("جاري إصدار الكشوفات الرسمية 📄", `تم تجهيز كشوفات درجات (${template.name}) لـ ${section?.name ?? "الفصل"} بصيغة PDF.`, "info");
  };

  const handleSaveLiveGrade = (assessmentId: string) => {
    const assessment = dashboardAssessments.find((item) => item.id === assessmentId);
    const student = students.find((item) =>
      /^\d+$/.test(item.id) &&
      (!assessment?.section?.id || item.sectionId === assessment.section.id),
    );

    if (!student) {
      showToast("Grades API", "Live grade save needs a backend student in the assessment section.", "warning");
      return;
    }

    const scoreText = window.prompt("Score", String(Math.min(assessment?.max_score ?? template.maxScore, template.maxScore)));
    if (!scoreText) return;
    const score = Number(scoreText);
    if (!Number.isFinite(score) || score < 0) {
      showToast("Grades API", "Score must be a positive number.", "warning");
      return;
    }

    void updateAssessmentGradesFromDashboard(assessmentId, [
      { student_id: student.id, score, note: "Saved from dashboard grades page." },
    ]);
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header 
          title="كنترول الدرجات وقوالب التقييم الأكاديمي" 
          subtitle="إدارة التقييمات الدورية، اختيار قوالب الرصد (أعمال السنة، النصفي، النهائي)، واعتماد الدرجات ونشرها" 
        />
        <main className="page-body">

          {apiStatus !== "live" && (<>
          {/* Template Selector Card */}
          <div className="card" style={{ marginBottom: 20, padding: "16px 20px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 14, marginBottom: 14 }}>
              <div>
                <div style={{ fontSize: 15, fontWeight: 800, color: "var(--text-dark)", display: "flex", alignItems: "center", gap: 8 }}>
                  <Award size={18} color="var(--primary)" /> قالب التقييم الأكاديمي المفتوح للرصد والتدقيق
                </div>
                <div style={{ fontSize: 12, color: "var(--text-muted)", marginTop: 2 }}>اختر نوع الاختبار أو التقييم المستمر لمعاينة الدرجات المرفوعة من المعلمين</div>
              </div>
              <div style={{ display: "flex", gap: 8 }}>
                <span className="badge badge-blue">الوزن النسبي: {template.weight}</span>
                <span className="badge badge-green">الدرجة العظمى: {template.maxScore} درجة</span>
              </div>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 10 }}>
              {templates.map((tpl) => {
                const active = tpl.id === selectedTemplateId;
                const approvedCount = (approvedMap[tpl.id] || []).length;
                return (
                  <div
                    key={tpl.id}
                    onClick={() => setSelectedTemplateId(tpl.id)}
                    style={{
                      padding: "12px 14px", borderRadius: "var(--radius)",
                      border: `1.5px solid ${active ? "var(--primary)" : "var(--border)"}`,
                      background: active ? "var(--primary-50)" : "var(--bg-page)",
                      cursor: "pointer", transition: "all 0.15s",
                      display: "flex", flexDirection: "column", justifyContent: "space-between",
                    }}
                  >
                    <div>
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
                        <span style={{ fontSize: 11, fontWeight: 700, color: active ? "var(--primary)" : "var(--text-light)" }}>{tpl.type}</span>
                        <span style={{ fontSize: 10.5, color: "var(--text-muted)", fontFamily: "monospace" }}>الدرجة: {tpl.maxScore}</span>
                      </div>
                      <div style={{ fontSize: 13, fontWeight: 800, color: "var(--text-dark)", lineHeight: 1.4, marginBottom: 8 }}>{tpl.name}</div>
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: 11, borderTop: "1px solid var(--border-light)", paddingTop: 6 }}>
                      <span style={{ color: "var(--text-muted)" }}>📅 {tpl.date}</span>
                      <span style={{ color: approvedCount === sections.length ? "var(--green)" : "var(--warning)", fontWeight: 700 }}>
                        ✓ اعتُمد ({approvedCount}/{sections.length})
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
          </>)}

          {visibleAssessments.length > 0 && (
            <div className="card" style={{ marginBottom: 20 }}>
              <div className="card-header">
                <div>
                  <div className="card-title">التقييمات والاختبارات الأكاديمية المسجلة</div>
                  <div className="card-subtitle">متابعة رصد درجات الطلاب، الاعتماد الإداري، وتصدير كشوفات الدرجات</div>
                </div>
                <span className="badge badge-green">{visibleAssessments.length} تقييم معتمد</span>
              </div>
              <div>
                {visibleAssessments.slice(0, 8).map((assessment, index) => {
                  const statusMap: Record<string, { label: string; cls: string }> = {
                    draft: { label: "مسودة", cls: "badge-gray" },
                    submitted: { label: "بانتظار الاعتماد", cls: "badge-orange" },
                    approved: { label: "معتمد", cls: "badge-green" },
                    published: { label: "منشور لأولياء الأمور", cls: "badge-blue" },
                    locked: { label: "مغلق نهائياً", cls: "badge-gray" },
                  };
                  const statusInfo = statusMap[assessment.status ?? "draft"] ?? { label: assessment.status ?? "معتمد", cls: "badge-blue" };
                  const entered = assessment.grade_summary?.entered_entries ?? 0;
                  const expected = assessment.grade_summary?.expected_students ?? 0;
                  const missing = assessment.grade_summary?.missing_scores ?? 0;

                  return (
                    <div key={assessment.id} className="feed-item" style={{ borderBottom: index < Math.min(visibleAssessments.length, 8) - 1 ? "1px solid var(--border-light)" : "none" }}>
                      <div style={{ flex: 1 }}>
                        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center", marginBottom: 5 }}>
                          <strong style={{ fontSize: 13.5 }}>{assessment.title}</strong>
                          <span className={`badge ${statusInfo.cls}`}>{statusInfo.label}</span>
                          <span className="badge badge-gray">{assessment.subject?.name ?? assessment.type}</span>
                        </div>
                        <div style={{ fontSize: 12, color: "var(--text-light)" }}>
                          الشعبة: <strong>{assessment.section?.name ?? "عام"}</strong> — تم رصد: <strong>{entered}</strong> من أصل {expected} طالب {missing > 0 ? `(متبقي ${missing} لم ترصد لهم درجات)` : "✓ اكتمل الرصد"}
                        </div>
                      </div>
                      <div style={{ display: "flex", gap: 6, flexWrap: "wrap", alignItems: "center" }}>
                        <button className="btn btn-ghost btn-sm" onClick={() => void requestGradeSheetExport(assessment.id)}>
                          <FileText size={14} /> تصدير PDF
                        </button>
                        <button className="btn btn-outline btn-sm" onClick={() => handleSaveLiveGrade(assessment.id)}>
                          <Check size={14} /> رصد درجة
                        </button>
                        {assessment.available_actions?.some((action) => ["approve", "publish", "lock"].includes(action)) && (
                          <button
                            className="btn btn-green btn-sm"
                            onClick={() => approveSectionGrades(`${assessment.section?.name ?? section?.name ?? "الفصل"} (${assessment.title})`)}
                          >
                            <CheckCircle size={14} /> اعتماد ونشر
                          </button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
              {visibleReportExports.length > 0 && (
                <div style={{ borderTop: "1px solid var(--border-light)", padding: "12px 16px", display: "grid", gap: 8 }}>
                  {visibleReportExports.map((reportExport) => (
                    <div key={reportExport.export_id} style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "center", fontSize: 12 }}>
                      <span style={{ fontFamily: "monospace", color: "var(--text-muted)" }}>{reportExport.export_id}</span>
                      <span className="badge badge-blue">{reportExport.status === "completed" ? "جاهز للتحميل" : "قيد المعالجة"}</span>
                      {reportExport.download_url ? (
                        <a className="btn btn-green btn-sm" href={reportExport.download_url} target="_blank" rel="noreferrer">
                          تحميل الكشف
                        </a>
                      ) : (
                        <button className="btn btn-ghost btn-sm" onClick={() => void refreshReportExport(reportExport.export_id)}>
                          <Clock size={14} /> تحديث الحالة
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {apiStatus === "live" && visibleAssessments.length === 0 && (
            <div className="card" style={{ marginBottom: 20, padding: 24, textAlign: "center", color: "var(--text-muted)" }}>
              لا توجد تقييمات مسجلة لهذه المدرسة/الشعبة حالياً. لم يتم إنشاء درجات تجريبية.
            </div>
          )}

          {apiStatus !== "live" && (<>
          {/* Status cards */}
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 16, marginBottom: 24 }}>
            {[
              { icon: <Clock size={22} />, value: sections.length - approvedList.length, label: "فصول تنتظر المراجعة والاعتماد", bg: "var(--warning-50)", color: "var(--warning)" },
              { icon: <CheckCircle size={22} />, value: approvedList.length, label: "فصول اعتمدت ومنشورة للأهالي", bg: "var(--green-50)", color: "var(--green)" },
              { icon: <FileText size={22} />, value: `${students.length} شهادة`, label: `شهادات جاهزة (${template.name})`, bg: "var(--primary-50)", color: "var(--primary)" },
            ].map((s, i) => (
              <div className="kpi-card" key={i}>
                <div className="kpi-icon" style={{ background: s.bg, color: s.color }}>{s.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 18 }}>{s.value}</div>
                  <div className="kpi-label">{s.label}</div>
                </div>
              </div>
            ))}
          </div>

          {/* Section selector */}
          <div style={{ display: "flex", gap: 8, marginBottom: 16, flexWrap: "wrap" }}>
            {sections.map((s, i) => (
              <button
                key={s.id}
                onClick={() => setSelectedSectionIdx(i)}
                className={`btn ${i === selectedSectionIdx ? "btn-primary" : "btn-ghost"} btn-sm`}
              >
                {s.name}
                {approvedList.includes(s.id) && (
                  <span style={{ color: i === selectedSectionIdx ? "white" : "var(--green)", fontWeight: 800 }}> ✓</span>
                )}
              </button>
            ))}
          </div>

          {/* Grade Table */}
          <div className="card">
            <div className="card-header">
              <div>
                <div className="card-title">
                  رصد {section.name} — {template.name} {isApproved ? " (معتمدة ومرسلة لأولياء الأمور ✓)" : ""}
                </div>
                <div className="card-subtitle">الدرجة العظمى: {template.maxScore} درجة — {sectionStudents.length} طالب مسجل</div>
              </div>
              <div style={{ display: "flex", gap: 8 }}>
                <button onClick={handleExportPDF} className="btn btn-ghost btn-sm">
                  <FileText size={14} /> تصدير الكشوفات (PDF)
                </button>
                <button
                  onClick={handleApprove}
                  className={`btn ${isApproved ? "btn-outline" : "btn-green"} btn-sm`}
                  style={isApproved ? { borderColor: "var(--green)", color: "var(--green)" } : {}}
                >
                  <CheckCircle size={14} /> {isApproved ? "إعادة إرسال إشعار الاعتماد" : "اعتماد الدرجات ونشرها للتطبيق"}
                </button>
              </div>
            </div>
            <div className="data-table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>الطالب والرقم المدرسي</th>
                    {gradeSubjects.map(sub => (
                      <th key={sub.id} style={{ textAlign: "center" }}>{sub.icon} {sub.name}</th>
                    ))}
                    <th style={{ textAlign: "center" }}>المجموع</th>
                    <th style={{ textAlign: "center" }}>النسبة المئوية</th>
                    <th style={{ textAlign: "center" }}>التقدير والاعتماد</th>
                  </tr>
                </thead>
                <tbody>
                  {sectionStudents.map((student, si) => {
                    const scores = gradeSubjects.map((_, subIdx) => getScore(student, subIdx, template.maxScore));
                    const totalScore = scores.reduce((a, b) => a + b, 0);
                    const maxTotal = template.maxScore * gradeSubjects.length;
                    const pct = Math.round((totalScore / maxTotal) * 100);

                    return (
                      <tr key={student.id}>
                        <td>
                          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                            <div style={{
                              width: 32, height: 32, borderRadius: "50%",
                              background: student.avatarColor + "20", color: student.avatarColor,
                              display: "flex", alignItems: "center", justifyContent: "center",
                              fontSize: 12, fontWeight: 800, flexShrink: 0,
                            }}>{student.avatarInitials}</div>
                            <div>
                              <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)" }}>{student.name}</div>
                              <div style={{ fontSize: 11, color: "var(--text-muted)", fontFamily: "monospace" }}>{student.studentCode}</div>
                            </div>
                          </div>
                        </td>
                        {scores.map((score, idx) => {
                          const subPct = (score / template.maxScore) * 100;
                          return (
                            <td key={idx} style={{ textAlign: "center", fontWeight: 700, color: scoreColor(subPct) }}>
                              {score}<span style={{ fontSize: 10, color: "var(--text-muted)" }}>/{template.maxScore}</span>
                            </td>
                          );
                        })}
                        <td style={{ textAlign: "center", fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>
                          {totalScore}<span style={{ fontSize: 10, color: "var(--text-muted)" }}>/{maxTotal}</span>
                        </td>
                        <td style={{ textAlign: "center" }}>
                          <span style={{
                            fontWeight: 800, fontSize: 14,
                            color: scoreColor(pct),
                          }}>{pct}%</span>
                        </td>
                        <td style={{ textAlign: "center" }}>
                          {(() => {
                            const { text, cls } = gradeLabel(pct);
                            return <span className={`badge ${cls}`}>{text}</span>;
                          })()}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
          </>)}
        </main>
        <Footer />
      </div>
    </div>
  );
}
