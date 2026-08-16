"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import OperationsModal from "@/components/OperationsModal";
import { useDashboard } from "@/context/DashboardContext";
import { Calendar, Clock, Plus, RefreshCw, AlertTriangle, CheckCircle } from "lucide-react";

const days = ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس"];
const periods = [1, 2, 3, 4, 5, 6, 7];

export default function SchedulePage() {
  const {
    sections, subjects, teachers, substitutes, showToast, apiStatus,
    dashboardSchedules, scheduleConflictResult, runScheduleConflictCheck,
  } = useDashboard();
  const [selectedSectionIdx, setSelectedSectionIdx] = useState(0);
  const [modalOpen, setModalOpen] = useState(false);

  const section = sections[selectedSectionIdx] || sections[0];
  const liveSectionSlots = apiStatus === "live" && section
    ? dashboardSchedules.filter((slot) => slot.section_id === section.id)
    : [];
  const liveStartTimes = Array.from(new Set(liveSectionSlots.map((slot) => slot.starts_at).filter(Boolean) as string[])).sort();
  const displayRows = apiStatus === "live"
    ? liveStartTimes.map((time, index) => ({ key: time, label: `الحصة ${index + 1}`, time, period: index + 1 }))
    : periods.map((period) => ({ key: String(period), label: `الحصة ${period}`, time: `${7 + period}:00 ص`, period }));

  function getCell(sectionIdx: number, dayIdx: number, period: number, startTime?: string) {
    if (!section) return null;

    if (apiStatus === "live") {
      const slot = liveSectionSlots.find((item) => item.weekday === dayIdx + 1 && item.starts_at === startTime);
      if (!slot) return null;
      const knownSubject = subjects.find((item) => item.id === slot.subject_id);
      return {
        subject: knownSubject ?? { id: slot.subject_id ?? "", name: slot.subject_name ?? "مادة دراسية", code: "", weeklyPeriods: 0, icon: "📘", color: "#176B9A" },
        teacher: { name: slot.teacher_name ?? "معلم غير محدد", avatarColor: "#176B9A" },
        isSubstitute: false,
        room: slot.room ?? section.roomNumber,
        startsAt: slot.starts_at ?? "",
        endsAt: slot.ends_at ?? "",
      };
    }

    if (!subjects.length || !teachers.length) return null;
    const subjectIdx = (sectionIdx * 7 + dayIdx * 3 + period * 11) % subjects.length;
    const teacherIdx = (sectionIdx + dayIdx + period) % teachers.length;
    if ((sectionIdx + dayIdx + period) % 9 === 0) return null;
    const sub = subjects[subjectIdx];
    const teacher = teachers[teacherIdx];
    const subAssign = substitutes.find(s => s.period === period && s.sectionName.includes(section.name));
    if (subAssign && dayIdx === 1) {
      return {
        subject: sub,
        teacher: { name: `${subAssign.substituteTeacherName} (تغطية احتياط)`, avatarColor: "#EF4444" },
        isSubstitute: true,
        room: section.roomNumber,
        startsAt: "",
        endsAt: "",
      };
    }
    return { subject: sub, teacher, isSubstitute: false, room: section.roomNumber, startsAt: "", endsAt: "" };
  }

  const handleConflictCheck = () => {
    void runScheduleConflictCheck();
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="الجداول الدراسية وحصص الانتظار" subtitle="جداول حصص الفصول الأسبوعية وتكليف معلمي الانتظار لتغطية غياب زملائهم" />
        <main className="page-body">

          {/* Controls */}
          <div className="card" style={{ marginBottom: 20, padding: "16px 20px" }}>
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: 14 }}>
              <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                {sections.map((s, i) => (
                  <button
                    key={s.id}
                    onClick={() => setSelectedSectionIdx(i)}
                    className={`btn ${i === selectedSectionIdx ? "btn-primary" : "btn-ghost"} btn-sm`}
                  >
                    {s.name}
                  </button>
                ))}
              </div>
              <div style={{ display: "flex", gap: 10 }}>
                <button onClick={handleConflictCheck} className="btn btn-outline btn-sm">
                  <RefreshCw size={14} /> فحص التعارضات الآلي
                </button>
                <button onClick={() => setModalOpen(true)} className="btn btn-green btn-sm">
                  <Clock size={14} /> تكليف معلم انتظار
                </button>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-header">
              <div>
                <div className="card-title">جدول {section?.name ?? "الشعبة"} (متزامن مع Backend)</div>
                <div className="card-subtitle">الأسبوع الدراسي — الأحد إلى الخميس (7 حصص يومياً)</div>
              </div>
              <span className={`badge ${scheduleConflictResult?.has_conflict ? "badge-red" : "badge-green"}`}>
                <span className="dot" />
                {scheduleConflictResult?.has_conflict
                  ? `${scheduleConflictResult.conflicts.length} تعارضات مباشرة`
                  : `${liveSectionSlots.length} حصة مباشرة`}
              </span>
            </div>
            <div className="data-table-wrap" style={{ padding: "0 0 4px" }}>
              <table className="data-table" style={{ fontSize: 12 }}>
                <thead>
                  <tr>
                    <th style={{ width: 70, textAlign: "center" }}>الحصة</th>
                    {days.map(d => <th key={d} style={{ textAlign: "center" }}>{d}</th>)}
                  </tr>
                </thead>
                <tbody>
                  {displayRows.map((row) => (
                    <tr key={row.key}>
                      <td style={{ textAlign: "center", fontWeight: 800, color: "var(--text-muted)", fontSize: 12 }}>
                        <div>{row.label}</div>
                        <div style={{ fontSize: 10, fontWeight: 500 }}>{row.time}</div>
                      </td>
                      {days.map((_, di) => {
                        const cell = getCell(selectedSectionIdx, di, row.period, apiStatus === "live" ? row.time : undefined);
                        if (!cell) return (
                          <td key={di} style={{ textAlign: "center", color: "var(--text-muted)", fontSize: 11 }}>
                            <span style={{ background: "var(--bg-page)", padding: "4px 8px", borderRadius: 4, display: "inline-block" }}>—</span>
                          </td>
                        );
                        return (
                          <td key={di} style={{ padding: "8px 10px" }}>
                            <div
                              onClick={() => showToast(`تفاصيل ${row.label} — ${days[di]}`, `المادة: ${cell.subject.name} | المعلم: ${cell.teacher.name} | القاعة: ${cell.room || "غير محددة"}${cell.startsAt ? ` | ${cell.startsAt}-${cell.endsAt}` : ""}`, "info")}
                              style={{
                                background: cell.isSubstitute ? "#FEF2F2" : cell.subject.color + "15",
                                borderRight: `3px solid ${cell.isSubstitute ? "#EF4444" : cell.subject.color}`,
                                borderRadius: "var(--radius-sm)",
                                padding: "8px 10px",
                                cursor: "pointer",
                                transition: "all 0.15s",
                              }}
                            >
                              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 4 }}>
                                <span style={{ fontSize: 12.5, fontWeight: 800, color: "var(--text-dark)" }}>
                                  {cell.subject.icon} {cell.subject.name}
                                </span>
                                {cell.isSubstitute && (
                                  <span className="badge badge-red" style={{ fontSize: 9, padding: "1px 5px" }}>احتياط 🔄</span>
                                )}
                              </div>
                              <div style={{ fontSize: 11, color: cell.isSubstitute ? "#DC2626" : "var(--text-muted)", fontWeight: cell.isSubstitute ? 700 : 500 }}>
                                {cell.teacher.name}
                              </div>
                            </div>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
              {apiStatus === "live" && liveStartTimes.length === 0 && (
                <div style={{ padding: 24, textAlign: "center", color: "var(--text-muted)", fontSize: 13 }}>
                  لا توجد حصص مسجلة لهذه الشعبة في Dashboard Schedule API حالياً.
                </div>
              )}
            </div>
          </div>
        </main>
        <Footer />
      </div>

      <OperationsModal
        type={modalOpen ? "substitute" : null}
        onClose={() => setModalOpen(false)}
      />
    </div>
  );
}
