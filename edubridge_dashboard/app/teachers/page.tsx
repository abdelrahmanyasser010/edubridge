"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import OperationsModal from "@/components/OperationsModal";
import { useDashboard } from "@/context/DashboardContext";
import { Plus, Search, Users, Clock, CheckCircle, AlertCircle, Phone, Mail } from "lucide-react";

export default function TeachersPage() {
  const { teachers, apiStatus } = useDashboard();
  const [searchTerm, setSearchTerm] = useState("");
  const [specFilter, setSpecFilter] = useState("all");
  const [modalType, setModalType] = useState<"add_teacher" | "substitute" | null>(null);
  const [targetId, setTargetId] = useState<string | undefined>(undefined);

  const filteredTeachers = teachers.filter((t) => {
    const matchesSearch = t.name.includes(searchTerm) || t.email.includes(searchTerm) || t.specialization.includes(searchTerm);
    const matchesSpec = specFilter === "all" || t.specialization === specFilter;
    return matchesSearch && matchesSpec;
  });

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header title="شؤون الكوادر والأنصِبة الأكاديمية المتقدمة" subtitle="إدارة أعضاء هيئة التدريس، متابعة مؤشرات الأداء (KPIs)، وإسناد تغطية حصص الاحتياط الفورية" />
        <main className="page-body">

          {/* Controls Bar */}
          <div className="card" style={{ marginBottom: 20, padding: "16px 20px" }}>
            <div style={{ display: "flex", gap: 14, alignItems: "center", flexWrap: "wrap", justifyContent: "space-between" }}>
              <div style={{ display: "flex", gap: 10, flex: 1, minWidth: 280 }}>
                <div style={{ position: "relative", flex: 1 }}>
                  <input
                    type="text"
                    placeholder="ابحث باسم المعلم، التخصص الأكاديمي، أو البريد الإلكتروني..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    style={{
                      width: "100%", height: 40, border: "1px solid var(--border)",
                      borderRadius: "var(--radius)", padding: "0 12px 0 36px",
                      fontFamily: "Cairo, sans-serif", fontSize: 13, outline: "none",
                      background: "var(--bg-page)", color: "var(--text-dark)",
                    }}
                  />
                  <Search size={16} color="var(--text-muted)" style={{ position: "absolute", left: 12, top: 12 }} />
                </div>
                <select
                  value={specFilter}
                  onChange={(e) => setSpecFilter(e.target.value)}
                  style={{
                    height: 40, border: "1px solid var(--border)", borderRadius: "var(--radius)",
                    padding: "0 12px", fontFamily: "Cairo, sans-serif", fontSize: 13, outline: "none",
                    background: "var(--bg-page)", color: "var(--text-dark)", cursor: "pointer",
                  }}
                >
                  <option value="all">جميع التخصصات</option>
                  <option value="الرياضيات">الرياضيات</option>
                  <option value="اللغة العربية">اللغة العربية</option>
                  <option value="العلوم">العلوم</option>
                  <option value="التربية الاجتماعية">التربية الاجتماعية</option>
                </select>
              </div>
              <button onClick={() => setModalType("add_teacher")} className="btn btn-primary">
                <Plus size={16} /> إضافة كادر تعليمي وتعيين التخصص
              </button>
            </div>
          </div>

          {/* Teachers Table */}
          <div className="card">
            <div className="card-header">
              <div>
                <div className="card-title">دليل الكوادر التعليمية ومؤشرات الالتزام</div>
                <div className="card-subtitle">يتم رصد الحضور وإرسال الواجبات والملاحظات من تطبيق المعلم مباشرة ({filteredTeachers.length} معلم)</div>
              </div>
            </div>
            <div className="data-table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>المعلم وبيانات الاتصال</th>
                    <th>التخصص الأكاديمي</th>
                    <th>النصاب الأسبوعي</th>
                    <th>مؤشر الالتزام (KPI)</th>
                    <th>الحالة الوظيفية</th>
                    <th>إدارة التغطية والاحتياط</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredTeachers.map((t) => (
                    <tr key={t.id}>
                      <td>
                        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                          <div style={{
                            width: 36, height: 36, borderRadius: "50%",
                            background: t.avatarColor + "20", color: t.avatarColor,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 13, fontWeight: 800, flexShrink: 0,
                          }}>{t.avatarInitials}</div>
                          <div>
                            <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{t.name}</div>
                            <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{t.email}</div>
                          </div>
                        </div>
                      </td>
                      <td><span className="badge badge-gray">{t.specialization}</span></td>
                      <td>
                        {apiStatus === "live" ? <span style={{ color: "var(--text-muted)" }}>—</span> : <span style={{ fontWeight: 700, fontSize: 13 }}>{t.lessonsThisWeek} حصة / أسبوع</span>}
                      </td>
                      <td>
                        {apiStatus === "live" ? (
                          <span className="badge badge-gray">KPI غير متاح من API</span>
                        ) : (
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            <div className="progress-bar" style={{ width: 50 }}>
                              <div className="progress-fill" style={{ width: `${t.kpiScore}%`, background: t.kpiScore >= 95 ? "var(--green)" : t.kpiScore >= 85 ? "var(--warning)" : "var(--danger)" }} />
                            </div>
                            <span style={{ fontWeight: 800, color: t.kpiScore >= 95 ? "var(--green)" : t.kpiScore >= 85 ? "var(--warning)" : "var(--danger)" }}>{t.kpiScore}%</span>
                          </div>
                        )}
                      </td>
                      <td>
                        <span className={`badge ${t.activeStatus === "active" ? "badge-green" : "badge-orange"}`}>
                          <span className="dot" />{t.activeStatus === "active" ? "نشط ويداوم" : "إجازة رسمية"}
                        </span>
                      </td>
                      <td>
                        {t.activeStatus === "on_leave" ? (
                          <button
                            onClick={() => { setTargetId(t.id); setModalType("substitute"); }}
                            className="btn btn-green btn-sm"
                            style={{ background: "var(--green-50)", color: "var(--green)", border: "1px solid var(--green)" }}
                          >
                            <Clock size={14} /> إسناد تغطية احتياط 🔄
                          </button>
                        ) : (
                          <span style={{ fontSize: 11.5, color: "var(--text-muted)" }}>— متاح ومستقر</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </main>
        <Footer />
      </div>

      <OperationsModal
        type={modalType}
        targetId={targetId}
        onClose={() => { setModalType(null); setTargetId(undefined); }}
      />
    </div>
  );
}
