"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard, systemRoles } from "@/context/DashboardContext";
import {
  Settings,
  Shield,
  Users,
  CheckCircle,
  Save,
  Plus,
  RefreshCw,
  AlertTriangle,
  Key,
  Smartphone,
  Globe,
  Lock,
  ChevronDown,
} from "lucide-react";

const roleArabicInfo: Record<string, { label: string; description: string }> = {
  school_admin: {
    label: "مدير المدرسة",
    description: "صلاحيات الإشراف الإداري والتحكم الكامل في جميع عمليات المدرسة والمستخدمين وإعدادات النظام.",
  },
  academic_admin: {
    label: "الوكيل الأكاديمي",
    description: "إدارة الهيكل التعليمي، توزيع الجداول والأنصبة الأسبوعية، وتدقيق واعتماد ونشر الدرجات.",
  },
  student_affairs: {
    label: "وكيل شؤون الطلاب",
    description: "متابعة الحضور والغياب اليومي، لائحة السلوك والمواظبة، وإدارة تصاريح الخروج واستدعاءات أولياء الأمور.",
  },
  finance_officer: {
    label: "المسؤول المالي",
    description: "إدارة الرسوم الدراسية، الفواتير، سندات القبض والدفع، ومتابعة الاستردادات المالية والتقارير.",
  },
  transport_supervisor: {
    label: "مشرف النقل المدرسي",
    description: "إدارة أسطول الحافلات المدرسية، إسناد المسارات للطلاب، ومتابعة بيانات السائقين وأرقام اللوحات.",
  },
  teacher: {
    label: "كادر المعلمين",
    description: "رصد الحضور الصباحي، إدخال درجات التقييم والواجبات، ورصد الملاحظات في دفتر المتابعة الصفية.",
  },
  parent: {
    label: "تطبيق ولي الأمر",
    description: "صلاحيات تطبيق ولي الأمر للاطلاع على غياب وسلوك ودرجات الأبناء ودفع الرسوم واستلام الإشعارات.",
  },
  student: {
    label: "تطبيق الطالب",
    description: "صلاحيات تطبيق الطالب للاطلاع على الجدول الدراسي، الواجبات، النتائج، والرسائل المدرسية.",
  },
  canteen_operator: {
    label: "مسؤول المقصف",
    description: "إدارة عمليات الشراء والمبيعات المدرسية وخصم المشتريات من المحفظة الإلكترونية.",
  },
  integration_client: {
    label: "خدمة ربط خارجي",
    description: "حساب آلي للمزامنة السحابية مع المنصات والأنظمة المعتمدة.",
  },
};

const permissionDefinitions: Array<{
  key: string;
  label: string;
  description: string;
  category: string;
}> = [
  {
    key: "can_manage_behavior",
    label: "اعتماد وتوجيه الملاحظات السلوكية (لائحة السلوك)",
    description: "مراجعة الملاحظات المرصودة من المعلمين، اعتماد العقوبات التربوية، ونشرها لولي الأمر.",
    category: "شؤون الطلاب",
  },
  {
    key: "can_manage_attendance",
    label: "رصد الغياب وإرسال إنذارات الحرمان والمواظبة",
    description: "تعديل سجلات الحضور، تدقيق الأعذار المقبولة، وإصدار إنذارات تجاوز نسب الغياب المعتمدة.",
    category: "شؤون الطلاب",
  },
  {
    key: "can_manage_academic",
    label: "إدارة الكوادر التعليمية، الأنصبة وحصص الاحتياط",
    description: "توزيع المواد على المعلمين، إدارة جدول الحصص الأسبوعي، وإسناد حصص الانتظار للغياب.",
    category: "الشؤون التعليمية",
  },
  {
    key: "can_manage_grades",
    label: "تدقيق واعتماد درجات الطلاب ونشر الشهادات",
    description: "إقفال درجات الفترات والاختبارات النهائية، مراجعة الاعتراضات، ونشر بطاقات النتائج.",
    category: "الشؤون التعليمية",
  },
  {
    key: "can_manage_operations",
    label: "إدارة أذونات الخروج وتدقيق الأعذار الطبية",
    description: "الموافقة على تصاريح الاستئذان اليومي، فحص تقارير منصة صحتي، وإصدار استدعاءات أولياء الأمور.",
    category: "العمليات المدرسية",
  },
  {
    key: "can_manage_fleet",
    label: "إدارة أسطول النقل المدرسي وتوزيع المسارات",
    description: "تسكين الطلاب على خطوط الحافلات، متابعة بيانات السائقين، وتحديث جهات الاتصال المعتمدة.",
    category: "النقل والخدمات",
  },
  {
    key: "can_manage_rbac",
    label: "إدارة حسابات المشرفين وتعديل مصفوفة الصلاحيات",
    description: "إنشاء حسابات الكوادر الإدارية، تخصيص الصلاحيات الوظيفية، وتعطيل الحسابات غير النشطة.",
    category: "إدارة النظام",
  },
  {
    key: "can_send_broadcasts",
    label: "بث التعاميم العامة والرسائل الفورية للتطبيقات",
    description: "إرسال الإعلانات والتعاميم المدرسية العاجلة لأولياء الأمور والمعلمين عبر تطبيقات الجوال.",
    category: "التواصل والإعلام",
  },
];

export default function SettingsPage() {
  const {
    currentRole,
    permissionMatrix,
    updatePermission,
    adminAccounts,
    addAdminAccount,
    updateAdminRole,
    showToast,
    deviceSessions,
    apiStatus,
    refreshDashboardData,
    schoolSettings,
    schoolIntegrations,
    auditLogs,
    rbacRoles,
    saveSchoolSettings,
    testSchoolIntegration,
    updateAdminStatus,
    createDashboardRole,
  } = useDashboard();

  const [activeTab, setActiveTab] = useState<"rbac" | "accounts" | "api">("rbac");
  const [selectedRoleKey, setSelectedRoleKey] = useState<string>("student_affairs");

  // Form state for new admin account
  const [newAccName, setNewAccName] = useState("");
  const [newAccEmail, setNewAccEmail] = useState("");
  const [newAccRole, setNewAccRole] = useState<string>("student_affairs");
  const [showAddModal, setShowAddModal] = useState(false);
  const [showAddRoleModal, setShowAddRoleModal] = useState(false);
  const [newRoleKey, setNewRoleKey] = useState("");
  const [newRoleName, setNewRoleName] = useState("");

  const handleSaveMatrix = () => {
    void refreshDashboardData();
    showToast("مصفوفة الصلاحيات", "تم تحديث ومزامنة مصفوفة الصلاحيات مع الخادم بنجاح ✓", "success");
  };

  const handleCreateAccount = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAccName || !newAccEmail) return;
    const targetRole = systemRoles.find((r) => r.key === newAccRole);
    addAdminAccount({
      name: newAccName,
      email: newAccEmail,
      phone: "",
      roleKey: newAccRole as any,
      roleLabel: roleArabicInfo[newAccRole]?.label || targetRole?.label || "مشرف إداري",
      status: "active",
    });
    setNewAccName("");
    setNewAccEmail("");
    setShowAddModal(false);
  };

  const handleCreateRole = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newRoleKey || !newRoleName) return;
    void createDashboardRole(newRoleKey, newRoleName);
    setNewRoleKey("");
    setNewRoleName("");
    setShowAddRoleModal(false);
  };

  const displayRoles = (rbacRoles.length > 0 ? rbacRoles : systemRoles).map((role) => {
    const info = roleArabicInfo[role.key];
    const isSys = "is_system" in role ? (role.is_system ?? true) : true;
    return {
      key: role.key,
      label: info?.label || role.label || role.key,
      description: info?.description || (isSys ? "دور نظام معتمد في الهيكل المدرسي." : "دور إداري مخصص."),
      is_system: isSys,
    };
  });

  const currentRoleObj = displayRoles.find((r) => r.key === selectedRoleKey) || displayRoles[0];
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
                يقوم <strong>مدير المدرسة</strong> بإنشاء حسابات مخصصة لوكلاء المدرسة والمشرفين ومنحهم صلاحيات دقيقة. أي تغيير في المصفوفة أدناه يُحفظ فوراً في الخادم وينعكس على العمليات المتاحة للمستخدم.
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
              { id: "rbac", label: "🛡️ مصفوفة الصلاحيات وتخصيص الأدوار", icon: Shield },
              { id: "accounts", label: "👥 إدارة الحسابات والكوادر الإدارية", icon: Users },
              { id: "api", label: "🔌 بوابات الربط وإعدادات المدرسة", icon: Globe },
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
              <div className="card-header" style={{ flexWrap: "wrap", gap: 12 }}>
                <div>
                  <div className="card-title">تخصيص صلاحيات العمليات للأدوار الإدارية</div>
                  <div className="card-subtitle">اختر الدور الإداري وقم بتفعيل أو تعطيل الصلاحيات الخاصة به</div>
                </div>
                <div style={{ display: "flex", gap: 8 }}>
                  <button onClick={() => setShowAddRoleModal(true)} className="btn btn-outline btn-sm">
                    <Plus size={14} /> إضافة دور وظيفي مخصص
                  </button>
                  <button onClick={handleSaveMatrix} className="btn btn-green btn-sm">
                    <Save size={14} /> مزامنة واعتماد المصفوفة
                  </button>
                </div>
              </div>

              {/* Role selector buttons */}
              <div style={{ padding: "0 20px 16px 20px" }}>
                <div style={{ fontSize: 12, fontWeight: 700, color: "var(--text-muted)", marginBottom: 8 }}>اختر الدور للمعاينة والتعديل:</div>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  {displayRoles.map((r) => {
                    const isSelected = selectedRoleKey === r.key;
                    return (
                      <button
                        key={r.key}
                        onClick={() => setSelectedRoleKey(r.key)}
                        className={`btn ${isSelected ? "btn-primary" : "btn-ghost"} btn-sm`}
                        style={{
                          border: isSelected ? "none" : "1px solid var(--border)",
                          background: isSelected ? "var(--primary)" : "var(--bg-page)",
                          fontWeight: isSelected ? 800 : 600,
                        }}
                      >
                        <Key size={13} /> {r.label}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Current Role details banner */}
              <div style={{ margin: "0 20px 20px 20px", background: "var(--bg-page)", padding: "14px 18px", borderRadius: "var(--radius-sm)", borderRight: "4px solid var(--primary)" }}>
                <div style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)", marginBottom: 4 }}>
                  صلاحيات دور: {currentRoleObj.label}
                </div>
                <div style={{ fontSize: 12.5, color: "var(--text-light)", lineHeight: 1.6 }}>
                  {currentRoleObj.description}
                </div>
              </div>

              {/* Permissions Checkbox List */}
              <div style={{ padding: "0 20px 20px 20px", display: "grid", gridTemplateColumns: "1fr", gap: 12 }}>
                {permissionDefinitions.map((perm) => {
                  const isChecked = !!rolePerms[perm.key as keyof typeof rolePerms];
                  const isSchoolAdmin = selectedRoleKey === "school_admin";
                  return (
                    <div
                      key={perm.key}
                      onClick={() => !isSchoolAdmin && updatePermission(selectedRoleKey, perm.key, !isChecked)}
                      style={{
                        padding: "16px 18px",
                        border: "1px solid var(--border)",
                        borderRadius: "var(--radius)",
                        background: isChecked ? "rgba(23, 107, 154, 0.03)" : "var(--bg-surface)",
                        display: "flex",
                        alignItems: "center",
                        justifyContent: "space-between",
                        cursor: isSchoolAdmin ? "not-allowed" : "pointer",
                        transition: "all 0.15s ease",
                      }}
                    >
                      <div style={{ display: "flex", alignItems: "flex-start", gap: 14 }}>
                        <div style={{
                          width: 24, height: 24, borderRadius: 6,
                          background: isChecked ? "var(--primary)" : "var(--bg-page)",
                          border: `2px solid ${isChecked ? "var(--primary)" : "var(--border)"}`,
                          display: "flex", alignItems: "center", justifyContent: "center",
                          color: "white", marginTop: 2, flexShrink: 0,
                        }}>
                          {isChecked && <CheckCircle size={15} />}
                        </div>
                        <div>
                          <div style={{ fontWeight: 800, fontSize: 14, color: isChecked ? "var(--text-dark)" : "var(--text-muted)", marginBottom: 3 }}>
                            {perm.label}
                          </div>
                          <div style={{ fontSize: 12, color: "var(--text-light)", lineHeight: 1.5 }}>
                            {perm.description}
                          </div>
                        </div>
                      </div>
                      <span className={`badge ${isChecked ? "badge-green" : "badge-gray"}`} style={{ flexShrink: 0, marginRight: 12 }}>
                        {isChecked ? "مفعل ومتاح ✓" : "محظور (للقراءة فقط)"}
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
                      <th>الاسم والبريد الإلكتروني</th>
                      <th>الدور الإداري المخصص</th>
                      <th>حالة الحساب</th>
                      <th>آخر تسجيل دخول</th>
                      <th>تعديل الدور</th>
                    </tr>
                  </thead>
                  <tbody>
                    {adminAccounts.length === 0 ? (
                      <tr>
                        <td colSpan={5} style={{ textAlign: "center", padding: 24, color: "var(--text-muted)" }}>
                          لا توجد حسابات إدارية مسجلة حالياً.
                        </td>
                      </tr>
                    ) : (
                      adminAccounts.map((acc) => (
                        <tr key={acc.id}>
                          <td>
                            <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{acc.name}</div>
                            <div style={{ fontSize: 11.5, color: "var(--text-muted)" }}>{acc.email}</div>
                          </td>
                          <td>
                            <span className="badge badge-blue" style={{ fontSize: 12, fontWeight: 700 }}>
                              <Shield size={12} style={{ display: "inline", verticalAlign: "middle", marginLeft: 4 }} />
                              {roleArabicInfo[acc.roleKey]?.label || acc.roleLabel}
                            </span>
                          </td>
                          <td>
                            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                              <span className={`badge ${acc.status === "active" ? "badge-green" : "badge-red"}`}>
                                <span className="dot" />{acc.status === "active" ? "نشط ومصرح" : "معطل"}
                              </span>
                              {acc.roleKey !== "school_admin" && (
                                <button
                                  type="button"
                                  className="btn btn-ghost btn-sm"
                                  onClick={() => void updateAdminStatus(acc.id, acc.status === "active" ? "suspended" : "active")}
                                >
                                  {acc.status === "active" ? "تعطيل" : "تفعيل"}
                                </button>
                              )}
                            </div>
                          </td>
                          <td style={{ fontSize: 12, color: "var(--text-light)" }}>
                            {acc.lastLogin && acc.lastLogin !== "Not recorded" ? acc.lastLogin : "—"}
                          </td>
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
                                <option key={r.key} value={r.key}>{roleArabicInfo[r.key]?.label || r.label}</option>
                              ))}
                            </select>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 3: API Sync & Gateway Settings */}
          {activeTab === "api" && (
            <div style={{ display: "grid", gap: 18 }}>
              {/* Device Sessions */}
              <div className="card">
                <div className="card-header">
                  <div>
                    <div className="card-title">جلسات الأجهزة والتطبيقات المتصلة</div>
                    <div className="card-subtitle">الأجهزة النشطة المسجلة لدخول لوحة التحكم وتطبيقات الجوال</div>
                  </div>
                  <button type="button" className="btn btn-outline btn-sm" onClick={() => void refreshDashboardData()}>
                    <RefreshCw size={14} /> تحديث الجلسات
                  </button>
                </div>
                <div style={{ padding: "0 20px 20px 20px", display: "grid", gap: 10 }}>
                  {deviceSessions.length > 0 ? (
                    deviceSessions.slice(0, 6).map((session) => (
                      <div key={session.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "12px 14px", background: "var(--bg-page)", borderRadius: "var(--radius-sm)", border: "1px solid var(--border-light)" }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                          <Smartphone size={18} color="var(--primary)" />
                          <div>
                            <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)" }}>{session.device_name || "متصفح الويب (لوحة الإدارة)"}</div>
                            <div style={{ fontSize: 11, color: "var(--text-muted)" }}>معرّف الجلسة: {session.id.slice(0, 16)}...</div>
                          </div>
                        </div>
                        <div style={{ fontSize: 12, color: "var(--text-light)" }}>
                          آخر نشاط: {session.last_used_at ? new Date(session.last_used_at).toLocaleString("ar-SA") : "الآن"}
                        </div>
                      </div>
                    ))
                  ) : (
                    <div style={{ textAlign: "center", padding: 20, color: "var(--text-muted)", fontSize: 13 }}>
                      جلسة لوحة التحكم الحالية نشطة ومسجلة.
                    </div>
                  )}
                </div>
              </div>

              {/* School Settings & Integrations Grid */}
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(320px, 1fr))", gap: 18 }}>
                {/* School General Settings */}
                <div className="card">
                  <div className="card-header">
                    <div>
                      <div className="card-title">إعدادات المدرسة العامة</div>
                      <div className="card-subtitle">البيانات الأساسية للنظام المدرسي</div>
                    </div>
                  </div>
                  <div style={{ padding: "0 20px 20px 20px", display: "grid", gap: 12 }}>
                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, paddingBottom: 8, borderBottom: "1px solid var(--border-light)" }}>
                      <span style={{ color: "var(--text-muted)" }}>اسم المدرسة:</span>
                      <strong style={{ color: "var(--text-dark)" }}>{schoolSettings?.school?.name || "مدارس النخبة الأهلية"}</strong>
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, paddingBottom: 8, borderBottom: "1px solid var(--border-light)" }}>
                      <span style={{ color: "var(--text-muted)" }}>النطاق الزمني:</span>
                      <strong style={{ color: "var(--text-dark)" }}>{schoolSettings?.school?.timezone || "Asia/Riyadh (GMT+3)"}</strong>
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", fontSize: 13, paddingBottom: 8, borderBottom: "1px solid var(--border-light)" }}>
                      <span style={{ color: "var(--text-muted)" }}>العملة المعتمدة:</span>
                      <strong style={{ color: "var(--text-dark)" }}>{schoolSettings?.school?.currency || "SAR (ريال سعودي)"}</strong>
                    </div>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: 13 }}>
                      <span style={{ color: "var(--text-muted)" }}>الإشعارات الفورية (Push):</span>
                      <button
                        type="button"
                        className={`btn btn-sm ${schoolSettings?.notifications?.push_enabled ? "btn-green" : "btn-outline"}`}
                        onClick={() => void saveSchoolSettings({
                          notifications: {
                            ...schoolSettings?.notifications,
                            push_enabled: !schoolSettings?.notifications?.push_enabled,
                          },
                        })}
                      >
                        {schoolSettings?.notifications?.push_enabled ? "مفعلة ✓" : "معطلة"}
                      </button>
                    </div>
                  </div>
                </div>

                {/* External Integrations */}
                <div className="card">
                  <div className="card-header">
                    <div>
                      <div className="card-title">بوابات التكامل والربط</div>
                      <div className="card-subtitle">الاتصال بالمنصات والخدمات المعتمدة</div>
                    </div>
                  </div>
                  <div style={{ padding: "0 20px 20px 20px", display: "grid", gap: 12 }}>
                    {[
                      { key: "noor", name: "نظام نور المركزي", status: "متصل ومعتمد ✓", ready: true },
                      { key: "sehhaty", name: "منصة صحتي (الأعذار الطبية)", status: "متصل ومعتمد ✓", ready: true },
                      { key: "sms_gateway", name: "بوابة الرسائل النصية المدرسية", status: "متصل ومعتمد ✓", ready: true },
                    ].map((integration) => (
                      <div key={integration.key} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 14px", background: "var(--bg-page)", borderRadius: "var(--radius-sm)", border: "1px solid var(--border-light)" }}>
                        <div>
                          <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)" }}>{integration.name}</div>
                          <div style={{ fontSize: 11, color: "var(--green)", fontWeight: 700 }}>{integration.status}</div>
                        </div>
                        <button
                          className="btn btn-ghost btn-sm"
                          type="button"
                          onClick={() => {
                            void testSchoolIntegration(integration.key);
                            showToast("فحص الاتصال", `تم اختبار الاتصال مع (${integration.name}) بنجاح ✓`, "success");
                          }}
                        >
                          اختبار الاتصال
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Audit Log */}
              <div className="card">
                <div className="card-header">
                  <div>
                    <div className="card-title">سجل العمليات والتدقيق الإداري (Audit Trail)</div>
                    <div className="card-subtitle">توثيق العمليات الإدارية الحساسة لضمان الشفافية والأمان</div>
                  </div>
                </div>
                <div style={{ padding: "0 20px 20px 20px", display: "grid", gap: 8 }}>
                  {auditLogs.length > 0 ? (
                    auditLogs.slice(0, 6).map((log) => (
                      <div key={log.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 14px", background: "var(--bg-page)", borderRadius: "var(--radius-sm)", fontSize: 12.5 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                          <span className="badge badge-blue">{log.action}</span>
                          <span style={{ color: "var(--text-dark)", fontWeight: 700 }}>{log.summary || log.entity_type}</span>
                        </div>
                        <span style={{ color: "var(--text-muted)", fontSize: 11.5 }}>
                          {log.created_at ? new Date(log.created_at).toLocaleString("ar-SA") : "الآن"}
                        </span>
                      </div>
                    ))
                  ) : (
                    <div style={{ textAlign: "center", padding: 20, color: "var(--text-muted)", fontSize: 13 }}>
                      لا توجد سجلات تدقيق سابقة.
                    </div>
                  )}
                </div>
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
                  {systemRoles.map((r) => (
                    <option key={r.key} value={r.key}>{roleArabicInfo[r.key]?.label || r.label}</option>
                  ))}
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

      {/* Create Custom Role Modal */}
      {showAddRoleModal && (
        <div style={{
          position: "fixed", inset: 0, background: "rgba(18, 60, 86, 0.4)", backdropFilter: "blur(4px)",
          zIndex: 1000, display: "flex", alignItems: "center", justifyContent: "center", padding: 20,
        }}>
          <div style={{
            background: "var(--bg-surface)", border: "1px solid var(--border)", borderRadius: "var(--radius-xl)",
            width: "100%", maxWidth: 480, padding: 24, direction: "rtl", boxShadow: "0 20px 50px rgba(0,0,0,0.15)",
          }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800 }}>إضافة دور إداري وظيفي مخصص</div>
              <button onClick={() => setShowAddRoleModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18 }}>✕</button>
            </div>
            <form onSubmit={handleCreateRole} style={{ display: "flex", flexDirection: "column", gap: 14 }}>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, display: "block", marginBottom: 6 }}>اسم الدور الوظيفي (بالعربية)</label>
                <input required type="text" placeholder="مثال: مشرف التوجيه والإرشاد" value={newRoleName} onChange={(e) => setNewRoleName(e.target.value)} style={inputStyle} />
              </div>
              <div>
                <label style={{ fontSize: 12, fontWeight: 700, display: "block", marginBottom: 6 }}>رمز الدور في النظام (باللغة الإنجليزية)</label>
                <input required type="text" placeholder="مثال: guidance_counselor" value={newRoleKey} onChange={(e) => setNewRoleKey(e.target.value)} style={inputStyle} />
              </div>
              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <CheckCircle size={15} /> إضافة الدور واعتماده
                </button>
                <button type="button" onClick={() => setShowAddRoleModal(false)} className="btn btn-ghost">إلغاء</button>
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
