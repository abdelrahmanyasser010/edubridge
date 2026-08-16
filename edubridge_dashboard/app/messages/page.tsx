"use client";

import React, { useEffect, useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import type { DashboardCalendarEvent } from "@/lib/dashboardApi";
import { Send, Users, Megaphone, Star, Bell, Clock, FileText, Calendar, Plus, MapPin, CheckCircle, AlertCircle } from "lucide-react";

function TypeBadge({ type }: { type: string }) {
  const map: Record<string, { cls: string; icon: React.ReactNode }> = {
    "تعميم": { cls: "badge-blue", icon: <Megaphone size={10} /> },
    "تنبيه": { cls: "badge-red", icon: <Bell size={10} /> },
    "تهنئة": { cls: "badge-green", icon: <Star size={10} /> },
  };
  const { cls, icon } = map[type] || { cls: "badge-gray", icon: null };
  return <span className={`badge ${cls}`}>{icon}{type}</span>;
}

interface SchoolEvent {
  id: string;
  title: string;
  date: string;
  time: string;
  location: string;
  category: "قياس واختبارات" | "مجالس آباء" | "رحلات وفعاليات" | "عطلات إدارية";
  target: string;
  status: "مجدول" | "قائم الآن" | "مكتمل";
}

const initialEvents: SchoolEvent[] = [
  {
    id: "e1",
    title: "يوم القياس والتقييم المعياري الوطني",
    date: "2026-07-15",
    time: "08:00 صباحاً - 11:00 صباحاً",
    location: "قاعات الاختبارات المركزية",
    category: "قياس واختبارات",
    target: "الصفوف الرابعة والخامسة والسادسة",
    status: "مجدول"
  },
  {
    id: "e2",
    title: "مجلس الآباء والمعلمين الأول لمناقشة الأداء",
    date: "2026-07-18",
    time: "05:00 مساءً - 08:00 مساءً",
    location: "مسرح المدرسة + بث مباشر عبر التطبيق",
    category: "مجالس آباء",
    target: "جميع أولياء الأمور",
    status: "مجدول"
  },
  {
    id: "e3",
    title: "الرحلة العلمية إلى واحة الملك سلمان للعلوم",
    date: "2026-07-22",
    time: "07:30 صباحاً - 01:30 ظهراً",
    location: "الرياض — حي الرائد",
    category: "رحلات وفعاليات",
    target: "الصف الخامس (شعبة أ + ب)",
    status: "مجدول"
  },
  {
    id: "e4",
    title: "ورشة عمل تدريبية للمعلمين: أدوات حوكمة التقييم",
    date: "2026-07-05",
    time: "01:00 ظهراً - 03:00 عصراً",
    location: "مركز التطوير المهني بالمدرسة",
    category: "عطلات إدارية",
    target: "جميع الكوادر التعليمية",
    status: "مكتمل"
  }
];

function datePart(value?: string | null) {
  if (!value) return "";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value.split("T")[0] ?? "";
  return parsed.toISOString().split("T")[0] ?? "";
}

function timePart(value?: string | null) {
  if (!value) return "";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "";
  return parsed.toLocaleTimeString("ar-EG", { hour: "2-digit", minute: "2-digit" });
}

function mapCalendarCategory(type?: string | null): SchoolEvent["category"] {
  if (type === "exam" || type === "deadline") return initialEvents[0].category;
  if (type === "meeting") return initialEvents[1].category;
  if (type === "holiday") return initialEvents[3].category;
  return initialEvents[2].category;
}

function mapCalendarStatus(status?: string | null): SchoolEvent["status"] {
  return status === "completed" || status === "cancelled" ? initialEvents[3].status : initialEvents[0].status;
}

function mapDashboardCalendarEvents(events: DashboardCalendarEvent[]): SchoolEvent[] {
  return events.map((event) => {
    const startsAt = timePart(event.starts_at);
    const endsAt = timePart(event.ends_at);
    return {
      id: event.id,
      title: event.title || "\u0641\u0639\u0627\u0644\u064a\u0629 \u0645\u062f\u0631\u0633\u064a\u0629",
      date: datePart(event.starts_at),
      time: event.all_day ? "\u0637\u0648\u0627\u0644 \u0627\u0644\u064a\u0648\u0645" : [startsAt, endsAt].filter(Boolean).join(" - "),
      location: event.location || "\u062d\u0631\u0645 \u0627\u0644\u0645\u062f\u0631\u0633\u0629",
      category: mapCalendarCategory(event.type),
      target: event.audience_type || "\u0627\u0644\u062c\u0645\u064a\u0639",
      status: mapCalendarStatus(event.status),
    };
  });
}

export default function MessagesPage() {
  const {
    messages, sendBroadcast, scheduleBroadcast, showToast, broadcasts, apiStatus,
    calendarEvents, createSchoolCalendarEvent, updateSchoolCalendarEvent, deleteSchoolCalendarEvent,
    broadcastDeliveries, cancelDashboardBroadcast, loadBroadcastDeliveries,
  } = useDashboard();
  const [activeTab, setActiveTab] = useState<"broadcasts" | "calendar">("broadcasts");
  
  // Broadcast form state
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [target, setTarget] = useState("جميع أولياء الأمور");
  const [type, setType] = useState<"تعميم" | "تنبيه" | "تهنئة">("تعميم");

  // Calendar state
  const [events, setEvents] = useState<SchoolEvent[]>([]);
  const [eventTitle, setEventTitle] = useState("");
  const [eventDate, setEventDate] = useState("");
  const [eventTime, setEventTime] = useState("");
  const [eventLocation, setEventLocation] = useState("");
  const [eventCategory, setEventCategory] = useState<SchoolEvent["category"]>("مجالس آباء");
  const [eventTarget, setEventTarget] = useState("جميع أولياء الأمور");
  const [showAddEvent, setShowAddEvent] = useState(false);

  useEffect(() => {
    if (apiStatus === "live") {
      setEvents(mapDashboardCalendarEvents(calendarEvents));
      return;
    }
    if (apiStatus === "mock") {
      setEvents(initialEvents);
    }
  }, [apiStatus, calendarEvents]);

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!title.trim() || !body.trim()) {
      showToast("تنبيه", "يرجى كتابة عنوان ونص الرسالة أولاً.", "warning");
      return;
    }
    sendBroadcast(title, body, target, type);
    setTitle("");
    setBody("");
  };

  const handleSchedule = () => {
    if (!title.trim() || !body.trim()) {
      showToast("تنبيه", "يرجى كتابة عنوان ونص الرسالة أولاً.", "warning");
      return;
    }

    const scheduled = new Date();
    scheduled.setDate(scheduled.getDate() + 1);
    scheduled.setHours(7, 0, 0, 0);
    scheduleBroadcast(title, body, target, type, scheduled.toISOString());
    setTitle("");
    setBody("");
  };

  const handleEventReminder = (event: SchoolEvent) => {
    sendBroadcast(
      `تذكير: ${event.title}`,
      `${event.title}\n${event.date} - ${event.time}\n${event.location}`,
      event.target,
      "تنبيه",
    );
  };

  const handleTemplate = () => {
    setTitle("تنبيه هام: موعد انصراف مبكر لاختبارات الفترة الأولى");
    setBody("نحيطكم علماً بأن انصراف الطلاب يوم الخميس الموافق 10 يوليو سيكون في تمام الساعة 11:30 صباحاً، نرجو الالتزام بالحضور لاستلام الأبناء في الموعد المحدد أو متابعة تتبع الحافلة عبر التطبيق.");
    setType("تنبيه");
    showToast("تم إدراج القالب الجاهز 📝", "يمكنك التعديل على النص الآن قبل إرساله.", "info");
  };

  const handleAddEvent = (e: React.FormEvent) => {
    e.preventDefault();
    if (!eventTitle.trim() || !eventDate.trim()) {
      showToast("خطأ في الإدخال", "يرجى تحديد اسم الفعالية وتاريخها على الأقل.", "warning");
      return;
    }
    const newEv: SchoolEvent = {
      id: "e-" + Date.now(),
      title: eventTitle,
      date: eventDate,
      time: eventTime || "طوال اليوم",
      location: eventLocation || "حرم المدرسة",
      category: eventCategory,
      target: eventTarget,
      status: "مجدول"
    };

    if (apiStatus === "live") {
      void createSchoolCalendarEvent({
        title: newEv.title,
        date: newEv.date,
        time: eventTime,
        location: newEv.location,
        category: newEv.category,
        target: newEv.target,
      }).catch((error) => showToast("تعذر إضافة الفعالية", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
    } else {
      setEvents([newEv, ...events]);
      showToast("وضع تجريبي", "تمت إضافة الفعالية محلياً فقط.", "info");
    }

    setEventTitle("");
    setEventDate("");
    setEventTime("");
    setEventLocation("");
    setShowAddEvent(false);
  };

  const handleEditEvent = (event: SchoolEvent) => {
    const nextTitle = window.prompt("تعديل عنوان الفعالية", event.title);
    if (!nextTitle?.trim()) return;
    if (apiStatus === "live") {
      void updateSchoolCalendarEvent(event.id, { title: nextTitle })
        .catch((error) => showToast("تعذر تعديل الفعالية", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
      return;
    }
    setEvents((prev) => prev.map((item) => (item.id === event.id ? { ...item, title: nextTitle } : item)));
  };

  const handleDeleteEvent = (event: SchoolEvent) => {
    if (apiStatus === "live") {
      void deleteSchoolCalendarEvent(event.id)
        .catch((error) => showToast("تعذر حذف الفعالية", error instanceof Error ? error.message : "تعذر الاتصال بالخادم.", "error"));
      return;
    }
    setEvents((prev) => prev.filter((item) => item.id !== event.id));
  };

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header 
          title="مركز الاتصال والتقويم المدرسي" 
          subtitle={`Broadcast API: ${apiStatus} - ${broadcasts.length || messages.length} records`}
        />
        <main className="page-body">

          {/* Navigation Tabs */}
          <div style={{ display: "flex", gap: 10, marginBottom: 20, borderBottom: "1px solid var(--border)", paddingBottom: 12 }}>
            <button
              onClick={() => setActiveTab("broadcasts")}
              className={`btn ${activeTab === "broadcasts" ? "btn-primary" : "btn-ghost"}`}
              style={{ display: "flex", alignItems: "center", gap: 8, padding: "0 18px" }}
            >
              <Megaphone size={16} /> التعاميم والإشعارات (داخل النظام / Push)
              <span className="badge badge-blue" style={{ background: activeTab === "broadcasts" ? "rgba(255,255,255,0.2)" : undefined, color: activeTab === "broadcasts" ? "white" : undefined }}>{messages.length}</span>
            </button>
            <button
              onClick={() => setActiveTab("calendar")}
              className={`btn ${activeTab === "calendar" ? "btn-primary" : "btn-ghost"}`}
              style={{ display: "flex", alignItems: "center", gap: 8, padding: "0 18px" }}
            >
              <Calendar size={16} /> التقويم المدرسي والفعاليات
              <span className="badge badge-green" style={{ background: activeTab === "calendar" ? "rgba(255,255,255,0.2)" : undefined, color: activeTab === "calendar" ? "white" : undefined }}>{events.length}</span>
            </button>
          </div>

          {activeTab === "broadcasts" ? (
            <>
              {/* Compose Box */}
              <form onSubmit={handleSend} className="card" style={{ marginBottom: 20 }}>
                <div className="card-header">
                  <div className="card-title">كتابة رسالة أو تعميم جديد</div>
                  <span className="badge badge-blue">📱 تصل مباشرة إلى تطبيق ولي الأمر والهاتف</span>
                </div>
                <div className="card-body">
                  <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 12, marginBottom: 12 }}>
                    <div className="form-group" style={{ marginBottom: 0 }}>
                      <label className="form-label">عنوان الرسالة</label>
                      <input
                        required
                        type="text"
                        className="form-input"
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="مثال: تنبيه بموعد الانصراف المبكر..."
                      />
                    </div>
                    <div className="form-group" style={{ marginBottom: 0 }}>
                      <label className="form-label">الفئة المستهدفة</label>
                      <select
                        className="form-select"
                        value={target}
                        onChange={(e) => setTarget(e.target.value)}
                      >
                        <option>جميع أولياء الأمور</option>
                        <option>الصف الخامس / شعبة أ</option>
                        <option>الصف الخامس / شعبة ب</option>
                        <option>الصف السادس / شعبة أ</option>
                        <option>الصف الرابع</option>
                        <option>جميع المعلمين</option>
                      </select>
                    </div>
                    <div className="form-group" style={{ marginBottom: 0 }}>
                      <label className="form-label">نوع الرسالة</label>
                      <select
                        className="form-select"
                        value={type}
                        onChange={(e) => setType(e.target.value as any)}
                      >
                        <option value="تعميم">تعميم رسمي (إداري)</option>
                        <option value="تنبيه">تنبيه هام أو عاجل</option>
                        <option value="تهنئة">رسالة شكر وتقدير</option>
                      </select>
                    </div>
                  </div>
                  <div className="form-group">
                    <label className="form-label">نص الرسالة</label>
                    <textarea
                      required
                      rows={3}
                      className="form-textarea"
                      value={body}
                      onChange={(e) => setBody(e.target.value)}
                      placeholder="اكتب نص الرسالة هنا ليصل إلى المستهدفين عبر إشعارات التطبيق..."
                    />
                  </div>
                  <div style={{ display: "flex", gap: 8 }}>
                    <button type="submit" className="btn btn-primary">
                      <Send size={14} /> إرسال الرسالة الآن 📤
                    </button>
                    <button
                      type="button"
                      onClick={handleSchedule}
                      className="btn btn-ghost"
                    >
                      <Clock size={14} /> جدولة الإرسال
                    </button>
                    <button type="button" onClick={handleTemplate} className="btn btn-outline">
                      <FileText size={14} /> استخدام قالب جاهز
                    </button>
                  </div>
                </div>
              </form>

              {/* Messages History */}
              <div className="card">
                <div className="card-header">
                  <div className="card-title">سجل الرسائل والتعاميم السابقة</div>
                  <div className="card-subtitle">الرسائل التي تم إرسالها سابقاً وإحصائيات وصولها ({messages.length} رسائل)</div>
                </div>
                <div>
                  {messages.map((msg, idx) => (
                    <div key={msg.id} className="feed-item" style={{ borderBottom: idx < messages.length - 1 ? "1px solid var(--border-light)" : "none" }}>
                      <div style={{
                        width: 44, height: 44, borderRadius: "var(--radius)",
                        background: msg.type === "تهنئة" ? "var(--green-50)" : msg.type === "تنبيه" ? "var(--danger-50)" : "var(--primary-50)",
                        display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0,
                      }}>
                        {msg.type === "تعميم" ? <Megaphone size={20} color="var(--primary)" /> : msg.type === "تنبيه" ? <Bell size={20} color="var(--danger)" /> : <Star size={20} color="var(--green)" />}
                      </div>
                      <div style={{ flex: 1 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4, flexWrap: "wrap" }}>
                          <span style={{ fontWeight: 800, fontSize: 14, color: "var(--text-dark)" }}>{msg.title}</span>
                          <TypeBadge type={msg.type} />
                        </div>
                        <div style={{ fontSize: 13, color: "var(--text-light)", lineHeight: 1.7, marginBottom: 8 }}>{msg.body}</div>
                        <div style={{ display: "flex", gap: 16, fontSize: 11.5, color: "var(--text-muted)", flexWrap: "wrap" }}>
                          <span><Users size={11} style={{ display: "inline", verticalAlign: "middle" }} /> {msg.target}</span>
                          <span>📤 {msg.sentBy}</span>
                          <span>📅 {msg.date}</span>
                          <span style={{ color: "var(--green)", fontWeight: 700 }}>✓ وصلت إلى {msg.reachCount} هاتف</span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              {broadcasts.length > 0 && (
                <div className="card" style={{ marginTop: 20 }}>
                  <div className="card-header">
                    <div>
                      <div className="card-title">Live broadcast lifecycle</div>
                      <div className="card-subtitle">GET /dashboard/broadcasts, POST cancel, GET deliveries</div>
                    </div>
                  </div>
                  <div>
                    {broadcasts.slice(0, 8).map((broadcast, index) => {
                      const deliveries = broadcastDeliveries[broadcast.id];
                      return (
                        <div key={broadcast.id} className="feed-item" style={{ borderBottom: index < Math.min(broadcasts.length, 8) - 1 ? "1px solid var(--border-light)" : "none" }}>
                          <div style={{ flex: 1 }}>
                            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center", marginBottom: 5 }}>
                              <strong>{broadcast.title ?? broadcast.id}</strong>
                              <span className="badge badge-blue">{broadcast.status ?? "draft"}</span>
                              <span className="badge badge-gray">{broadcast.type ?? "announcement"}</span>
                            </div>
                            <div style={{ fontSize: 12, color: "var(--text-light)" }}>
                              {broadcast.target_label ?? broadcast.target?.type ?? "all"} - reach {broadcast.reach_count ?? 0}
                              {deliveries && ` - sent ${deliveries.sent}, failed ${deliveries.failed}, read ${deliveries.read}`}
                            </div>
                          </div>
                          <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                            <button className="btn btn-outline btn-sm" onClick={() => void loadBroadcastDeliveries(broadcast.id)}>
                              Deliveries
                            </button>
                            {!broadcast.sent_at && !broadcast.cancelled_at && broadcast.status !== "cancelled" && (
                              <button className="btn btn-ghost btn-sm" onClick={() => void cancelDashboardBroadcast(broadcast.id)}>
                                Cancel
                              </button>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )}
            </>
          ) : (
            <>
              {/* Calendar Controls Bar */}
              <div className="card" style={{ marginBottom: 20, padding: "16px 20px" }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", flexWrap: "wrap", gap: 14 }}>
                  <div>
                    <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>الجدول الزمني للفعاليات والمواعيد الهامة</div>
                    <div style={{ fontSize: 12, color: "var(--text-muted)", marginTop: 2 }}>تظهر المواعيد المضافة هنا تلقائياً في تقويم تطبيق ولي الأمر والمعلم</div>
                  </div>
                  <button onClick={() => setShowAddEvent(!showAddEvent)} className="btn btn-green">
                    <Plus size={16} /> {showAddEvent ? "إلغاء الإضافة" : "إدراج فعالية جديدة في التقويم"}
                  </button>
                </div>

                {/* Add Event Inline Form */}
                {showAddEvent && (
                  <form onSubmit={handleAddEvent} style={{ marginTop: 18, paddingTop: 18, borderTop: "1px dashed var(--border)", display: "grid", gridTemplateColumns: "1fr 1fr", gap: 14 }}>
                    <div className="form-group">
                      <label className="form-label">عنوان الفعالية أو الموعد</label>
                      <input required type="text" className="form-input" placeholder="مثال: مجلس الآباء والمعلمين للفصل الدراسي الثاني" value={eventTitle} onChange={e => setEventTitle(e.target.value)} />
                    </div>
                    <div className="form-group">
                      <label className="form-label">تصنيف الفعالية</label>
                      <select className="form-select" value={eventCategory} onChange={e => setEventCategory(e.target.value as any)}>
                        <option value="قياس واختبارات">قياس واختبارات معيارية</option>
                        <option value="مجالس آباء">مجالس الآباء واللقاءات</option>
                        <option value="رحلات وفعاليات">رحلات وفعاليات طلابية</option>
                        <option value="عطلات إدارية">عطلات وتطوير مهني</option>
                      </select>
                    </div>
                    <div className="form-group">
                      <label className="form-label">التاريخ والموعد</label>
                      <div style={{ display: "flex", gap: 8 }}>
                        <input required type="date" className="form-input" value={eventDate} onChange={e => setEventDate(e.target.value)} style={{ flex: 1.2 }} />
                        <input type="text" className="form-input" placeholder="09:00 صباحاً" value={eventTime} onChange={e => setEventTime(e.target.value)} style={{ flex: 0.8 }} />
                      </div>
                    </div>
                    <div className="form-group">
                      <label className="form-label">المكان والجمهور المستهدف</label>
                      <div style={{ display: "flex", gap: 8 }}>
                        <input type="text" className="form-input" placeholder="المكان (مثال: المسرح)" value={eventLocation} onChange={e => setEventLocation(e.target.value)} style={{ flex: 1 }} />
                        <input type="text" className="form-input" placeholder="المستهدفون (مثال: أولياء الأمور)" value={eventTarget} onChange={e => setEventTarget(e.target.value)} style={{ flex: 1 }} />
                      </div>
                    </div>
                    <div style={{ gridColumn: "span 2", display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 4 }}>
                      <button type="submit" className="btn btn-primary" style={{ padding: "0 24px" }}><CheckCircle size={14} /> اعتماد الفعالية في التقويم</button>
                    </div>
                  </form>
                )}
              </div>

              {/* Events List */}
              <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: 14 }}>
                {events.map((ev) => {
                  const catColors: Record<string, { bg: string; color: string; border: string }> = {
                    "قياس واختبارات": { bg: "#EFF6FF", color: "#1D4ED8", border: "#BFDBFE" },
                    "مجالس آباء": { bg: "#F0FDF4", color: "#15803D", border: "#BBF7D0" },
                    "رحلات وفعاليات": { bg: "#FFF7ED", color: "#C2410C", border: "#FED7AA" },
                    "عطلات إدارية": { bg: "#F8F4FF", color: "#6D28D9", border: "#DDD6FE" },
                  };
                  const { bg, color, border } = catColors[ev.category] || { bg: "var(--bg-page)", color: "var(--text-dark)", border: "var(--border)" };

                  return (
                    <div key={ev.id} className="card" style={{ padding: "18px 20px", borderLeft: `5px solid ${color}` }}>
                      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10, flexWrap: "wrap", gap: 8 }}>
                        <div>
                          <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 4 }}>
                            <span style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>{ev.title}</span>
                            <span style={{ background: bg, color: color, border: `1px solid ${border}`, padding: "2px 10px", borderRadius: 12, fontSize: 11, fontWeight: 700 }}>{ev.category}</span>
                          </div>
                          <div style={{ fontSize: 12.5, color: "var(--text-muted)", display: "flex", gap: 16, flexWrap: "wrap", marginTop: 6 }}>
                            <span style={{ display: "flex", alignItems: "center", gap: 5 }}><Calendar size={14} color="var(--primary)" /> <strong>{ev.date}</strong> ({ev.time})</span>
                            <span style={{ display: "flex", alignItems: "center", gap: 5 }}><MapPin size={14} color="var(--text-light)" /> {ev.location}</span>
                            <span style={{ display: "flex", alignItems: "center", gap: 5 }}><Users size={14} color="var(--text-light)" /> {ev.target}</span>
                          </div>
                        </div>
                        <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                          <span className={`badge ${ev.status === "مكتمل" ? "badge-gray" : "badge-green"}`}>
                            <span className="dot" /> {ev.status === "مكتمل" ? "مكتمل في الأرشيف" : "نشط ومجدول في التطبيق"}
                          </span>
                          <button
                            onClick={() => handleEventReminder(ev)}
                            className="btn btn-outline btn-sm"
                            style={{ fontSize: 11 }}
                          >
                            إرسال تذكير للأهالي
                          </button>
                          <button
                            onClick={() => handleEditEvent(ev)}
                            className="btn btn-ghost btn-sm"
                            style={{ fontSize: 11 }}
                          >
                            تعديل
                          </button>
                          <button
                            onClick={() => handleDeleteEvent(ev)}
                            className="btn btn-ghost btn-sm"
                            style={{ fontSize: 11 }}
                          >
                            إلغاء
                          </button>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}

        </main>
        <Footer />
      </div>
    </div>
  );
}
