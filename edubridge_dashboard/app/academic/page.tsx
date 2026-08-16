"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { Plus, BookOpen, Layers, CheckCircle } from "lucide-react";

export default function AcademicPage() {
  const { sections, subjects, addAcademicSection, addAcademicSubject } = useDashboard();

  const handleAddSection = () => {
    const gradeLevelId = sections.find((section) => section.gradeLevelId)?.gradeLevelId;
    const name = window.prompt("اسم الشعبة الجديدة", "شعبة جديدة");
    if (!name?.trim()) return;

    const code = window.prompt("كود/رقم القاعة", `SEC-${Date.now().toString(36).toUpperCase()}`);
    if (!code?.trim()) return;

    addAcademicSection(name.trim(), code.trim(), gradeLevelId, 30);
  };

  const handleAddSubject = () => {
    const name = window.prompt("اسم المادة الجديدة", "مادة جديدة");
    if (!name?.trim()) return;

    const code = window.prompt("كود المادة", `SUB-${Date.now().toString(36).toUpperCase()}`);
    if (!code?.trim()) return;

    addAcademicSubject(
      name.trim(),
      code.trim(),
      Array.from(new Set(sections.map((section) => section.gradeLevelId).filter(Boolean))) as string[],
    );
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="البنية الأكاديمية للمدرسة وتوزيع الفصول" subtitle="إدارة الشعب، تخصيص القاعات، وهيكلة المقررات الدراسية المعتمدة في المنهج" />
        <main className="page-body">

          <div className="grid-2" style={{ marginBottom: 20 }}>
            {/* Sections */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">الفصول والشعب الدراسية</div>
                  <div className="card-subtitle">{sections.length} شعب مسجّلة ونشطة في الحرم المدرسي</div>
                </div>
                <button onClick={handleAddSection} className="btn btn-primary btn-sm">
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
                  <div className="card-subtitle">{subjects.length} مقررات معتمدة في الجدول الأسبوعي</div>
                </div>
                <button onClick={handleAddSubject} className="btn btn-primary btn-sm">
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
    </div>
  );
}
