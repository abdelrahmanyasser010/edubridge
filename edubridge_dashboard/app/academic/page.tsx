"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { Plus, BookOpen, Layers, CheckCircle } from "lucide-react";

export default function AcademicPage() {
  const { sections, subjects, addAcademicSection, addAcademicSubject, showToast } = useDashboard();

  const [showSectionModal, setShowSectionModal] = useState(false);
  const [showSubjectModal, setShowSubjectModal] = useState(false);

  const [sectionName, setSectionName] = useState("");
  const [sectionCode, setSectionCode] = useState("");
  const [sectionCapacity, setSectionCapacity] = useState("30");

  const [subjectName, setSubjectName] = useState("");
  const [subjectCode, setSubjectCode] = useState("");
  const [subjectPeriods, setSubjectPeriods] = useState("4");

  const handleCreateSection = (e: React.FormEvent) => {
    e.preventDefault();
    if (!sectionName.trim()) return;
    const gradeLevelId = sections.find((section) => section.gradeLevelId)?.gradeLevelId;
    const code = sectionCode.trim() || `SEC-${Date.now().toString(36).toUpperCase()}`;
    addAcademicSection(sectionName.trim(), code, gradeLevelId, Number(sectionCapacity) || 30);
    showToast("تمت الإضافة", `تمت إضافة الشعبة ${sectionName} بنجاح.`, "success");
    setSectionName("");
    setSectionCode("");
    setSectionCapacity("30");
    setShowSectionModal(false);
  };

  const handleCreateSubject = (e: React.FormEvent) => {
    e.preventDefault();
    if (!subjectName.trim()) return;
    const code = subjectCode.trim() || `SUB-${Date.now().toString(36).toUpperCase()}`;
    addAcademicSubject(
      subjectName.trim(),
      code,
      Array.from(new Set(sections.map((section) => section.gradeLevelId).filter(Boolean))) as string[],
    );
    showToast("تمت الإضافة", `تمت إضافة المادة ${subjectName} بنجاح.`, "success");
    setSubjectName("");
    setSubjectCode("");
    setSubjectPeriods("4");
    setShowSubjectModal(false);
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="الفصول والمواد الدراسية" subtitle="إدارة الفصول والشعب، وتحديد الطاقة الاستيعابية وهيكلة المقررات المعتمدة" />
        <main className="page-body">

          <div className="grid-2" style={{ marginBottom: 20 }}>
            {/* Sections */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">الفصول والشعب الدراسية</div>
                  <div className="card-subtitle">{sections.length} شعب مسجّلة ونشطة في المدرسة</div>
                </div>
                <button onClick={() => setShowSectionModal(true)} className="btn btn-primary btn-sm">
                  <Plus size={14} /> إضافة شعبة جديدة
                </button>
              </div>
              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الشعبة</th>
                      <th>رقم القاعة</th>
                      <th>الطاقة الاستيعابية</th>
                      <th>الطلاب المسجلون</th>
                      <th>مربّي الفصل</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sections.map((s) => (
                      <tr key={s.id}>
                        <td style={{ fontWeight: 700, fontSize: 13 }}>{s.name}</td>
                        <td><span className="badge badge-gray" style={{ fontFamily: "monospace" }}>{s.roomNumber}</span></td>
                        <td style={{ textAlign: "center" }}>{s.capacity} طالب</td>
                        <td style={{ textAlign: "center" }}>
                          <span className={`badge ${s.enrolledCount >= s.capacity * 0.9 ? "badge-orange" : "badge-green"}`}>
                            {s.enrolledCount} / {s.capacity}
                          </span>
                        </td>
                        <td style={{ fontSize: 12, fontWeight: 600 }}>{s.classTeacherName}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Subjects */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">دليل المقررات والمواد التعليمية</div>
                  <div className="card-subtitle">{subjects.length} مقررات معتمدة في الخطة الدراسية</div>
                </div>
                <button onClick={() => setShowSubjectModal(true)} className="btn btn-primary btn-sm">
                  <Plus size={14} /> إضافة مادة
                </button>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {subjects.map((sub, idx) => (
                  <div key={sub.id} className="feed-item" style={{ borderBottom: idx < subjects.length - 1 ? "1px solid var(--border-light)" : "none" }}>
                    <div style={{
                      width: 44, height: 44, borderRadius: "var(--radius)",
                      background: sub.color + "20",
                      display: "flex", alignItems: "center", justifyContent: "center",
                      fontSize: 20, flexShrink: 0,
                    }}>{sub.icon}</div>
                    <div style={{ flex: 1 }}>
                      <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{sub.name}</div>
                      <div style={{ fontSize: 11, color: "var(--text-muted)", fontFamily: "monospace", marginTop: 2 }}>{sub.code}</div>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 12, color: "var(--text-light)" }}>
                      <BookOpen size={14} />
                      <strong style={{ color: "var(--text-dark)" }}>{sub.weeklyPeriods}</strong> حصص / أسبوع
                    </div>
                    <div style={{
                      width: 10, height: 10, borderRadius: "50%", background: sub.color, flexShrink: 0,
                    }} />
                  </div>
                ))}
              </div>
            </div>
          </div>
        </main>
        <Footer />
      </div>

      {/* Add Section Modal */}
      {showSectionModal && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>إضافة فصل / شعبة دراسية جديدة</div>
              <button onClick={() => setShowSectionModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18, color: "var(--text-muted)" }}>✕</button>
            </div>
            <form onSubmit={handleCreateSection} style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div className="form-group">
                <label className="form-label">اسم الشعبة الدراسية</label>
                <input required className="form-input" placeholder="مثال: الصف الخامس / شعبة ج" value={sectionName} onChange={e => setSectionName(e.target.value)} />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div className="form-group">
                  <label className="form-label">رقم / رمز القاعة</label>
                  <input className="form-input" placeholder="مثال: R-204" value={sectionCode} onChange={e => setSectionCode(e.target.value)} />
                </div>
                <div className="form-group">
                  <label className="form-label">الطاقة الاستيعابية</label>
                  <input required type="number" min="1" className="form-input" placeholder="30" value={sectionCapacity} onChange={e => setSectionCapacity(e.target.value)} />
                </div>
              </div>
              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <CheckCircle size={15} /> اعتماد الشعبة
                </button>
                <button type="button" onClick={() => setShowSectionModal(false)} className="btn btn-ghost">
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add Subject Modal */}
      {showSubjectModal && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>إضافة مادة / مقرر دراسي جديد</div>
              <button onClick={() => setShowSubjectModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18, color: "var(--text-muted)" }}>✕</button>
            </div>
            <form onSubmit={handleCreateSubject} style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div className="form-group">
                <label className="form-label">اسم المقرر الدراسي</label>
                <input required className="form-input" placeholder="مثال: الحاسب الآلي والذكاء الاصطناعي" value={subjectName} onChange={e => setSubjectName(e.target.value)} />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div className="form-group">
                  <label className="form-label">رمز المادة</label>
                  <input className="form-input" placeholder="مثال: CS-101" value={subjectCode} onChange={e => setSubjectCode(e.target.value)} />
                </div>
                <div className="form-group">
                  <label className="form-label">الحصص الأسبوعية</label>
                  <input required type="number" min="1" className="form-input" placeholder="4" value={subjectPeriods} onChange={e => setSubjectPeriods(e.target.value)} />
                </div>
              </div>
              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <CheckCircle size={15} /> اعتماد المقرر
                </button>
                <button type="button" onClick={() => setShowSubjectModal(false)} className="btn btn-ghost">
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
