"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard, systemRoles } from "@/context/DashboardContext";
import {
  Settings, Shield, Users, Lock, CheckCircle, Save,
  Plus, RefreshCw, AlertTriangle, Key, Smartphone, Globe
} from "lucide-react";

const permissionNames: Record<string, string> = {
  can_manage_behavior: "اعتماد وتوجيه الملاحظات السلوكية (لائحة السلوك)",
  can_manage_attendance: "رصد الغياب وإرسال إنذارات الحرمان الآلي",
  can_manage_academic: "إدارة الكوادر، الأنصِبة وإسناد حصص الاحتياط",
  can_manage_grades: "تدقيق واعتماد درجات الطلاب ونشر الشهادات",
  can_manage_operations: "إدارة أذونات الخروج وتدقيق أعذار منصة صحتي",
  can_manage_fleet: "تتبع أسطول الحافلات وإرسال التنبيهات المرورية",
  can_manage_rbac: "إدارة الحسابات وتعديل مصفوفة الصلاحيات (RBAC)",
  can_send_broadcasts: "بث التعاميم العامة والرسائل الفورية للتطبيقات",
};

export default function SettingsPage() {
  const {
    currentRole, permissionMatrix, updatePermission, adminAccounts,
    addAdminAccount, updateAdminRole, showToast,
    deviceSessions, apiStatus, apiError, refreshDashboardData,
    schoolSettings, schoolIntegrations, auditLogs, rbacRoles, rbacPermissions, rbacMatrix,
    saveSchoolSettings, testSchoolIntegration, updateAdminStatus, createDashboardRole,
  } = useDashboard();

  const [activeTab, setActiveTab] = useState<"rbac" | "accounts" | "api">("rbac");
  const [selectedRoleKey, setSelectedRoleKey] = useState<string>("student_affairs");

  // Form state for new admin account
  const [newAccName, setNewAccName] = useState("");
  const [newAccEmail, setNewAccEmail] = useState("");
  const [newAccRole, setNewAccRole] = useState<string>("student_affairs");
  const [showAddModal, setShowAddModal] = useState(false);
  const [newRoleKey, setNewRoleKey] = useState("");
  const [newRoleName, setNewRoleName] = useState("");

  const handleSaveMatrix = () => {
    void refreshDashboardData();
    showToast("مصفوفة الصلاحيات", "كل مفتاح صلاحية يُحفظ مباشرة في الخادم. تم طلب إعادة مزامنة المصفوفة الآن.", "info");
  };

  const handleCreateAccount = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAccName || !newAccEmail) return;
    const targetRole = systemRoles.find(r => r.key === newAccRole);
    addAdminAccount({
      name: newAccName,
      email: newAccEmail,
      phone: "",
      roleKey: newAccRole as any,
      roleLabel: targetRole?.label || "مشرف إداري",
      status: "active",
    });
    setNewAccName("");
    setNewAccEmail("");
    setShowAddModal(false);
  };

  const displayRoles = rbacRoles.length
    ? rbacRoles.map((role) => ({
        key: role.key,
        label: role.label ?? role.key,
        short: role.key,
        description: role.is_system ? "System role from backend RBAC." : "Custom role from backend RBAC.",
      }))
    : systemRoles;
  const currentRoleObj = displayRoles.find(r => r.key === selectedRoleKey) || displayRoles[1] || systemRoles[1];
  const rolePerms = permissionMatrix[selectedRoleKey] || {};

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="إعدادات النظام وإدارة صلاحيات الوصول (RBAC)"
          subtitle="تخصيص الأدوار، تقييد صلاحيات المشرفين، ومزامنة إعدادات الربط مع تطبيقات أولياء الأمور والمعلمين"
        />
        <main className="page-body">

          {/* Banner Explanation */}
          <div style={{
            background: "var(--bg-surface)", border: "1px solid var(--border)",
            borderRadius: "var(--radius)", padding: "16px 20px", marginBottom: 20,
            display: "flex", gap: 16, alignItems: "center", flexWrap: "wrap",
          }}>
            <Shield size={28} color="var(--primary)" style={{ flexShrink: 0 }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 4 }}>
                نظام حوكمة الصلاحيات والأدوار المتقدم (Role-Based Access Control — RBAC)
              </div>
              <div style={{ fontSize: 12.5, color: "var(--text-light)", lineHeight: 1.6 }}>
                في النظام الفعلي، يقوم <strong>مدير المدرسة (School Admin)</strong> بإنشاء حسابات مخصصة لوكلاء المدرسة والمشرفين ومنحهم أدواراً محددة. أي تغيير في المصفوفة أدناه ينعكس فوراً على الأزرار والعمليات المتاحة للمستخدم عند تسجيل دخوله.
              </div>
            </div>
            <div style={{ background: "var(--primary-50)", padding: "8px 14px", borderRadius: "var(--radius)", textAlign: "center" }}>
              <div style={{ fontSize: 11, color: "var(--primary)", fontWeight: 600 }}>الدور النشط الآن في الجلسة:</div>
              <div style={{ fontSize: 13, fontWeight: 800, color: "var(--primary)" }}>{currentRole.label}</div>
            </div>
          </div>

          {/* Navigation Tabs */}
          <div style={{ display: "flex", gap: 10, marginBottom: 20, borderBottom: "1px solid var(--border)", paddingBottom: 12, flexWrap: "wrap" }}>
            {[
              { id: "rbac", label: "🛡️ مصفوفة الصلاحيات وتخصيص الأدوار (RBAC)", icon: Shield },
              { id: "accounts", label: "👥 إدارة الحسابات والكوادر الإدارية", icon: Users },
              { id: "api", label: "🔌 إعدادات المزامنة وتكامل منصة صحتي ونور", icon: Globe },
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

          {/* TAB 1: RBAC Permission Matrix */}
          {activeTab === "rbac" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">تخصيص صلاحيات العمليات للأدوار الإدارية</div>
                  <div className="card-subtitle">اختر الدور الإداري وقم بتفعيل أو تعطيل الصلاحيات الخاصة به</div>
                </div>
                <button onClick={handleSaveMatrix} className="btn btn-green btn-sm">
                  <Save size={14} /> حفظ اعتماد المصفوفة
                </button>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr auto", gap: 10, marginBottom: 16 }}>
                <input className="form-input" value={newRoleName} onChange={(event) => setNewRoleName(event.target.value)} placeholder="اسم الدور الجديد" />
                <input className="form-input" value={newRoleKey} onChange={(event) => setNewRoleKey(event.target.value)} placeholder="role_key مثل academic_supervisor" />
                <button
                  className="btn btn-outline btn-sm"
                  onClick={() => {
                    void createDashboardRole(newRoleKey, newRoleName);
                    setNewRoleKey("");
                    setNewRoleName("");
                  }}
                >
                  <Plus size={14} /> إنشاء Role
                </button>
              </div>

              <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 16 }}>
                <span className="badge badge-blue">Roles: {rbacRoles.length || systemRoles.length}</span>
                <span className="badge badge-green">Permissions: {rbacPermissions.length || Object.keys(permissionNames).length}</span>
                <span className={`badge ${rbacMatrix ? "badge-green" : "badge-gray"}`}>{rbacMatrix ? "Live matrix" : "Local matrix"}</span>
              </div>

              {/* Role selector buttons */}
              <div style={{ display: "flex", gap: 10, marginBottom: 20, flexWrap: "wrap" }}>
                {displayRoles.map((r) => (
                  <button
                    key={r.key}
                    onClick={() => setSelectedRoleKey(r.key)}
                    className={`btn ${selectedRoleKey === r.key ? "btn-primary" : "btn-ghost"} btn-sm`}
                    style={{
                      border: selectedRoleKey === r.key ? "none" : "1px solid var(--border)",
                      background: selectedRoleKey === r.key ? "var(--primary)" : "var(--bg-page)",
                    }}
                  >
                    <Key size={13} /> {r.label}
                  </button>
                ))}
              </div>

              {/* Current Role details banner */}
              <div style={{ background: "var(--bg-page)", padding: "14px 18px", borderRadius: "var(--radius-sm)", marginBottom: 20, borderRight: "4px solid var(--primary)" }}>
                <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 4 }}>
                  صلاحيات دور: {currentRoleObj.label} ({currentRoleObj.short})
                </div>
                <div style={{ fontSize: 12.5, color: "var(--text-light)" }}>
                  {currentRoleObj.description}
                </div>
              </div>

              {/* Permissions Checkbox List */}
              <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: 12 }}>
                {Object.entries(permissionNames).map(([permKey, permLabel]) => {
                  const isChecked = !!rolePerms[permKey as keyof typeof rolePerms];
                  const isSchoolAdmin = selectedRoleKey === "school_admin";
                  return (
                    <div
                      key={permKey}
                      onClick={() => !isSchoolAdmin && updatePermission(selectedRoleKey, permKey, !isChecked)}
                      style={{
                        padding: "14px 18px",
                        border: "1px solid var(--border)",
                        borderRadius: "var(--radius)",
                        background: isChecked ? "rgba(23, 107, 154, 0.03)" : "var(--bg-surface)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        cursor: isSchoolAdmin ? "not-allowed" : "pointer",
                        transition: "all 0.15s",
                      }}
                    >
                      <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                        <div style={{
                          width: 22, height: 22, borderRadius: 6,
                          background: isChecked ? "var(--primary)" : "var(--bg-page)",
                          border: `2px solid ${isChecked ? "var(--primary)" : "var(--border)"}`,
                          display: "flex", alignItems: "center", justifyContent: "center",
                          color: "white", transition: "all 0.15s",
                        }}>
                          {isChecked && <CheckCircle size={14} />}
                        </div>
                        <div>
                          <div style={{ fontWeight: 700, fontSize: 13.5, color: isChecked ? "var(--text-dark)" : "var(--text-muted)" }}>
                            {permLabel}
                          </div>
                          <div style={{ fontSize: 11, color: "var(--text-muted)", fontFamily: "monospace" }}>{permKey}</div>
                        </div>
                      </div>
                      <span className={`badge ${isChecked ? "badge-green" : "badge-gray"}`}>
                        {isChecked ? "مفعل ومتاح" : "محظور (للقراءة فقط)"}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* TAB 2: Admin Accounts & Staff Management */}
          {activeTab === "accounts" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">إدارة حسابات المشرفين والكوادر الإدارية</div>
                  <div className="card-subtitle">ربط الموظفين بالأدوار الإدارية لتفعيل الدخول الآمن للوحة التحكم ({adminAccounts.length} حسابات)</div>
                </div>
                <button onClick={() => setShowAddModal(true)} className="btn btn-primary btn-sm">
                  <Plus size={14} /> إضافة حساب إداري جديد
                </button>
              </div>

              <div className="data-table-wrap">
                <table className="data-table">
                  <thead>
                    <tr>
                      <th>الاسم وبيانات الاتصال</th>
                      <th>الدور الإداري المخصص (RBAC)</th>
                      <th>حالة الحساب</th>
                      <th>آخر نشاط وجلسة</th>
                      <th>تعديل الدور والصلاحية</th>
                    </tr>
                  </thead>
                  <tbody>
                    {adminAccounts.map((acc) => (
                      <tr key={acc.id}>
                        <td>
                          <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{acc.name}</div>
                          <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{acc.email} — {acc.phone}</div>
                        </td>
                        <td>
                          <span className="badge badge-blue" style={{ fontSize: 12, fontWeight: 700 }}>
                            <Shield size={12} style={{ display: "inline", verticalAlign: "middle" }} /> {acc.roleLabel}
                          </span>
                        </td>
                        <td>
                          <span className={`badge ${acc.status === "active" ? "badge-green" : "badge-red"}`}>
                            <span className="dot" />{acc.status === "active" ? "نشط ومصرح" : "معطل"}
                          </span>
                          {acc.roleKey !== "school_admin" && (
                            <button
                              type="button"
                              className="btn btn-ghost btn-sm"
                              style={{ marginTop: 6 }}
                              onClick={() => void updateAdminStatus(acc.id, acc.status === "active" ? "suspended" : "active")}
                            >
                              {acc.status === "active" ? "تعطيل" : "تفعيل"}
                            </button>
                          )}
                        </td>
                        <td style={{ fontSize: 12, color: "var(--text-light)" }}>{acc.lastLogin}</td>
                        <td>
                          <select
                            value={acc.roleKey}
                            onChange={(e) => updateAdminRole(acc.id, e.target.value as any)}
                            disabled={acc.roleKey === "school_admin"}
                            style={{
                              height: 34, border: "1px solid var(--border)", borderRadius: "var(--radius-sm)",
                              padding: "0 10px", fontFamily: "Cairo, sans-serif", fontSize: 12, outline: "none",
                              background: "var(--bg-page)", color: "var(--text-dark)", cursor: acc.roleKey === "school_admin" ? "not-allowed" : "pointer",
                            }}
                          >
                            {systemRoles.map((r) => (
                              <option key={r.key} value={r.key}>{r.label}</option>
                            ))}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 3: API Sync & Gateway Settings */}
          {activeTab === "api" && (
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">إعدادات المزامنة وتكامل البنية التحتية المدرسية</div>
                  <div className="card-subtitle">الربط اللحظي مع تطبيقات Flutter الهاتفية وبوابات الوزارة الخارجية</div>
                </div>
                <span className={`badge ${apiStatus === "live" ? "badge-green" : apiStatus === "error" ? "badge-red" : "badge-gray"}`}><span className="dot" />{apiStatus === "live" ? "API متصل" : apiStatus === "error" ? "API غير متاح" : "جاري التحقق"}</span>
              </div>
              <div className="feed-item" style={{ alignItems: "flex-start", justifyContent: "space-between", marginBottom: 16 }}>
                <div style={{ flex: 1 }}>
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 6 }}>
                    <Smartphone size={16} color="var(--primary)" />
                    <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)" }}>Device sessions</div>
                    <span className={`badge ${apiStatus === "live" ? "badge-green" : apiStatus === "error" ? "badge-orange" : "badge-gray"}`}>
                      {apiStatus}
                    </span>
                  </div>
                  <div style={{ fontSize: 12.5, color: "var(--text-light)", marginBottom: 10 }}>
                    Read from GET /auth/device-sessions when a dashboard token is available.
                  </div>
                  <div style={{ display: "grid", gap: 8 }}>
                    {deviceSessions.length > 0 ? (
                      deviceSessions.slice(0, 4).map((session) => (
                        <div key={session.id} style={{ display: "flex", justifyContent: "space-between", gap: 12, fontSize: 12, color: "var(--text-dark)" }}>
                          <span>{session.device_name || "Dashboard Web"}</span>
                          <span style={{ color: "var(--text-light)" }}>{session.last_used_at || session.expires_at || session.id}</span>
                        </div>
                      ))
                    ) : (
                      <div style={{ fontSize: 12, color: "var(--text-light)" }}>
                        No live device sessions loaded yet.
                      </div>
                    )}
                  </div>
                  {apiError && <div style={{ marginTop: 8, fontSize: 12, color: "#B45309" }}>{apiError}</div>}
                </div>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => void refreshDashboardData()}>
                  <RefreshCw size={14} /> Refresh
                </button>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 16 }}>
                <div className="feed-item" style={{ alignItems: "flex-start" }}>
                  <Settings size={18} color="var(--primary)" />
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 6 }}>School settings</div>
                    <div style={{ fontSize: 12.5, color: "var(--text-light)", lineHeight: 1.8 }}>
                      <div>Name: {schoolSettings?.school?.name ?? "Not loaded"}</div>
                      <div>Timezone: {schoolSettings?.school?.timezone ?? "-"}</div>
                      <div>Locale/Currency: {schoolSettings?.school?.locale ?? "-"} / {schoolSettings?.school?.currency ?? "-"}</div>
                      <div>Push: {schoolSettings?.notifications?.push_enabled ? "enabled" : "disabled"}</div>
                    </div>
                    <button
                      type="button"
                      className="btn btn-outline btn-sm"
                      style={{ marginTop: 10 }}
                      onClick={() => void saveSchoolSettings({
                        notifications: {
                          ...schoolSettings?.notifications,
                          push_enabled: !schoolSettings?.notifications?.push_enabled,
                        },
                      })}
                    >
                      <Save size={14} /> Toggle push setting
                    </button>
                  </div>
                </div>

                <div className="feed-item" style={{ alignItems: "flex-start" }}>
                  <Key size={18} color="var(--primary)" />
                  <div style={{ flex: 1 }}>
                    <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 6 }}>Integrations</div>
                    <div style={{ display: "grid", gap: 8 }}>
                      {schoolIntegrations.slice(0, 4).map((integration) => (
                        <div key={integration.key} style={{ display: "flex", justifyContent: "space-between", gap: 10, alignItems: "center", fontSize: 12 }}>
                          <span>{integration.key} - {integration.status}</span>
                          <button className="btn btn-ghost btn-sm" type="button" onClick={() => void testSchoolIntegration(integration.key)}>
                            Test
                          </button>
                        </div>
                      ))}
                      {schoolIntegrations.length === 0 && <div style={{ color: "var(--text-light)", fontSize: 12 }}>No integrations loaded.</div>}
                    </div>
                  </div>
                </div>
              </div>

              <div className="feed-item" style={{ alignItems: "flex-start", marginBottom: 16 }}>
                <AlertTriangle size={18} color="#B45309" />
                <div style={{ flex: 1 }}>
                  <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 8 }}>Audit logs</div>
                  <div style={{ display: "grid", gap: 8 }}>
                    {auditLogs.slice(0, 6).map((log) => (
                      <div key={log.id} style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr auto", gap: 10, fontSize: 12, color: "var(--text-light)" }}>
                        <strong style={{ color: "var(--text-dark)" }}>{log.action}</strong>
                        <span>{log.summary ?? log.entity_type}</span>
                        <span>{log.created_at}</span>
                      </div>
                    ))}
                    {auditLogs.length === 0 && <div style={{ color: "var(--text-light)", fontSize: 12 }}>No audit logs loaded.</div>}
                  </div>
                </div>
              </div>

              <div style={{ fontSize: 12.5, color: "var(--text-light)", lineHeight: 1.7 }}>
                حالات التكامل المعروضة أعلاه تأتي من <code>/dashboard/school/integrations</code> فقط؛ لا يتم عرض حالات اتصال أو أرصدة SMS افتراضية.
              </div>
            </div>
          )}

        </main>
        <Footer />
      </div>

      {/* Create Account Modal */}
      {showAddModal && (
        <div style={{
          position: "fixed", inset: 0, background: "rgba(18, 60, 86, 0.4)", backdropFilter: "blur(4px)",
          zIndex: 1000, display: "flex", alignItems: "center", justifyContent: "center", padding: 20,
        }}>
          <div style={{
            background: "var(--bg-surface)", border: "1px solid var(--border)", borderRadius: "var(--radius-xl)",
            width: "100%", maxWidth: 480, padding: 24, direction: "rtl", boxShadow: "0 20px 50px rgba(0,0,0,0.15)",
          }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800 }}>إدراج حساب موظف إداري جديد</div>
              <button onClick={() => setShowAddModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18 }}>✕</button>
            </div>
            <form onSubmit={handleCreateAccount} style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, display: "block", marginBottom: 6 }}>اسم الموظف الثلاثي</label>
                <input required type="text" placeholder="مثال: الأستاذ عمر الغامدي..." value={newAccName} onChange={(e) => setNewAccName(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, display: "block", marginBottom: 6 }}>البريد الإلكتروني الرسمي</label>
                <input required type="email" placeholder="o.ghamdi@edubridge.sa" value={newAccEmail} onChange={(e) => setNewAccEmail(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, display: "block", marginBottom: 6 }}>تخصيص الدور والصلاحية (RBAC)</label>
                <select value={newAccRole} onChange={(e) => setNewAccRole(e.target.value as any)} style={inputStyle}>
                  {systemRoles.map(r => <option key={r.key} value={r.key}>{r.label} ({r.short})</option>)}
                </select>
              </div>
              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <CheckCircle size={15} /> إنشاء الحساب ومنح الصلاحية
                </button>
                <button type="button" onClick={() => setShowAddModal(false)} className="btn btn-ghost">إلغاء</button>
              </div>
            </form>
          </div>
        </div>
      )}
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
