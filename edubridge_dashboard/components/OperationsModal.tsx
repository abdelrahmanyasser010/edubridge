"use client";

import React, { useState } from "react";
import { useDashboard } from "@/context/DashboardContext";
import { X, UserPlus, Shield, BookOpen, Clock, FileText, AlertTriangle, CheckCircle, Video } from "lucide-react";

interface ModalProps {
  type: "add_student" | "add_teacher" | "recommendation" | "summons" | "substitute" | null;
  targetId?: string;
  onClose: () => void;
}

export default function OperationsModal({ type, targetId, onClose }: ModalProps) {
  const {
    students, teachers, sections, behaviorNotes,
    addStudent, addTeacher, attachRecommendation, issueParentSummons, assignSubstitute
  } = useDashboard();

  // Form states
  const [stuName, setStuName] = useState("");
  const [stuGrade, setStuGrade] = useState("الصف الخامس");
  const [stuSection, setStuSection] = useState(sections[0]?.id || "s1");
  const [parentName, setParentName] = useState("");
  const [parentPhone, setParentPhone] = useState("");

  const [tName, setTName] = useState("");
  const [tEmail, setTEmail] = useState("");
  const [tPhone, setTPhone] = useState("");
  const [tSpec, setTSpec] = useState("الرياضيات");

  const [recTitle, setRecTitle] = useState("خطة تعديل سلوك فردية + فيديو توجيهي");
  const [recDesc, setRecDesc] = useState("توجيه ولي الأمر لمتابعة جلسات الحوار اليومي مع الطالب ومراجعة المقطع المرئي المرفق من المشرف التربوي.");

  const [sumReason, setSumReason] = useState("تراجع المستوى الأكاديمي وتكرار الملاحظات السلوكية في الفسحة");
  const [sumDate, setSumDate] = useState(() => new Date(Date.now() + 86400000).toISOString().slice(0, 10));
  const [sumTime, setSumTime] = useState("10:30 صباحاً");

  const [subTeacherId, setSubTeacherId] = useState(teachers[0]?.id || "");
  const [subPeriod, setSubPeriod] = useState(3);

  if (!type) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (type === "add_student") {
      const sec = sections.find((s) => s.id === stuSection);
      addStudent({
        name: stuName,
        avatarInitials: stuName.split(" ").slice(0, 2).map((w) => w[0]).join(""),
        avatarColor: "#176B9A",
        gradeLevel: sec?.gradeLevel || stuGrade,
        sectionId: stuSection,
        sectionName: sec?.name || "الصف الخامس / شعبة أ",
        parentId: `p_${Date.now()}`,
        parentName: parentName || "ولي الأمر",
        parentPhone,
        academicScore: 88,
        attendanceRate: 98,
        riskLevel: "low",
      });
    } else if (type === "add_teacher") {
      addTeacher({
        name: tName,
        email: tEmail || "teacher@edubridge.sa",
        phone: tPhone || "0500000000",
        avatarInitials: tName.split(" ").slice(0, 2).map((w) => w[0]).join(""),
        avatarColor: "#7CC341",
        specialization: tSpec,
        assignedSections: ["s1", "s2"],
        assignedSubjects: ["sub1"],
        activeStatus: "active",
      });
    } else if (type === "recommendation" && targetId) {
      attachRecommendation(targetId, recTitle, recDesc);
    } else if (type === "summons" && targetId) {
      issueParentSummons(targetId, sumReason, sumDate, sumTime);
    } else if (type === "substitute" && targetId) {
      assignSubstitute(targetId, subTeacherId, "الصف الخامس / شعبة أ", subPeriod);
    }
    onClose();
  };

  const titles = {
    add_student: "تسجيل طالب جديد وربطه بولي أمره",
    add_teacher: "إضافة معلم جديد وتحديد تخصصه",
    recommendation: "إرفاق خطة علاجية لولي الأمر",
    summons: "إصدار استدعاء رسمي لولي الأمر",
    substitute: "تكليف معلم انتظار لتغطية حصة",
  };

  return (
    <div
      style={{
        position: "fixed",
        inset: 0,
        background: "rgba(18, 60, 86, 0.4)",
        backdropFilter: "blur(4px)",
        zIndex: 1000,
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: 20,
      }}
    >
      <div
        style={{
          background: "var(--bg-surface)",
          border: "1px solid var(--border)",
          borderRadius: "var(--radius-xl)",
          width: "100%",
          maxWidth: 500,
          boxShadow: "0 20px 50px rgba(0,0,0,0.15)",
          overflow: "hidden",
          direction: "rtl",
          animation: "modalZoom 0.2s cubic-bezier(0.16, 1, 0.3, 1)",
        }}
      >
        {/* Modal Header */}
        <div
          style={{
            padding: "16px 20px",
            borderBottom: "1px solid var(--border-light)",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            background: "var(--bg-page)",
          }}
        >
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <div
              style={{
                width: 36, height: 36, borderRadius: "var(--radius)",
                background: "var(--primary-100)", color: "var(--primary)",
                display: "flex", alignItems: "center", justifyContent: "center",
              }}
            >
              {type === "add_student" && <UserPlus size={18} />}
              {type === "add_teacher" && <UserPlus size={18} />}
              {type === "recommendation" && <BookOpen size={18} />}
              {type === "summons" && <AlertTriangle size={18} />}
              {type === "substitute" && <Clock size={18} />}
            </div>
            <div>
              <div style={{ fontSize: 15, fontWeight: 800, color: "var(--text-dark)" }}>
                {titles[type]}
              </div>
              <div style={{ fontSize: 11, color: "var(--text-light)" }}>
                ستصل مباشرة لتطبيقات ولي الأمر والمعلم
              </div>
            </div>
          </div>
          <button
            onClick={onClose}
            style={{ background: "transparent", border: "none", cursor: "pointer", color: "var(--text-muted)" }}
          >
            <X size={18} />
          </button>
        </div>

        {/* Modal Body */}
        <form onSubmit={handleSubmit} style={{ padding: "20px" }}>
          {type === "add_student" && (
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>اسم الطالب الثلاثي</label>
                <input required type="text" placeholder="مثال: عمر محمد القحطاني..." value={stuName} onChange={(e) => setStuName(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>اسم ولي الأمر (للربط العائلي)</label>
                <input required type="text" placeholder="مثال: محمد القحطاني..." value={parentName} onChange={(e) => setParentName(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>رقم جوال ولي الأمر</label>
                <input required type="tel" placeholder="0501234567" value={parentPhone} onChange={(e) => setParentPhone(e.target.value)} style={inputStyle} />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>المرحلة / الصف</label>
                  <select value={stuGrade} onChange={(e) => setStuGrade(e.target.value)} style={inputStyle}>
                    <option>الصف الرابع</option>
                    <option>الصف الخامس</option>
                    <option>الصف السادس</option>
                  </select>
                </div>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>تسكين الشعبة</label>
                  <select value={stuSection} onChange={(e) => setStuSection(e.target.value)} style={inputStyle}>
                    {sections.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                </div>
              </div>
            </div>
          )}

          {type === "add_teacher" && (
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>اسم المعلم</label>
                <input required type="text" placeholder="مثال: الأستاذ صالح العوفي..." value={tName} onChange={(e) => setTName(e.target.value)} style={inputStyle} />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>البريد الإلكتروني الرسمي</label>
                  <input required type="email" placeholder="name@edubridge.sa" value={tEmail} onChange={(e) => setTEmail(e.target.value)} style={inputStyle} />
                </div>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>رقم الجوال (لحساب التطبيق)</label>
                  <input required type="text" placeholder="0501234567" value={tPhone} onChange={(e) => setTPhone(e.target.value)} style={inputStyle} />
                </div>
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>التخصص الأكاديمي</label>
                <select value={tSpec} onChange={(e) => setTSpec(e.target.value)} style={inputStyle}>
                  <option>الرياضيات</option>
                  <option>اللغة العربية</option>
                  <option>العلوم</option>
                  <option>اللغة الإنجليزية</option>
                  <option>التربية الإسلامية</option>
                  <option>التربية البدنية</option>
                </select>
              </div>
            </div>
          )}

          {type === "recommendation" && (
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>عنوان الخطة العلاجية</label>
                <input required type="text" value={recTitle} onChange={(e) => setRecTitle(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>نص التوصية لولي الأمر</label>
                <textarea rows={4} value={recDesc} onChange={(e) => setRecDesc(e.target.value)} style={{ ...inputStyle, height: "auto", padding: "10px 12px", resize: "vertical" }} />
              </div>
              <div style={{ padding: "10px 12px", background: "var(--primary-50)", borderRadius: "var(--radius)", display: "flex", alignItems: "center", gap: 10 }}>
                <Video size={18} color="var(--primary)" />
                <span style={{ fontSize: 12, fontWeight: 700, color: "var(--primary)" }}>سيتم إرفاق مقطع فيديو توجيهي للمتابعة التربوية تلقائياً</span>
              </div>
            </div>
          )}

          {type === "summons" && (
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>سبب الاستدعاء التربوي</label>
                <textarea rows={3} value={sumReason} onChange={(e) => setSumReason(e.target.value)} style={{ ...inputStyle, height: "auto", padding: "10px 12px", resize: "vertical" }} />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>تاريخ المقابلة</label>
                  <input required type="date" value={sumDate} onChange={(e) => setSumDate(e.target.value)} style={inputStyle} />
                </div>
                <div>
                  <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>الوقت المحدد</label>
                  <input required type="text" value={sumTime} onChange={(e) => setSumTime(e.target.value)} style={inputStyle} />
                </div>
              </div>
              <div style={{ fontSize: 11, color: "var(--danger)", fontWeight: 700, display: "flex", alignItems: "center", gap: 6 }}>
                <AlertTriangle size={14} /> سيتم بث إشعار حاسم وتنبيه صوتي فوري في هاتف ولي الأمر!
              </div>
            </div>
          )}

          {type === "substitute" && (
            <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>اختيار معلم الانتظار المتاح</label>
                <select value={subTeacherId} onChange={(e) => setSubTeacherId(e.target.value)} style={inputStyle}>
                  {teachers.filter((t) => t.activeStatus === "active").map((t) => (
                    <option key={t.id} value={t.id}>{t.name} ({t.specialization} — {t.lessonsThisWeek} حصة)</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, color: "var(--text-light)", display: "block", marginBottom: 6 }}>رقم الحصة المطلوب تغطيتها</label>
                <select value={subPeriod} onChange={(e) => setSubPeriod(Number(e.target.value))} style={inputStyle}>
                  {[1, 2, 3, 4, 5, 6, 7].map((p) => <option key={p} value={p}>الحصة {p}</option>)}
                </select>
              </div>
            </div>
          )}

          {/* Modal Actions */}
          <div style={{ display: "flex", gap: 10, marginTop: 20 }}>
            <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
              <CheckCircle size={15} />
              {type === "add_student" && "تسجيل الطالب وربطه بولي الأمر"}
              {type === "add_teacher" && "إضافة المعلم للنظام"}
              {type === "recommendation" && "حفظ وإرسال الخطة لولي الأمر"}
              {type === "summons" && "إصدار بطاقة الاستدعاء"}
              {type === "substitute" && "إرسال التكليف للمعلم"}
            </button>
            <button type="button" onClick={onClose} className="btn btn-ghost" style={{ justifyContent: "center" }}>
              إلغاء
            </button>
          </div>
        </form>
      </div>
      <style jsx global>{`
        @keyframes modalZoom {
          from { transform: scale(0.95); opacity: 0; }
          to { transform: scale(1); opacity: 1; }
        }
      `}</style>
    </div>
  );
}

const inputStyle: React.CSSProperties = {
  width: "100%",
  height: 40,
  border: "1px solid var(--border)",
  borderRadius: "var(--radius)",
  padding: "0 12px",
  fontFamily: "Cairo, sans-serif",
  fontSize: 13,
  outline: "none",
  background: "var(--bg-page)",
  color: "var(--text-dark)",
};
