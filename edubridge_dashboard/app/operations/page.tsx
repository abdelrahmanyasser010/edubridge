"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { Shield, FileCheck, Phone, Clock, UserCheck, AlertTriangle, CheckCircle, XCircle, RefreshCw } from "lucide-react";

export default function OperationsPage() {
  const {
    medicalExcuses, leavePermits, parentSummons, substitutes, teachers,
    approveMedicalExcuse, rejectMedicalExcuse, approveLeavePermit, assignSubstitute, showToast
  } = useDashboard();

  const [activeTab, setActiveTab] = useState<"permits" | "excuses" | "summons" | "substitutes">("permits");
  const [selectedAbsentId, setSelectedAbsentId] = useState<string>(teachers[0]?.id || "");
  const [selectedSubId, setSelectedSubId] = useState<string>(teachers[1]?.id || "");
  const [subPeriod, setSubPeriod] = useState<number>(3);
  const [subSection, setSubSection] = useState<string>("الصف الخامس / شعبة أ");

  const pendingExcusesCount = medicalExcuses.filter(e => e.status === "pending").length;
  const pendingPermitsCount = leavePermits.filter(p => p.status === "waiting_gate").length;
  const activeSummonsCount = parentSummons.filter(s => s.status === "scheduled").length;

  const handleCreateSub = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedAbsentId || !selectedSubId) return;
    assignSubstitute(selectedAbsentId, selectedSubId, subSection, subPeriod);
    showToast("تم تكليف معلم الانتظار 🔄", "تم إرسال تكليف تغطية الحصة إلى تطبيق المعلم بنجاح.", "success");
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="الطلبات والأعذار اليومية واستدعاءات أولياء الأمور" subtitle="معالجة طلبات الاستئذان، تدقيق التقارير الطبية، تنظيم المقابلات التربوية، وإسناد حصص الانتظار" />
        <main className="page-body">

          {/* KPI Banner */}
          <div className="kpi-grid" style={{ marginBottom: 20 }}>
            {[
              { label: "طلبات استئذان بانتظار الموافقة", value: `${pendingPermitsCount} طلاب`, icon: <UserCheck size={22} />, bg: "#EFF6FF", color: "#176B9A", tab: "permits" },
              { label: "تقارير طبية تحتاج تدقيق", value: `${pendingExcusesCount} تقارير`, icon: <FileCheck size={22} />, bg: "#FFF7ED", color: "#F59E0B", tab: "excuses" },
              { label: "استدعاءات ومقابلات مجدولة", value: `${activeSummonsCount} مواعيد`, icon: <Phone size={22} />, bg: "#FEF2F2", color: "#EF4444", tab: "summons" },
              { label: "تكليفات حصص الانتظار اليوم", value: `${substitutes.length} حصص`, icon: <RefreshCw size={22} />, bg: "#F0FDF4", color: "#16A34A", tab: "substitutes" },
            ].map((stat, i) => (
              <div
                key={i}
                className="kpi-card"
                onClick={() => setActiveTab(stat.tab as any)}
                style={{ cursor: "pointer", border: activeTab === stat.tab ? "2px solid var(--primary)" : "1px solid var(--border)" }}
              >
                <div className="kpi-icon" style={{ background: stat.bg, color: stat.color }}>{stat.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 20 }}>{stat.value}</div>
                  <div className="kpi-label">{stat.label}</div>
                </div>
              </div>
            ))}
          </div>

          {/* Navigation Tabs */}
          <div style={{ display: "flex", gap: 10, marginBottom: 20, borderBottom: "1px solid var(--border)", paddingBottom: 12, flexWrap: "wrap" }}>
            {[
              { id: "permits", label: `🚪 أذونات الخروج واستئذان الطلاب (${pendingPermitsCount})` },
              { id: "excuses", label: `🏥 تدقيق التقارير الطبية (منصة صحتي) (${pendingExcusesCount})` },
              { id: "summons", label: `📜 استدعاءات ومقابلات أولياء الأمور (${activeSummonsCount})` },
              { id: "substitutes", label: `🔄 جدول حصص الانتظار وتغطية الغياب (${substitutes.length})` },
            ].map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`btn ${activeTab === tab.id ? "btn-primary" : "btn-ghost"}`}
                style={{ fontSize: 13 }}
              >
                {tab.label}
              </button>
            ))}
          </div>

          {/* TAB 1: Leave Permits */}
          {activeTab === "permits" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">طلبات الاستئذان والمغادرة المبكرة</div>
                  <div className="card-subtitle">الطلبات المرفوعة من أولياء الأمور عبر التطبيق لاستئذان الطلاب قبل نهاية الدوام</div>
                </div>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {leavePermits.map((permit, idx) => (
                  <div key={permit.id} className="feed-item" style={{ borderBottom: idx < leavePermits.length - 1 ? "1px solid var(--border-light)" : "none", alignItems: "center" }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
                        <span style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)" }}>{permit.studentName}</span>
                        <span className="badge badge-gray">{permit.sectionName}</span>
                        <span className={`badge ${permit.status === "waiting_gate" ? "badge-orange" : "badge-green"}`}>
                          <span className="dot" />{permit.status === "waiting_gate" ? "بانتظار موافقة الإدارة" : "تم السماح بالمغادرة"}
                        </span>
                      </div>
                      <div style={{ fontSize: 13, color: "var(--text-light)", marginBottom: 6 }}>
                        السبب: <strong>{permit.reason}</strong>
                      </div>
                      <div style={{ display: "flex", gap: 16, fontSize: 11.5, color: "var(--text-muted)" }}>
                        <span>👤 مقدم الطلب: <strong>{permit.parentName}</strong></span>
                        <span>🚗 طريقة الاستلام: <strong>{permit.pickupType}</strong></span>
                        <span>⏱ وقت الاستئذان: {permit.requestTime}</span>
                        <span>🔑 رقم العبور للأمن: <strong style={{ fontFamily: "monospace", color: "var(--primary)" }}>{permit.gatePassCode}</strong></span>
                      </div>
                    </div>
                    {permit.status === "waiting_gate" && (
                      <button onClick={() => approveLeavePermit(permit.id)} className="btn btn-green btn-sm" style={{ flexShrink: 0 }}>
                        <CheckCircle size={14} /> الموافقة وإبلاغ حارس الأمن
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 2: Sehatty Medical Excuses */}
          {activeTab === "excuses" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">تدقيق التقارير الطبية (منصة صحتي)</div>
                  <div className="card-subtitle">مراجعة التقارير المرفوعة لتبرير الغياب وحماية درجات الطالب من الحرمان</div>
                </div>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {medicalExcuses.map((exc, idx) => (
                  <div key={exc.id} className="feed-item" style={{ borderBottom: idx < medicalExcuses.length - 1 ? "1px solid var(--border-light)" : "none", alignItems: "center" }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
                        <span style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)" }}>{exc.studentName}</span>
                        <span className="badge badge-gray">{exc.sectionName}</span>
                        <span className={`badge ${exc.status === "pending" ? "badge-orange" : exc.status === "approved" ? "badge-green" : "badge-red"}`}>
                          <span className="dot" />{exc.status === "pending" ? "قيد المراجعة والتدقيق" : exc.status === "approved" ? "تم قبول العذر رسمياً" : "مرفوض"}
                        </span>
                      </div>
                      <div style={{ fontSize: 13, color: "var(--text-light)", marginBottom: 6 }}>
                        الجهة المعالجة: <strong>{exc.hospitalName}</strong> — التشخيص: <strong style={{ color: "var(--text-dark)" }}>{exc.reason}</strong>
                      </div>
                      <div style={{ display: "flex", gap: 16, fontSize: 11.5, color: "var(--text-muted)" }}>
                        <span>📅 تاريخ الغياب: {exc.absenceDate}</span>
                        <span>📤 مرفوع بواسطة: {exc.submittedBy}</span>
                        <span style={{ color: "var(--green)", fontWeight: 700 }}>✓ تم التحقق من الباركود (صحتي)</span>
                      </div>
                    </div>
                    {exc.status === "pending" && (
                      <div style={{ display: "flex", gap: 8, flexShrink: 0 }}>
                        <button onClick={() => approveMedicalExcuse(exc.id)} className="btn btn-green btn-sm">
                          <CheckCircle size={14} /> اعتماد العذر
                        </button>
                        <button onClick={() => rejectMedicalExcuse(exc.id)} className="btn btn-sm" style={{ background: "#FEF2F2", color: "#DC2626", border: "1px solid #FECACA" }}>
                          <XCircle size={14} /> رفض التقرير
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 3: Parent Summons */}
          {activeTab === "summons" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">استدعاءات ومقابلات أولياء الأمور</div>
                  <div className="card-subtitle">سجل المقابلات التربوية المجدولة مع أولياء الأمور لمعالجة المستوى الدراسي والسلوكي</div>
                </div>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {parentSummons.map((sum, idx) => (
                  <div key={sum.id} className="feed-item" style={{ borderBottom: idx < parentSummons.length - 1 ? "1px solid var(--border-light)" : "none", alignItems: "center" }}>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
                        <span style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)" }}>{sum.studentName}</span>
                        <span className="badge badge-gray">{sum.sectionName}</span>
                        <span className="badge badge-red"><span className="dot" />موعد مقابلة مجدول</span>
                      </div>
                      <div style={{ fontSize: 13, color: "var(--text-light)", marginBottom: 6 }}>
                        سبب الاستدعاء: <strong style={{ color: "var(--danger)" }}>{sum.reason}</strong>
                      </div>
                      <div style={{ display: "flex", gap: 16, fontSize: 11.5, color: "var(--text-muted)", flexWrap: "wrap" }}>
                        <span>👤 ولي الأمر: <strong>{sum.parentName}</strong> ({sum.parentPhone})</span>
                        <span>📅 موعد المقابلة: <strong>{sum.meetingDate}</strong> — {sum.meetingTime}</span>
                        <span>👨‍💼 المشرف المسؤول: {sum.supervisorName}</span>
                      </div>
                    </div>
                    <button
                      onClick={() => showToast("تذكير بموعد المقابلة 📱", `تم إرسال إشعار تذكير إلى هاتف ولي الأمر ${sum.parentName} بموعد المقابلة غداً.`, "info")}
                      className="btn btn-outline btn-sm"
                    >
                      <Phone size={13} /> إرسال تذكير للهاتف
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 4: Standby Teachers & Substitution (حصص الانتظار) */}
          {activeTab === "substitutes" && (
            <div className="grid-2">
              <div className="card">
                <div className="card-header">
                  <div>
                    <div className="card-title">سجل تكليفات حصص الانتظار اليوم</div>
                    <div className="card-subtitle">المعلمون المكلفون بتغطية حصص الزملاء الغائبين أو المجازين ({substitutes.length} تكليفات)</div>
                  </div>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                  {substitutes.map((sub, idx) => (
                    <div key={sub.id} className="feed-item" style={{ borderBottom: idx < substitutes.length - 1 ? "1px solid var(--border-light)" : "none" }}>
                      <div style={{ flex: 1 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4 }}>
                          <span style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{sub.substituteTeacherName}</span>
                          <span className="badge badge-red" style={{ fontSize: 11 }}>معلم انتظار (تغطية)</span>
                        </div>
                        <div style={{ fontSize: 12.5, color: "var(--text-light)", marginBottom: 6 }}>
                          تغطية بدلاً من الأستاذ/ة: <strong style={{ color: "var(--danger)" }}>{sub.absentTeacherName}</strong>
                        </div>
                        <div style={{ display: "flex", gap: 14, fontSize: 11.5, color: "var(--text-muted)" }}>
                          <span>🏫 الفصل: <strong>{sub.sectionName}</strong></span>
                          <span>⏱ الحصة: <strong>{sub.period}</strong></span>
                          <span>📅 التاريخ: {sub.date}</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <form onSubmit={handleCreateSub} className="card">
                <div className="card-header">
                  <div className="card-title">إصدار تكليف حصة انتظار جديد</div>
                  <span className="badge badge-blue">⚡ تصل للمعلم في التطبيق</span>
                </div>
                <div className="card-body">
                  <div className="form-group">
                    <label className="form-label">المعلم الغائب / المجاز</label>
                    <select className="form-select" value={selectedAbsentId} onChange={(e) => setSelectedAbsentId(e.target.value)}>
                      {teachers.map(t => <option key={t.id} value={t.id}>{t.name} ({t.specialization})</option>)}
                    </select>
                  </div>
                  <div className="form-group">
                    <label className="form-label">معلم الانتظار (البديل المكلف)</label>
                    <select className="form-select" value={selectedSubId} onChange={(e) => setSelectedSubId(e.target.value)}>
                      {teachers.map(t => <option key={t.id} value={t.id}>{t.name} (النصاب الحالي: {t.lessonsThisWeek} حصة)</option>)}
                    </select>
                  </div>
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12, marginBottom: 16 }}>
                    <div className="form-group" style={{ marginBottom: 0 }}>
                      <label className="form-label">الفصل الدراسي</label>
                      <select className="form-select" value={subSection} onChange={(e) => setSubSection(e.target.value)}>
                        <option>الصف الخامس / شعبة أ</option>
                        <option>الصف الخامس / شعبة ب</option>
                        <option>الصف السادس / شعبة أ</option>
                        <option>الصف السادس / شعبة ب</option>
                      </select>
                    </div>
                    <div className="form-group" style={{ marginBottom: 0 }}>
                      <label className="form-label">رقم الحصة</label>
                      <select className="form-select" value={subPeriod} onChange={(e) => setSubPeriod(Number(e.target.value))}>
                        {[1, 2, 3, 4, 5, 6, 7].map(p => <option key={p} value={p}>الحصة {p}</option>)}
                      </select>
                    </div>
                  </div>
                  <button type="submit" className="btn btn-primary" style={{ width: "100%", justifyContent: "center" }}>
                    <RefreshCw size={15} /> إرسال التكليف لمعلم الانتظار الآن
                  </button>
                </div>
              </form>
            </div>
          )}

        </main>
        <Footer />
      </div>
    </div>
  );
}
