"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import StudentProfileModal from "@/components/StudentProfileModal";
import { useDashboard } from "@/context/DashboardContext";
import { Student } from "@/data/mockData";
import {
  Archive,
  Bus,
  CheckCircle,
  Clock,
  List,
  Phone,
  Plus,
  Save,
  Shield,
  Trash2,
  UserCheck,
  UserPlus,
  Users,
} from "lucide-react";

export default function TransportPage() {
  const {
    busRoutes,
    students,
    showToast,
    transportSummary,
    transportPassengers,
    transportEvents,
    refreshTransportRouteDetails,
    createDashboardTransportRoute,
    updateDashboardTransportRoute,
    archiveDashboardTransportRoute,
    assignStudentToTransportRoute,
    archiveStudentTransportAssignment,
    logTransportDriverContact,
  } = useDashboard();

  const [selectedBusId, setSelectedBusId] = useState<string | null>(() => busRoutes[0]?.id || null);
  const [profileStudent, setProfileStudent] = useState<Student | null>(null);
  const [newRouteName, setNewRouteName] = useState("");
  const [newRouteCode, setNewRouteCode] = useState("");
  const [newRouteCapacity, setNewRouteCapacity] = useState("40");
  const [newRouteDriverName, setNewRouteDriverName] = useState("");
  const [newRouteDriverPhone, setNewRouteDriverPhone] = useState("");
  const [newRoutePlate, setNewRoutePlate] = useState("");
  const [selectedStudentToAssign, setSelectedStudentToAssign] = useState("");

  const handleDriverContact = (busId: string, driverName: string, phone: string) => {
    void logTransportDriverContact(busId, "called", `تم الاتصال بالسائق ${driverName} عبر لوحة الإدارة`)
      .then(() => showToast("تم تسجيل الاتصال", `تم تسجيل سجل الاتصال بالسائق ${driverName}.`, "success"))
      .catch((error) => showToast("تعذر تسجيل الاتصال", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
  };

  const handleCreateRoute = () => {
    if (!newRouteName.trim()) {
      showToast("تنبيه", "يرجى كتابة اسم المسار.", "warning");
      return;
    }
    const capacity = Number(newRouteCapacity);
    void createDashboardTransportRoute({
      name: newRouteName.trim(),
      code: newRouteCode.trim() || undefined,
      capacity: Number.isFinite(capacity) && capacity > 0 ? capacity : 40,
      driver_name: newRouteDriverName.trim() || null,
      driver_phone: newRouteDriverPhone.trim() || null,
      plate_number: newRoutePlate.trim() || null,
    }).then(() => {
      showToast("تم إنشاء المسار", `تمت إضافة ${newRouteName} بنجاح.`, "success");
      setNewRouteName("");
      setNewRouteCode("");
      setNewRouteCapacity("40");
      setNewRouteDriverName("");
      setNewRouteDriverPhone("");
      setNewRoutePlate("");
    });
  };

  const handleUpdateRouteCapacity = (busId: string, currentCapacity: number) => {
    const nextCapacity = window.prompt("السعة الاستيعابية الجديدة للحافلة (عدد المقاعد):", String(currentCapacity || 40));
    if (!nextCapacity) return;
    const capacity = Number(nextCapacity);
    if (!Number.isFinite(capacity) || capacity <= 0) {
      showToast("تنبيه", "يجب أن تكون السعة رقماً موجباً.", "warning");
      return;
    }
    void updateDashboardTransportRoute(busId, { capacity }).then(() => {
      showToast("تم التحديث", "تم تحديث السعة الاستيعابية للمسار.", "success");
    });
  };

  const handleArchiveRoute = (busId: string, routeName: string) => {
    if (!window.confirm(`هل أنت متأكد من رغبتك في أرشفة ${routeName}؟`)) return;
    void archiveDashboardTransportRoute(busId).then(() => {
      showToast("تمت الأرشفة", `تم أرشفة المسار ${routeName}.`, "info");
      if (selectedBusId === busId) {
        setSelectedBusId(null);
      }
    });
  };

  // Compute student roster
  const busRoster = (busId: string) => students.filter(s => s.busRouteId === busId);
  const totalAssignedStudents = transportSummary?.assigned_students ?? students.filter(s => s.busRouteId).length;
  const totalFleetCapacity = busRoutes.reduce((sum, b) => sum + (b.capacity || 40), 0);
  const unassignedStudents = students.filter(s => !s.busRouteId);

  const activeBus = busRoutes.find(b => b.id === (selectedBusId || busRoutes[0]?.id));
  const activeBusRoster = activeBus ? busRoster(activeBus.id) : [];
  const activeLivePassengers = activeBus ? (transportPassengers[activeBus.id] ?? []) : [];
  const activeBusEvents = activeBus ? (transportEvents[activeBus.id] ?? []) : [];

  const assignableStudents = unassignedStudents.slice(0, 50);

  const [showAddRouteModal, setShowAddRouteModal] = useState(false);

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="النقل المدرسي والحافلات"
          subtitle="إدارة أسطول الحافلات المدرسية، تعيين السائقين والمشرفين، وتسكين الطلاب على المسارات المعتمدة"
        />
        <main className="page-body">

          {/* Fleet KPI Banner */}
          <div className="kpi-grid" style={{ marginBottom: 24 }}>
            {[
              { label: "إجمالي خطوط النقل والحافلات", value: `${transportSummary?.routes ?? busRoutes.length} مسار`, icon: <Bus size={22} />, bg: "#EFF6FF", color: "#176B9A" },
              { label: "السعة الاستيعابية للأسطول", value: `${totalFleetCapacity} مقعد`, icon: <UserCheck size={22} />, bg: "#F8F4FF", color: "#8B5CF6" },
              { label: "طلاب مسجلون بالنقل المدرسي", value: `${totalAssignedStudents} طالب`, icon: <Users size={22} />, bg: "#F0FDF4", color: "#16A34A" },
              { label: "طلاب بنقل خاص / مع أولياء الأمور", value: `${unassignedStudents.length} طالب`, icon: <Shield size={22} />, bg: "#FFF7ED", color: "#F59E0B" },
            ].map((stat, i) => (
              <div key={i} className="kpi-card">
                <div className="kpi-icon" style={{ background: stat.bg, color: stat.color }}>{stat.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 20 }}>{stat.value}</div>
                  <div className="kpi-label">{stat.label}</div>
                </div>
              </div>
            ))}
          </div>

          {/* Header Action Bar */}
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
            <div>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>خطوط النقل المسجلة ({busRoutes.length})</div>
              <div style={{ fontSize: 12, color: "var(--text-muted)" }}>حدد مساراً لإدارة ركابه وتسكين الطلاب الجدد</div>
            </div>
            <button className="btn btn-primary" onClick={() => setShowAddRouteModal(true)}>
              <Plus size={15} /> إضافة خط سير حافلة جديد
            </button>
          </div>

          <div className="grid-2">
            {/* Bus Cards List */}
            <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
              {busRoutes.map((bus) => {
                const roster = busRoster(bus.id);
                const livePassengers = transportPassengers[bus.id] ?? [];
                const isSelected = (selectedBusId || busRoutes[0]?.id) === bus.id;
                const assignedCount = livePassengers.length || roster.length || bus.assignedStudentsCount;
                const cap = bus.capacity || 40;
                const occupancyPercent = Math.min(Math.round((assignedCount / cap) * 100), 100);

                return (
                  <div
                    key={bus.id}
                    className="card"
                    style={{
                      padding: "18px",
                      border: isSelected ? "2px solid var(--primary)" : "1px solid var(--border)",
                      transition: "border-color 0.2s",
                    }}
                  >
                    <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", marginBottom: 12 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                        <div style={{
                          width: 44, height: 44, borderRadius: "var(--radius)",
                          background: "var(--primary-50)",
                          display: "flex", alignItems: "center", justifyContent: "center",
                        }}>
                          <Bus size={22} color="var(--primary)" />
                        </div>
                        <div>
                          <div style={{ fontSize: 15, fontWeight: 800, color: "var(--text-dark)" }}>{bus.routeName}</div>
                          <div style={{ fontSize: 11.5, color: "var(--text-muted)", fontFamily: "monospace", marginTop: 2 }}>
                            لوحة المركبة: {bus.plateNumber || "—"}
                          </div>
                        </div>
                      </div>
                      <span className="badge badge-blue">
                        {assignedCount} / {cap} مقعد
                      </span>
                    </div>

                    {/* Occupancy bar */}
                    <div style={{ marginBottom: 12 }}>
                      <div style={{ display: "flex", justifyContent: "space-between", fontSize: 11, color: "var(--text-muted)", marginBottom: 4 }}>
                        <span>نسبة الإشغال</span>
                        <span>{occupancyPercent}%</span>
                      </div>
                      <div className="progress-bar" style={{ height: 6 }}>
                        <div
                          className="progress-fill"
                          style={{
                            width: `${occupancyPercent}%`,
                            background: occupancyPercent > 90 ? "var(--danger)" : occupancyPercent > 70 ? "var(--warning)" : "var(--primary)",
                          }}
                        />
                      </div>
                    </div>

                    {/* Bus Info Grid */}
                    <div style={{
                      background: "var(--bg-page)", borderRadius: "var(--radius-sm)", padding: "10px 14px",
                      display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginBottom: 14, fontSize: 12,
                    }}>
                      <div><span style={{ color: "var(--text-muted)" }}>السائق: </span><strong>{bus.driverName || "—"}</strong></div>
                      <div><span style={{ color: "var(--text-muted)" }}>الجوال: </span><span style={{ direction: "ltr", display: "inline-block", fontFamily: "monospace" }}>{bus.driverPhone || "—"}</span></div>
                      <div><span style={{ color: "var(--text-muted)" }}>المشرف: </span><strong>{bus.supervisorName || "—"}</strong></div>
                      <div><span style={{ color: "var(--text-muted)" }}>سعة الحافلة: </span><strong>{cap} مقعد</strong></div>
                    </div>

                    {/* Actions */}
                    <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                      <button
                        onClick={() => {
                          setSelectedBusId(bus.id);
                          void refreshTransportRouteDetails(bus.id);
                        }}
                        className={`btn ${isSelected ? "btn-primary" : "btn-outline"} btn-sm`}
                        style={{ flex: 1, justifyContent: "center" }}
                      >
                        <List size={14} /> إدارة ركاب المسار ({assignedCount})
                      </button>
                      {bus.driverPhone && (
                        <button
                          onClick={() => handleDriverContact(bus.id, bus.driverName, bus.driverPhone)}
                          className="btn btn-ghost btn-sm"
                          title="اتصال بالسائق"
                        >
                          <Phone size={14} />
                        </button>
                      )}
                      <button
                        onClick={() => handleUpdateRouteCapacity(bus.id, cap)}
                        className="btn btn-ghost btn-sm"
                        title="تعديل السعة الاستيعابية"
                      >
                        <Save size={14} />
                      </button>
                      <button
                        onClick={() => handleArchiveRoute(bus.id, bus.routeName)}
                        className="btn btn-ghost btn-sm"
                        title="أرشفة المسار"
                        style={{ color: "var(--danger)" }}
                      >
                        <Archive size={14} />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Passenger Roster & Assignment Panel */}
            <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
              {activeBus ? (
                <div className="card" style={{ padding: "20px" }}>
                  <div className="card-header" style={{ padding: 0, marginBottom: 16 }}>
                    <div>
                      <div className="card-title">تسكين وركاب {activeBus.routeName}</div>
                      <div className="card-subtitle">
                        السائق: {activeBus.driverName} — اللوحة: {activeBus.plateNumber || "—"}
                      </div>
                    </div>
                    <span className="badge badge-green">
                      {activeLivePassengers.length || activeBusRoster.length} طلاب مسجلين
                    </span>
                  </div>

                  {/* Assign Student Form */}
                  <div style={{
                    background: "var(--bg-page)",
                    border: "1px solid var(--border-light)",
                    borderRadius: "var(--radius)",
                    padding: "12px 14px",
                    marginBottom: 16,
                  }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: "var(--text-dark)", marginBottom: 8 }}>
                      تسكين طالب جديد على هذا المسار:
                    </div>
                    <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 8 }}>
                      <select
                        className="form-select"
                        value={selectedStudentToAssign}
                        onChange={(e) => setSelectedStudentToAssign(e.target.value)}
                      >
                        <option value="">اختر طالباً من غير المسجلين بالنقل...</option>
                        {assignableStudents.map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.name} ({s.sectionName || s.studentCode})
                          </option>
                        ))}
                      </select>
                      <button
                        className="btn btn-primary btn-sm"
                        disabled={!selectedStudentToAssign}
                        onClick={() => {
                          if (selectedStudentToAssign) {
                            void assignStudentToTransportRoute(activeBus.id, selectedStudentToAssign).then(() => {
                              showToast("تم تسكين الطالب", `تم تسجيل الطالب على ${activeBus.routeName}.`, "success");
                              setSelectedStudentToAssign("");
                            });
                          }
                        }}
                      >
                        <UserPlus size={14} /> تسكين
                      </button>
                    </div>
                  </div>

                  {/* Passengers Roster */}
                  <div style={{ maxHeight: 380, overflowY: "auto" }}>
                    {activeLivePassengers.length > 0 ? (
                      activeLivePassengers.map((passenger, idx) => (
                        <div
                          key={passenger.assignment_id}
                          style={{
                            display: "flex", alignItems: "center", gap: 10,
                            padding: "10px 12px", borderRadius: "var(--radius-sm)",
                            borderBottom: idx < activeLivePassengers.length - 1 ? "1px solid var(--border-light)" : "none",
                          }}
                        >
                          <div style={{
                            width: 32, height: 32, borderRadius: "50%",
                            background: "var(--primary-50)", color: "var(--primary)",
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 11, fontWeight: 800, flexShrink: 0,
                          }}>
                            {(passenger.student_name ?? "ST").slice(0, 2)}
                          </div>
                          <div style={{ flex: 1 }}>
                            <div style={{ fontWeight: 700, fontSize: 13, color: "var(--text-dark)" }}>{passenger.student_name}</div>
                            <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{passenger.section_name ?? passenger.admission_number}</div>
                          </div>
                          <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                            {passenger.parent_phone && (
                              <span style={{ fontSize: 11, color: "var(--text-light)", fontFamily: "monospace" }}>
                                {passenger.parent_phone}
                              </span>
                            )}
                            <button
                              className="btn btn-ghost btn-sm"
                              title="إلغاء التسكين"
                              style={{ color: "var(--danger)" }}
                              onClick={() => void archiveStudentTransportAssignment(activeBus.id, passenger.assignment_id).then(() => {
                                showToast("تم الإلغاء", "تم إلغاء تسجيل الطالب من المسار.", "info");
                              })}
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        </div>
                      ))
                    ) : activeBusRoster.length > 0 ? (
                      activeBusRoster.map((stu, idx) => (
                        <div
                          key={stu.id}
                          onClick={() => setProfileStudent(stu)}
                          style={{
                            display: "flex", alignItems: "center", gap: 10,
                            padding: "10px 12px", borderRadius: "var(--radius-sm)",
                            borderBottom: idx < activeBusRoster.length - 1 ? "1px solid var(--border-light)" : "none",
                            cursor: "pointer", transition: "background 0.12s",
                          }}
                          onMouseEnter={e => (e.currentTarget.style.background = "var(--primary-50)")}
                          onMouseLeave={e => (e.currentTarget.style.background = "transparent")}
                        >
                          <div style={{
                            width: 32, height: 32, borderRadius: "50%",
                            background: stu.avatarColor + "20", color: stu.avatarColor,
                            display: "flex", alignItems: "center", justifyContent: "center",
                            fontSize: 11, fontWeight: 800, flexShrink: 0,
                          }}>
                            {stu.avatarInitials}
                          </div>
                          <div style={{ flex: 1 }}>
                            <div style={{ fontWeight: 700, fontSize: 13, color: "var(--text-dark)" }}>{stu.name}</div>
                            <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{stu.sectionName} — ولي الأمر: {stu.parentName}</div>
                          </div>
                          <span className="badge badge-gray" style={{ fontSize: 10 }}>مسجل بالمسار</span>
                        </div>
                      ))
                    ) : (
                      <div style={{ textAlign: "center", padding: "30px 16px", color: "var(--text-muted)", fontSize: 13 }}>
                        لا يوجد طلاب مسجلون على هذا المسار حالياً. يمكنك اختيار طالب من القائمة أعلاه لتسكينه.
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div className="card" style={{ padding: 30, textAlign: "center", color: "var(--text-muted)" }}>
                  اختر مساراً من القائمة لمعاينة الطلاب المسجلين وإدارة التسكين.
                </div>
              )}

              {/* Unassigned Students Card */}
              <div className="card" style={{ padding: "18px 20px" }}>
                <div style={{ fontSize: 13, fontWeight: 800, color: "var(--text-dark)", marginBottom: 10 }}>
                  طلاب غير مسجلين في النقل المدرسي ({unassignedStudents.length} طالب)
                </div>
                <div style={{ display: "flex", gap: 6, flexWrap: "wrap", maxHeight: 160, overflowY: "auto" }}>
                  {unassignedStudents.map(s => (
                    <div
                      key={s.id}
                      onClick={() => setProfileStudent(s)}
                      style={{
                        display: "flex", alignItems: "center", gap: 5,
                        padding: "5px 10px", borderRadius: 16,
                        background: s.avatarColor + "15", cursor: "pointer",
                        border: `1px solid ${s.avatarColor}25`,
                      }}
                    >
                      <div style={{ width: 18, height: 18, borderRadius: "50%", background: s.avatarColor + "30", color: s.avatarColor, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 8, fontWeight: 800 }}>
                        {s.avatarInitials}
                      </div>
                      <span style={{ fontSize: 11, fontWeight: 700, color: s.avatarColor }}>{s.name}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </main>
        <Footer />
      </div>

      {/* Add Bus Route Modal */}
      {showAddRouteModal && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>إضافة خط سير حافلة جديد</div>
              <button onClick={() => setShowAddRouteModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18, color: "var(--text-muted)" }}>✕</button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleCreateRoute();
                setShowAddRouteModal(false);
              }}
              style={{ display: "flex", flexDirection: "column", gap: 14 }}
            >
              <div className="form-group">
                <label className="form-label">اسم المسار والحي السكني</label>
                <input
                  required
                  className="form-input"
                  placeholder="مثال: مسار 4 - حي الصحافة والياسمين"
                  value={newRouteName}
                  onChange={(e) => setNewRouteName(e.target.value)}
                />
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div className="form-group">
                  <label className="form-label">رمز المسار (اختياري)</label>
                  <input
                    className="form-input"
                    placeholder="مثال: BUS-04"
                    value={newRouteCode}
                    onChange={(e) => setNewRouteCode(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">السعة (عدد المقاعد)</label>
                  <input
                    required
                    type="number"
                    min="1"
                    className="form-input"
                    placeholder="40"
                    value={newRouteCapacity}
                    onChange={(e) => setNewRouteCapacity(e.target.value)}
                  />
                </div>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
                <div className="form-group">
                  <label className="form-label">اسم السائق المسؤول</label>
                  <input
                    className="form-input"
                    placeholder="مثال: صالح الرشيدي"
                    value={newRouteDriverName}
                    onChange={(e) => setNewRouteDriverName(e.target.value)}
                  />
                </div>
                <div className="form-group">
                  <label className="form-label">رقم جوال السائق</label>
                  <input
                    className="form-input"
                    placeholder="0501234567"
                    value={newRouteDriverPhone}
                    onChange={(e) => setNewRouteDriverPhone(e.target.value)}
                  />
                </div>
              </div>

              <div className="form-group">
                <label className="form-label">رقم لوحة الحافلة</label>
                <input
                  className="form-input"
                  placeholder="مثال: أ ب ج 1234"
                  value={newRoutePlate}
                  onChange={(e) => setNewRoutePlate(e.target.value)}
                />
              </div>

              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <CheckCircle size={15} /> حفظ واعتماد المسار
                </button>
                <button type="button" onClick={() => setShowAddRouteModal(false)} className="btn btn-ghost">
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <StudentProfileModal student={profileStudent} onClose={() => setProfileStudent(null)} />
    </div>
  );
}
