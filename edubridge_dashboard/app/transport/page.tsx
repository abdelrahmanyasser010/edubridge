"use client";

import React, { useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import StudentProfileModal from "@/components/StudentProfileModal";
import { useDashboard } from "@/context/DashboardContext";
import { Student } from "@/data/mockData";
import { Archive, Bus, Clock, List, MapPin, Phone, Plus, Save, Shield, Trash2, UserPlus, Users, AlertTriangle } from "lucide-react";

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { cls: string; label: string }> = {
    in_school: { cls: "badge-blue", label: "في حرم المدرسة" },
    on_route: { cls: "badge-orange", label: "في الطريق (تتبع نشط)" },
    arrived: { cls: "badge-green", label: "وصل للوجهة بنجاح" },
  };
  const { cls, label } = map[status] || { cls: "badge-gray", label: status };
  return <span className={`badge ${cls}`}><span className="dot" />{label}</span>;
}

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
    sendTransportDelayAlert,
    logTransportDriverContact,
    apiStatus,
  } = useDashboard();
  const [selectedBusId, setSelectedBusId] = useState<string | null>(null);
  const [rosterBusId, setRosterBusId] = useState<string | null>(null);
  const [profileStudent, setProfileStudent] = useState<Student | null>(null);
  const [newRouteName, setNewRouteName] = useState("");
  const [newRouteCode, setNewRouteCode] = useState("");
  const [newRouteCapacity, setNewRouteCapacity] = useState("40");
  const [newRouteDriverName, setNewRouteDriverName] = useState("");
  const [newRouteDriverPhone, setNewRouteDriverPhone] = useState("");
  const [newRoutePlate, setNewRoutePlate] = useState("");
  const [assignmentStudentByRoute, setAssignmentStudentByRoute] = useState<Record<string, string>>({});

  const handleDriverContact = (busId: string, driverName: string, phone: string) => {
    void logTransportDriverContact(busId, "called", `Called ${driverName} at ${phone} from dashboard.`)
      .catch((error) => showToast("تعذر تسجيل الاتصال", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
  };

  const handleEmergencyAlert = (busId: string, routeName: string) => {
    void sendTransportDelayAlert(busId, 15, `Bus route ${routeName} is delayed by 15 minutes.`)
      .catch((error) => showToast("تعذر إرسال تنبيه التأخر", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
  };

  const handleCreateRoute = () => {
    const capacity = Number(newRouteCapacity);
    void createDashboardTransportRoute({
      name: newRouteName || `Route ${busRoutes.length + 1}`,
      code: newRouteCode || undefined,
      capacity: Number.isFinite(capacity) ? capacity : 40,
      driver_name: newRouteDriverName || null,
      driver_phone: newRouteDriverPhone || null,
      plate_number: newRoutePlate || null,
    }).then(() => {
      setNewRouteName("");
      setNewRouteCode("");
      setNewRouteCapacity("40");
      setNewRouteDriverName("");
      setNewRouteDriverPhone("");
      setNewRoutePlate("");
    });
  };

  const handleUpdateRouteCapacity = (busId: string, currentCount: number) => {
    const nextCapacity = window.prompt("Route capacity", String(Math.max(currentCount, 40)));
    if (!nextCapacity) return;
    const capacity = Number(nextCapacity);
    if (!Number.isFinite(capacity) || capacity <= 0) {
      showToast("Transport API", "Capacity must be a positive number.", "warning");
      return;
    }
    void updateDashboardTransportRoute(busId, { capacity });
  };

  const handleArchiveRoute = (busId: string, routeName: string) => {
    if (!window.confirm(`Archive route ${routeName}?`)) return;
    void archiveDashboardTransportRoute(busId);
  };

  // Compute real student roster per bus
  const busRoster = (busId: string) => students.filter(s => s.busRouteId === busId);
  const backendStudents = students.filter((student) => /^\d+$/.test(student.id));
  const totalOnBus = transportSummary?.assigned_students ?? students.filter(s => s.busRouteId).length;
  const onRouteBus = busRoutes.find(b => b.status === "on_route");

  const rosterBus = busRoutes.find(b => b.id === rosterBusId);
  const rosterStudents = rosterBusId ? busRoster(rosterBusId) : [];

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="النقل المدرسي والحافلات"
          subtitle="مراقبة خطوط سير الحافلات، التتبع الجغرافي الحي، وقوائم الطلاب المسجلين في كل مسار"
        />
        <main className="page-body">

          {/* Fleet KPI Banner */}
          <div className="kpi-grid" style={{ marginBottom: 24 }}>
            {[
              { label: "إجمالي الحافلات النشطة", value: `${transportSummary?.routes ?? busRoutes.length} حافلات`, icon: <Bus size={22} />, bg: "#EFF6FF", color: "#176B9A" },
              { label: "حافلة في الطريق (بث حي)", value: `${transportSummary?.on_route ?? busRoutes.filter(b => b.status === "on_route").length} حافلة`, icon: <MapPin size={22} />, bg: "#FFF7ED", color: "#F59E0B" },
              { label: "طلاب مسجلون على الحافلات", value: `${totalOnBus} طالب`, icon: <Users size={22} />, bg: "#F0FDF4", color: "#16A34A" },
              { label: "طلاب غير مسجلين على الحافلات", value: `${students.length - totalOnBus} طالب`, icon: <Shield size={22} />, bg: "#F8F4FF", color: "#8B5CF6" },
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

          <div className="card" style={{ marginBottom: 20 }}>
            <div className="card-header">
              <div>
                <div className="card-title">Live transport route management</div>
                <div className="card-subtitle">POST/PATCH/DELETE /dashboard/transport/routes + assignments</div>
              </div>
              <span className="badge badge-blue">Dashboard API</span>
            </div>
            <div className="card-body">
              <div style={{ display: "grid", gridTemplateColumns: "1.2fr 0.8fr 0.6fr", gap: 10, marginBottom: 10 }}>
                <input className="form-input" value={newRouteName} onChange={(event) => setNewRouteName(event.target.value)} placeholder="Route name" />
                <input className="form-input" value={newRouteCode} onChange={(event) => setNewRouteCode(event.target.value)} placeholder="Code" />
                <input className="form-input" type="number" min="1" value={newRouteCapacity} onChange={(event) => setNewRouteCapacity(event.target.value)} placeholder="Capacity" />
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr auto", gap: 10 }}>
                <input className="form-input" value={newRouteDriverName} onChange={(event) => setNewRouteDriverName(event.target.value)} placeholder="Driver name" />
                <input className="form-input" value={newRouteDriverPhone} onChange={(event) => setNewRouteDriverPhone(event.target.value)} placeholder="Driver phone" />
                <input className="form-input" value={newRoutePlate} onChange={(event) => setNewRoutePlate(event.target.value)} placeholder="Plate number" />
                <button className="btn btn-primary btn-sm" onClick={handleCreateRoute}>
                  <Plus size={14} /> Create
                </button>
              </div>
            </div>
          </div>

          <div className="grid-2">
            {/* Bus Cards List */}
            <div style={{ display: "flex", flexDirection: "column", gap: 16 }}>
              {busRoutes.map((bus) => {
                const roster = busRoster(bus.id);
                const livePassengers = transportPassengers[bus.id] ?? [];
                const liveEvents = transportEvents[bus.id] ?? [];
                const isShowingRoster = rosterBusId === bus.id;
                const assignedStudentIds = new Set([
                  ...roster.map((student) => student.id),
                  ...livePassengers.map((passenger) => passenger.student_id),
                ]);
                const assignableStudents = backendStudents
                  .filter((student) => !assignedStudentIds.has(student.id))
                  .slice(0, 50);
                const selectedAssignmentStudentId = assignmentStudentByRoute[bus.id] ?? "";
                return (
                  <div key={bus.id} className="card" style={{ padding: "20px" }}>
                    <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between", marginBottom: 14 }}>
                      <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                        <div style={{
                          width: 48, height: 48, borderRadius: "var(--radius)",
                          background: bus.status === "on_route" ? "var(--warning-50)" : bus.status === "arrived" ? "var(--green-50)" : "var(--primary-50)",
                          display: "flex", alignItems: "center", justifyContent: "center",
                        }}>
                          <Bus size={24} color={bus.status === "on_route" ? "var(--warning)" : bus.status === "arrived" ? "var(--green)" : "var(--primary)"} />
                        </div>
                        <div>
                          <div style={{ fontSize: 15, fontWeight: 800, color: "var(--text-dark)" }}>{bus.routeName}</div>
                          <div style={{ fontSize: 11.5, color: "var(--text-muted)", fontFamily: "monospace", marginTop: 2 }}>لوحة: {bus.plateNumber}</div>
                        </div>
                      </div>
                      <StatusBadge status={bus.status} />
                    </div>

                    {/* Bus Info */}
                    <div style={{
                      background: "var(--bg-page)", borderRadius: "var(--radius-sm)", padding: "12px 16px",
                      display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginBottom: 14, fontSize: 12.5,
                    }}>
                      <div><span style={{ color: "var(--text-muted)" }}>السائق: </span><strong>{bus.driverName}</strong></div>
                      <div><span style={{ color: "var(--text-muted)" }}>الجوال: </span><span style={{ direction: "ltr", display: "inline-block", fontFamily: "monospace" }}>{bus.driverPhone}</span></div>
                      <div><span style={{ color: "var(--text-muted)" }}>المشرف: </span><strong>{bus.supervisorName}</strong></div>
                      <div>
                        <span style={{ color: "var(--text-muted)" }}>مسجلون: </span>
                        <strong style={{ color: "var(--primary)" }}>
                          {roster.length > 0 ? roster.length : bus.assignedStudentsCount} طالب
                        </strong>
                      </div>
                    </div>

                    {bus.status === "on_route" && bus.estimatedArrival && (
                      <div style={{
                        background: "#FFF7ED", border: "1px solid #FED7AA", borderRadius: "var(--radius-sm)",
                        padding: "10px 14px", marginBottom: 14, display: "flex", alignItems: "center", gap: 10, fontSize: 12, color: "#C2410C",
                      }}>
                        <Clock size={16} />
                        <span>الوصول المتوقع: <strong>{bus.estimatedArrival}</strong> — البث الحي نشط في التطبيق</span>
                      </div>
                    )}

                    {/* Roster Toggle */}
                    <div
                      onClick={() => {
                        setRosterBusId(isShowingRoster ? null : bus.id);
                        if (!isShowingRoster) void refreshTransportRouteDetails(bus.id);
                      }}
                      style={{
                        cursor: "pointer", padding: "10px 14px", borderRadius: "var(--radius-sm)",
                        background: isShowingRoster ? "var(--primary-50)" : "var(--bg-page)",
                        border: `1px solid ${isShowingRoster ? "var(--primary)" : "var(--border)"}`,
                        marginBottom: 14, display: "flex", alignItems: "center", gap: 8,
                        transition: "all 0.15s",
                      }}
                    >
                      <List size={16} color={isShowingRoster ? "var(--primary)" : "var(--text-muted)"} />
                      <span style={{ fontSize: 12.5, fontWeight: 700, color: isShowingRoster ? "var(--primary)" : "var(--text-light)" }}>
                        {isShowingRoster ? "إخفاء قائمة الطلاب ▲" : `عرض قائمة الطلاب (${livePassengers.length || roster.length || bus.assignedStudentsCount}) ▼`}
                      </span>
                    </div>

                    {isShowingRoster && (
                      <div style={{ display: "grid", gridTemplateColumns: "1fr auto", gap: 8, marginBottom: 14 }}>
                        <select
                          className="form-select"
                          value={selectedAssignmentStudentId}
                          onChange={(event) =>
                            setAssignmentStudentByRoute((prev) => ({ ...prev, [bus.id]: event.target.value }))
                          }
                        >
                          <option value="">Select student for live assignment</option>
                          {assignableStudents.map((student) => (
                            <option key={student.id} value={student.id}>
                              {student.name} - {student.studentCode}
                            </option>
                          ))}
                        </select>
                        <button
                          className="btn btn-primary btn-sm"
                          disabled={!selectedAssignmentStudentId}
                          onClick={() => {
                            void assignStudentToTransportRoute(bus.id, selectedAssignmentStudentId).then(() =>
                              setAssignmentStudentByRoute((prev) => ({ ...prev, [bus.id]: "" })),
                            );
                          }}
                        >
                          <UserPlus size={14} /> Assign
                        </button>
                      </div>
                    )}

                    {/* Inline Roster */}
                    {isShowingRoster && roster.length > 0 && (
                      <div style={{ marginBottom: 14 }}>
                        {roster.map((stu, idx) => (
                          <div
                            key={stu.id}
                            onClick={() => setProfileStudent(stu)}
                            style={{
                              display: "flex", alignItems: "center", gap: 10,
                              padding: "8px 12px", borderRadius: "var(--radius-sm)",
                              borderBottom: idx < roster.length - 1 ? "1px solid var(--border-light)" : "none",
                              cursor: "pointer", transition: "background 0.12s",
                            }}
                            onMouseEnter={e => (e.currentTarget.style.background = "var(--primary-50)")}
                            onMouseLeave={e => (e.currentTarget.style.background = "transparent")}
                          >
                            <div style={{
                              width: 30, height: 30, borderRadius: "50%",
                              background: stu.avatarColor + "20", color: stu.avatarColor,
                              display: "flex", alignItems: "center", justifyContent: "center",
                              fontSize: 10, fontWeight: 800, flexShrink: 0,
                            }}>{stu.avatarInitials}</div>
                            <div style={{ flex: 1 }}>
                              <div style={{ fontWeight: 700, fontSize: 12.5, color: "var(--text-dark)" }}>{stu.name}</div>
                              <div style={{ fontSize: 10.5, color: "var(--text-muted)" }}>{stu.sectionName}</div>
                            </div>
                            {apiStatus === "live" ? (
                              <span className="badge badge-gray" style={{ fontSize: 10 }}>بيانات المسار</span>
                            ) : (
                              <span className={`badge ${stu.riskLevel === "high" ? "badge-red" : stu.riskLevel === "medium" ? "badge-orange" : "badge-green"}`} style={{ fontSize: 10 }}>
                                <span className="dot" />{stu.riskLevel === "high" ? "خطر" : stu.riskLevel === "medium" ? "متابعة" : "منتظم"}
                              </span>
                            )}
                          </div>
                        ))}
                      </div>
                    )}

                    {isShowingRoster && roster.length === 0 && livePassengers.length > 0 && (
                      <div style={{ marginBottom: 14 }}>
                        {livePassengers.map((passenger, idx) => (
                          <div
                            key={passenger.assignment_id}
                            style={{
                              display: "flex", alignItems: "center", gap: 10,
                              padding: "8px 12px", borderRadius: "var(--radius-sm)",
                              borderBottom: idx < livePassengers.length - 1 ? "1px solid var(--border-light)" : "none",
                            }}
                          >
                            <div style={{
                              width: 30, height: 30, borderRadius: "50%",
                              background: "var(--primary-50)", color: "var(--primary)",
                              display: "flex", alignItems: "center", justifyContent: "center",
                              fontSize: 10, fontWeight: 800, flexShrink: 0,
                            }}>{(passenger.student_name ?? "ST").slice(0, 2)}</div>
                            <div style={{ flex: 1 }}>
                              <div style={{ fontWeight: 700, fontSize: 12.5, color: "var(--text-dark)" }}>{passenger.student_name}</div>
                              <div style={{ fontSize: 10.5, color: "var(--text-muted)" }}>{passenger.section_name ?? passenger.admission_number}</div>
                            </div>
                            <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                              <span style={{ fontSize: 11, color: "var(--text-light)" }}>{passenger.parent_phone}</span>
                              <button
                                className="btn btn-ghost btn-sm"
                                onClick={() => void archiveStudentTransportAssignment(bus.id, passenger.assignment_id)}
                              >
                                <Trash2 size={12} /> Remove
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}

                    {isShowingRoster && liveEvents.length > 0 && (
                      <div style={{ marginBottom: 14, fontSize: 11.5, color: "var(--text-light)", background: "var(--bg-page)", borderRadius: "var(--radius-sm)", padding: "8px 12px" }}>
                        آخر حدث: {liveEvents[0].summary} - {liveEvents[0].occurred_at}
                      </div>
                    )}

                    {isShowingRoster && roster.length === 0 && livePassengers.length === 0 && (
                      <div style={{ textAlign: "center", padding: "12px", fontSize: 12, color: "var(--text-muted)", marginBottom: 14 }}>
                        لا يوجد طلاب مرتبطون بهذا المسار حالياً
                      </div>
                    )}

                    <div style={{ display: "flex", gap: 10 }}>
                      <button
                        onClick={() => handleDriverContact(bus.id, bus.driverName, bus.driverPhone)}
                        className="btn btn-outline btn-sm"
                        style={{ flex: 1, justifyContent: "center" }}
                      >
                        <Phone size={14} /> اتصال بالسائق
                      </button>
                      {bus.status === "on_route" && (
                        <button
                          onClick={() => handleEmergencyAlert(bus.id, bus.routeName)}
                          className="btn btn-sm"
                          style={{ background: "#FEF2F2", color: "#DC2626", border: "1px solid #FECACA", justifyContent: "center" }}
                        >
                          <AlertTriangle size={14} /> تنبيه تأخر
                        </button>
                      )}
                      <button
                        onClick={() => handleUpdateRouteCapacity(bus.id, bus.assignedStudentsCount)}
                        className="btn btn-ghost btn-sm"
                        title="Update route capacity"
                      >
                        <Save size={14} />
                      </button>
                      <button
                        onClick={() => handleArchiveRoute(bus.id, bus.routeName)}
                        className="btn btn-ghost btn-sm"
                        title="Archive route"
                        style={{ color: "var(--danger)" }}
                      >
                        <Archive size={14} />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* GPS Map Panel */}
            <div className="card" style={{ display: "flex", flexDirection: "column", height: "fit-content" }}>
              <div className="card-header">
                <div>
                  <div className="card-title">خريطة التتبع اللحظي للحافلات</div>
                  <div className="card-subtitle">متزامنة مع شاشة التتبع في تطبيق ولي الأمر</div>
                </div>
                <span className="badge badge-green"><span className="dot" />GPS نشط</span>
              </div>
              <div style={{
                minHeight: 320, background: "#E2E8F0", borderRadius: "var(--radius)",
                position: "relative", overflow: "hidden",
                backgroundImage: "radial-gradient(#CBD5E1 1px, transparent 1px)",
                backgroundSize: "20px 20px",
              }}>
                {/* Simulated streets */}
                <div style={{ position: "absolute", width: "100%", height: 28, background: "#94A3B8", top: "42%", transform: "rotate(-8deg)", opacity: 0.6 }} />
                <div style={{ position: "absolute", width: 28, height: "100%", background: "#94A3B8", left: "44%", opacity: 0.6 }} />
                <div style={{ position: "absolute", width: "70%", height: 22, background: "#94A3B8", top: "72%", left: "10%", transform: "rotate(3deg)", opacity: 0.5 }} />

                {/* School Pin */}
                <div style={{ position: "absolute", top: "36%", left: "41%", background: "white", padding: "6px 12px", borderRadius: 20, boxShadow: "0 4px 14px rgba(0,0,0,0.2)", display: "flex", alignItems: "center", gap: 6, zIndex: 3 }}>
                  <span style={{ fontSize: 18 }}>🏫</span>
                  <span style={{ fontSize: 11, fontWeight: 800 }}>مدارس EduBridge</span>
                </div>

                {/* Buses on route */}
                {busRoutes.filter(b => b.status === "on_route").map((bus, i) => (
                  <div key={bus.id} style={{
                    position: "absolute", top: `${52 + i * 12}%`, left: `${62 + i * 8}%`,
                    background: "var(--warning)", color: "white", padding: "7px 13px", borderRadius: 25,
                    boxShadow: "0 6px 20px rgba(245,158,11,0.5)", display: "flex", alignItems: "center", gap: 8,
                    animation: "gpsWave 2s infinite", zIndex: 4, fontSize: 12, fontWeight: 800,
                  }}>
                    <Bus size={16} />
                    <span>{bus.routeName.replace("مسار ", "")}</span>
                  </div>
                ))}

                {/* Arrived buses */}
                {busRoutes.filter(b => b.status === "arrived").map((bus, i) => (
                  <div key={bus.id} style={{
                    position: "absolute", top: `${25 + i * 10}%`, left: `${15 + i * 15}%`,
                    background: "var(--green)", color: "white", padding: "6px 12px", borderRadius: 25,
                    display: "flex", alignItems: "center", gap: 6, zIndex: 4, fontSize: 11, fontWeight: 700, opacity: 0.85,
                  }}>
                    <Bus size={14} /> <span>{bus.routeName.replace("مسار ", "")} ✓</span>
                  </div>
                ))}

                <style jsx>{`
                  @keyframes gpsWave {
                    0%, 100% { transform: translateY(0) scale(1); }
                    50% { transform: translateY(-5px) scale(1.03); }
                  }
                `}</style>
              </div>
              <div style={{ marginTop: 14, fontSize: 12, color: "var(--text-light)", textAlign: "center", padding: "0 16px 16px" }}>
                💡 يُرسَل إشعار تلقائي لولي الأمر قبل اقتراب الحافلة من المنزل بـ 5 دقائق
              </div>

              {/* Students without bus */}
              <div style={{ padding: "14px 20px", borderTop: "1px solid var(--border-light)" }}>
                <div style={{ fontSize: 12, fontWeight: 700, color: "var(--text-dark)", marginBottom: 8 }}>
                  🚗 طلاب غير مسجلين على الحافلات ({students.filter(s => !s.busRouteId).length} طالب)
                </div>
                <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
                  {students.filter(s => !s.busRouteId).map(s => (
                    <div
                      key={s.id}
                      onClick={() => setProfileStudent(s)}
                      style={{
                        display: "flex", alignItems: "center", gap: 5,
                        padding: "4px 10px", borderRadius: 16,
                        background: s.avatarColor + "15", cursor: "pointer",
                        border: `1px solid ${s.avatarColor}25`,
                      }}
                    >
                      <div style={{ width: 18, height: 18, borderRadius: "50%", background: s.avatarColor + "30", color: s.avatarColor, display: "flex", alignItems: "center", justifyContent: "center", fontSize: 7, fontWeight: 800 }}>{s.avatarInitials}</div>
                      <span style={{ fontSize: 11, fontWeight: 700, color: s.avatarColor }}>{s.name.split(" ")[0]}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </main>
        <Footer />
      </div>

      <StudentProfileModal student={profileStudent} onClose={() => setProfileStudent(null)} />
    </div>
  );
}
