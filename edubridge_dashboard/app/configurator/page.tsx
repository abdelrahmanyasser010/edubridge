"use client";

import React, { useState, useRef, useCallback, useEffect } from "react";
import Sidebar from "@/components/Sidebar";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import {
  Network, Plus, Trash2, ZoomIn, ZoomOut, Maximize,
  AlertTriangle, CheckCircle, Download, Save, Info,
  GraduationCap, Users, Bus, BookOpen, Shield, X,
  ChevronLeft, Layers, Check, ArrowRight, Sparkles, Filter, Link2,
  MapPin, SortAsc, HelpCircle, CheckSquare, ListFilter, UserPlus, Phone,
  Settings, ArrowDownRight
} from "lucide-react";

// ─── Types ───────────────────────────────────────────────────────────────────

type NodeType = "section" | "bus";

interface CanvasNode {
  id: string;
  type: NodeType;
  x: number;
  y: number;
  label: string;
  sublabel?: string;
  color: string;
  gradeLevel?: string;
  neighborhood?: string;
  roomNumber?: string;
  teachersCount: number;
  studentsCount: number;
  parentsCount: number;
  assignedTeachers: string[];
  assignedStudents: string[];
  driverName?: string;
  plateNumber?: string;
}

interface Connection {
  id: string;
  fromId: string;
  toId: string;
  color: string;
}

const NEIGHBORHOODS = ["حي الياسمين", "حي النزهة", "حي العليا", "حي السليمانية", "حي الملقا"];

// ─── Helpers ─────────────────────────────────────────────────────────────────

let nodeSeq = 3000;
const newId = () => `node_${++nodeSeq}_${Date.now()}`;

function bezierPath(x1: number, y1: number, x2: number, y2: number): string {
  const dx = Math.abs(x2 - x1) * 0.5;
  return `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function ConfiguratorPage() {
  const {
    teachers, students, sections, busRoutes, showToast,
    canvasConfig, saveConfiguratorCanvas,
  } = useDashboard();

  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  // ── Top Level Directional Mode ──────────────────────────────────────────
  const [mainTab, setMainTab] = useState<"wizard" | "canvas">("canvas");
  const [wizardStep, setWizardStep] = useState<1 | 2 | 3 | 4>(1);

  // ── Canvas State ──────────────────────────────────────────────────────────
  const [nodes, setNodes] = useState<CanvasNode[]>([]);
  const [connections, setConnections] = useState<Connection[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [connectingFromId, setConnectingFromId] = useState<string | null>(null);
  const [zoom, setZoom] = useState(0.9);
  const [pan, setPan] = useState({ x: 30, y: 30 });
  const [draggingId, setDraggingId] = useState<string | null>(null);
  const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 });
  const [isPanning, setIsPanning] = useState(false);
  const [panStart, setPanStart] = useState({ x: 0, y: 0 });

  // ── Smart Filters & Sorting State ───────────────────────────────────────
  const [gradeFilter, setGradeFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<"default" | "neighborhood" | "name">("default");
  const [activeToolboxTab, setActiveToolboxTab] = useState<"sections" | "buses">("sections");

  const availableGradeLevels = Array.from(new Set(sections.map(s => s.gradeLevel).filter(Boolean))) as string[];

  // ── Quick Add Form State in Wizard ──────────────────────────────────────
  const [newSectionName, setNewSectionName] = useState("");
  const [newSectionGrade, setNewSectionGrade] = useState(availableGradeLevels[0] || "الصف الأول");
  const [newTeacherName, setNewTeacherName] = useState("");
  const [newTeacherSpec, setNewTeacherSpec] = useState("لغة عربية");
  const [newStudentName, setNewStudentName] = useState("");
  const [newStudentParent, setNewStudentParent] = useState("");
  const [newStudentNeighborhood, setNewStudentNeighborhood] = useState("حي الياسمين");
  const [newBusRoute, setNewBusRoute] = useState("");

  const containerRef = useRef<HTMLDivElement>(null);

  // ── Derived & Filtered ────────────────────────────────────────────────────
  const selectedNode = nodes.find(n => n.id === selectedId) ?? null;

  const filteredSections = sections.filter(sec => gradeFilter === "all" || sec.gradeLevel === gradeFilter);

  const displayNodes = nodes.filter(n => {
    if (gradeFilter === "all") return true;
    if (n.type === "section" && n.gradeLevel) return n.gradeLevel === gradeFilter;
    if (n.type === "bus") return true;
    return true;
  });

  const sortedDisplayNodes = [...displayNodes].sort((a, b) => {
    if (sortBy === "neighborhood") {
      return (a.neighborhood || "z").localeCompare(b.neighborhood || "z", "ar");
    }
    if (sortBy === "name") {
      return a.label.localeCompare(b.label, "ar");
    }
    return 0;
  });

  // ── DOM coordinate helper ─────────────────────────────────────────────────
  const domCoords = useCallback((clientX: number, clientY: number) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return { x: 0, y: 0 };
    return {
      x: (clientX - rect.left - pan.x) / zoom,
      y: (clientY - rect.top  - pan.y) / zoom,
    };
  }, [pan, zoom]);

  // ── Drag Logic ────────────────────────────────────────────────────────────
  const handleNodeMouseDown = (e: React.MouseEvent, id: string) => {
    e.stopPropagation();
    const node = nodes.find(n => n.id === id);
    if (!node) return;
    const { x, y } = domCoords(e.clientX, e.clientY);
    setDraggingId(id);
    setDragOffset({ x: x - node.x, y: y - node.y });
    setSelectedId(id);
  };

  const handleMouseMove = useCallback((e: MouseEvent) => {
    if (draggingId) {
      const { x, y } = domCoords(e.clientX, e.clientY);
      const newX = Math.round((x - dragOffset.x) / 10) * 10;
      const newY = Math.round((y - dragOffset.y) / 10) * 10;
      setNodes(prev => prev.map(n => n.id === draggingId ? { ...n, x: newX, y: newY } : n));
    } else if (isPanning) {
      const dx = e.clientX - panStart.x;
      const dy = e.clientY - panStart.y;
      setPan(p => ({ x: p.x + dx / 5, y: p.y + dy / 5 }));
    }
  }, [draggingId, dragOffset, isPanning, panStart, domCoords]);

  const handleMouseUp = useCallback(() => {
    setDraggingId(null);
    setIsPanning(false);
  }, []);

  useEffect(() => {
    window.addEventListener("mousemove", handleMouseMove);
    window.addEventListener("mouseup", handleMouseUp);
    return () => {
      window.removeEventListener("mousemove", handleMouseMove);
      window.removeEventListener("mouseup", handleMouseUp);
    };
  }, [handleMouseMove, handleMouseUp]);

  const handleCanvasMouseDown = (e: React.MouseEvent) => {
    if ((e.target as HTMLElement).classList.contains("canvas-bg") || (e.target as HTMLElement).tagName === "svg" || (e.target as HTMLElement).tagName === "rect") {
      setSelectedId(null);
      setConnectingFromId(null);
      setIsPanning(true);
      setPanStart({ x: e.clientX, y: e.clientY });
    }
  };

  // ── Drop from Toolbox ─────────────────────────────────────────────────────
  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    const type = e.dataTransfer.getData("nodeType") as NodeType;
    const labelData = e.dataTransfer.getData("nodeLabel");
    const sublabelData = e.dataTransfer.getData("nodeSublabel");
    const gradeData = e.dataTransfer.getData("nodeGrade");
    const neighborData = e.dataTransfer.getData("nodeNeighbor");
    const countData = e.dataTransfer.getData("nodeCount");
    if (!type) return;

    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return;
    const x = Math.round(((e.clientX - rect.left - pan.x) / zoom - 140) / 10) * 10;
    const y = Math.round(((e.clientY - rect.top  - pan.y) / zoom - 70) / 10) * 10;

    const id = newId();
    const newNode: CanvasNode = {
      id, type, x, y,
      label: labelData || (type === "section" ? "شعبة جديدة" : "حافلة جديدة"),
      sublabel: sublabelData || "",
      color: type === "section" ? "#2563EB" : "#D97706",
      gradeLevel: gradeData || "الصف الخامس",
      neighborhood: neighborData || NEIGHBORHOODS[Math.floor(Math.random() * NEIGHBORHOODS.length)],
      roomNumber: "10" + Math.floor(Math.random() * 9),
      teachersCount: 2,
      studentsCount: countData ? parseInt(countData) : 33,
      parentsCount: countData ? parseInt(countData) : 33,
      assignedTeachers: ["الأستاذ سامي العتيبي", "الأستاذة نورة الشمري"],
      assignedStudents: ["فهد عبدالعزيز", "خالد عبدالله", "ريم محمد", "سارة خالد", "عمر راشد"],
      driverName: type === "bus" ? "صالح الرشيدي" : undefined,
      plateNumber: type === "bus" ? "أ ب ج 1234" : undefined,
    };

    setNodes(prev => [...prev, newNode]);
    setSelectedId(id);
    showToast("تم إدراج الوحدة 🏗️", `تم إضافة "${newNode.label}" إلى الشاشة بدون أي تداخل نصوص!`, "success");
  };

  // ── Easy Interactive Linking ─────────────────────────────────────────────
  const handleConnectClick = (e: React.MouseEvent, id: string) => {
    e.stopPropagation();
    if (!connectingFromId) {
      setConnectingFromId(id);
      const fromNode = nodes.find(n => n.id === id);
      showToast("وضع ربط الفصل بالحافلة 🔗", `تم تحديد "${fromNode?.label}". اضغط الآن على حافلة النقل لربطهما!`, "info");
    } else if (connectingFromId === id) {
      setConnectingFromId(null);
      showToast("تم إلغاء الربط", "تم الخروج من وضع الربط.", "info");
    } else {
      const fromNode = nodes.find(n => n.id === connectingFromId);
      const toNode   = nodes.find(n => n.id === id);
      if (!fromNode || !toNode) return;

      const alreadyConnected = connections.some(
        c => (c.fromId === connectingFromId && c.toId === id) || (c.fromId === id && c.toId === connectingFromId)
      );

      if (!alreadyConnected) {
        const conn: Connection = {
          id: `c_${connectingFromId}_${id}`,
          fromId: connectingFromId, toId: id,
          color: "#D97706",
        };
        setConnections(prev => [...prev, conn]);
        showToast("تم ربط الفصل بالحافلة ✅", `تم توصيل "${fromNode.label}" بـ "${toNode.label}" بنجاح!`, "success");
      }
      setConnectingFromId(null);
    }
  };

  const toggleConnectionWithNode = (targetId: string) => {
    if (!selectedId) return;
    const existing = connections.find(
      c => (c.fromId === selectedId && c.toId === targetId) || (c.fromId === targetId && c.toId === selectedId)
    );
    if (existing) {
      setConnections(prev => prev.filter(c => c.id !== existing.id));
      showToast("تم إلغاء رابط الحافلة ✂️", "تم فصل الشعبة عن هذا المسار.", "info");
    } else {
      setConnections(prev => [...prev, {
        id: `c_${selectedId}_${targetId}`,
        fromId: selectedId, toId: targetId,
        color: "#D97706",
      }]);
      showToast("تم ربط الحافلة 🚌", `تم اعتماد مسار النقل لهذه الشعبة!`, "success");
    }
  };

  const handleNodeClick = (e: React.MouseEvent, id: string) => {
    e.stopPropagation();
    if (connectingFromId && connectingFromId !== id) {
      handleConnectClick(e, id);
    } else {
      setSelectedId(id);
    }
  };

  const deleteNode = (id: string) => {
    const node = nodes.find(n => n.id === id);
    setNodes(prev => prev.filter(n => n.id !== id));
    setConnections(prev => prev.filter(c => c.fromId !== id && c.toId !== id));
    if (selectedId === id) setSelectedId(null);
    showToast("تم حذف الوحدة 🗑️", `تم إزالة "${node?.label}" من الشاشة بسهولة.`, "info");
  };

  const deleteConnection = (connId: string) => {
    setConnections(prev => prev.filter(c => c.id !== connId));
    showToast("تم فصل الحافلة ✂️", "تم إلغاء الرابط بنجاح.", "info");
  };

  const checkConflicts = () => {
    const sectionNodes = nodes.filter(n => n.type === "section");
    const issues: string[] = [];

    sectionNodes.forEach(s => {
      const hasBus = connections.some(c => c.fromId === s.id || c.toId === s.id);
      if (!hasBus) issues.push(`الشعبة "${s.label}" ليس لها حافلة نقل مدرسية متصلة.`);
      if (s.teachersCount === 0) issues.push(`الشعبة "${s.label}" لا يوجد بها معلمين مسندين.`);
    });

    if (issues.length === 0) {
      showToast("الهيكل منظم ومثالي 100% ✅", "جميع الفصول مرتبطة بالحافلات وأعداد الكادر والطلاب موزعة بانتظام.", "success");
    } else {
      showToast(`ملاحظات التوزيع (${issues.length}) ⚠️`, issues[0], "warning");
    }
  };

  // ── Auto Layout: Clean Structured Cards ─────────────────────────
  const loadFromData = useCallback(() => {
    const newNodes: CanvasNode[] = [];
    const newConns: Connection[] = [];

    // 1. Sections (Left Columns) - clean grid
    sections.forEach((sec, i) => {
      const sid = `sec_${sec.id}`;
      const neighbor = NEIGHBORHOODS[i % NEIGHBORHOODS.length];
      const col = i % 2;
      const row = Math.floor(i / 2);

      newNodes.push({
        id: sid, type: "section",
        x: 60 + col * 320, y: 40 + row * 180,
        label: sec.name,
        color: "#2563EB",
        gradeLevel: sec.gradeLevel,
        neighborhood: sec.roomNumber ? `قاعة ${sec.roomNumber}` : neighbor,
        roomNumber: sec.roomNumber,
        teachersCount: 1,
        studentsCount: sec.enrolledCount,
        parentsCount: sec.enrolledCount,
        assignedTeachers: sec.classTeacherName ? [sec.classTeacherName] : [],
        assignedStudents: [],
      });
    });

    // 2. Buses (Right Column) - clean alignment
    busRoutes.forEach((bus, i) => {
      const bid = `bus_${bus.id}`;
      const neighbor = NEIGHBORHOODS[i % NEIGHBORHOODS.length];
      newNodes.push({
        id: bid, type: "bus",
        x: 750, y: 50 + i * 190,
        label: bus.routeName,
        color: "#D97706",
        neighborhood: neighbor,
        studentsCount: bus.assignedStudentsCount,
        teachersCount: 0,
        parentsCount: 0,
        assignedTeachers: [],
        assignedStudents: [],
        driverName: bus.driverName,
        plateNumber: bus.plateNumber,
      });

      // Connect section matching the neighborhood
      const matchingSec = newNodes.find(n => n.type === "section" && n.neighborhood === neighbor);
      if (matchingSec) {
        newConns.push({
          id: `c_${matchingSec.id}_${bid}`,
          fromId: matchingSec.id, toId: bid,
          color: "#D97706",
        });
      }
    });

    setNodes(newNodes);
    setConnections(newConns);
    setZoom(0.9);
    setPan({ x: 30, y: 30 });
    setSelectedId(null);
    showToast("تحديث المخطط", "تم توزيع الشعب وخطوط النقل المسجلة في المدرسة على المخطط بنجاح.", "success");
  }, [sections, busRoutes, showToast]);

  const handleSaveCanvas = () => {
    void saveConfiguratorCanvas({
      nodes,
      connections,
      zoom,
      pan,
      filters: { gradeFilter, sortBy },
      saved_at: new Date().toISOString(),
    });
    showToast("حفظ المخطط", "تم حفظ المخطط الحالي بنجاح في قاعدة بيانات المدرسة.", "success");
  };

  const loadSavedCanvas = () => {
    const payload = canvasConfig?.payload as Partial<{
      nodes: CanvasNode[];
      connections: Connection[];
      zoom: number;
      pan: { x: number; y: number };
      filters: { gradeFilter?: string; sortBy?: "default" | "neighborhood" | "name" };
    }> | undefined;
    const savedNodes = payload?.nodes;

    if (!canvasConfig?.exists || !Array.isArray(savedNodes)) {
      showToast("تنبيه المخطط", "لا يوجد مخطط محفوظ مسبقاً، يمكنك البدء بالسحب أو التوليد التلقائي.", "warning");
      return;
    }

    setNodes(savedNodes);
    setConnections(Array.isArray(payload?.connections) ? payload.connections : []);
    if (typeof payload?.zoom === "number") setZoom(payload.zoom);
    if (payload?.pan && typeof payload.pan.x === "number" && typeof payload.pan.y === "number") {
      setPan(payload.pan);
    }
    if (payload?.filters?.gradeFilter) setGradeFilter(payload.filters.gradeFilter);
    if (payload?.filters?.sortBy) setSortBy(payload.filters.sortBy);
    setSelectedId(null);
    showToast("استعادة المخطط", "تم استرجاع المخطط المحفوظ بنجاح.", "success");
  };

  useEffect(() => {
    if (nodes.length === 0 && mainTab === "canvas" && sections.length > 0) {
      loadFromData();
    }
  }, [mainTab, sections.length, loadFromData, nodes.length]);

  const handleWheel = (e: React.WheelEvent) => {
    e.preventDefault();
    setZoom(z => Math.min(1.8, Math.max(0.4, z - e.deltaY * 0.001)));
  };

  if (!mounted) {
    return (
      <div className="dashboard-shell">
        <Sidebar />
        <div className="main-content">
          <div style={{ padding: 40, textAlign: "center", color: "var(--text-muted)", fontSize: 14 }}>
            جاري تهيئة مخطط هيكل المدرسة...
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content" style={{ overflow: "hidden" }}>
        
        {/* ── Top Level Header & Tabs ──────────────────────── */}
        <div style={{
          display: "flex", alignItems: "center", justifyContent: "space-between",
          padding: "14px 24px", borderBottom: "1px solid var(--border)",
          background: "var(--bg-surface)", flexWrap: "wrap", gap: 12,
          boxShadow: "0 2px 10px rgba(0,0,0,0.03)"
        }}>
          <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
            <div style={{
              width: 42, height: 42, borderRadius: 12,
              background: "linear-gradient(135deg, var(--primary), #1e83bb)",
              display: "flex", alignItems: "center", justifyContent: "center",
              boxShadow: "0 4px 14px rgba(23,107,154,0.2)"
            }}>
              <Network size={22} color="#fff" />
            </div>
            <div>
              <div style={{ fontWeight: 900, fontSize: 17, color: "var(--text-dark)" }}>
                مركز تأسيس وتكوين هيكل المدرسة
              </div>
              <div style={{ fontSize: 11.5, color: "var(--text-muted)" }}>
                توزيع وتسكين الفصول والكوادر التعليمية وخطوط النقل المدرسي
              </div>
            </div>
          </div>

          {/* TWO MAIN DIRECTIONAL TABS */}
          <div style={{
            display: "flex", background: "var(--bg-page)", padding: 4,
            borderRadius: 12, border: "1px solid var(--border)", gap: 6
          }}>
            <button
              onClick={() => setMainTab("wizard")}
              className={`btn ${mainTab === "wizard" ? "btn-primary" : "btn-ghost"}`}
              style={{ padding: "8px 18px", fontSize: 13, fontWeight: 800, gap: 8, borderRadius: 10 }}
            >
              <CheckSquare size={16} />
              مسار إدراج الموارد الأساسية
            </button>
            <button
              onClick={() => { setMainTab("canvas"); if (nodes.length === 0) loadFromData(); }}
              className={`btn ${mainTab === "canvas" ? "btn-primary" : "btn-ghost"}`}
              style={{ padding: "8px 18px", fontSize: 13, fontWeight: 800, gap: 8, borderRadius: 10 }}
            >
              <Network size={16} />
              مخطط التوزيع والربط البصري
            </button>
          </div>
        </div>

        {/* ── TAB 1: GUIDED RESOURCE SETUP WIZARD ──────────────────────── */}
        {mainTab === "wizard" && (
          <div style={{ padding: "24px 32px", overflowY: "auto", height: "calc(100vh - 134px)", background: "var(--bg-page)" }}>
            <div style={{
              background: "var(--bg-surface)", borderRadius: 16, padding: "20px 24px",
              border: "1px solid var(--border)", marginBottom: 24, boxShadow: "0 2px 12px rgba(0,0,0,0.02)"
            }}>
              <div style={{ fontSize: 13, fontWeight: 800, color: "var(--text-muted)", marginBottom: 14 }}>
                🚀 التوجيه الذكي لبناء مدرسة متكاملة (أكمل الخطوات بالترتيب لسهولة الربط في الشاشة):
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12 }}>
                {[
                  { step: 1, title: "1. الفصول والشعب", desc: "تأسيس الحاضنة الدراسية", icon: <BookOpen size={18} />, color: "#2563EB" },
                  { step: 2, title: "2. الكادر والمعلمون", desc: "إسناد المربين للفصول", icon: <Users size={18} />, color: "#7C3AED" },
                  { step: 3, title: "3. الطلاب وأولياء الأمور", desc: "التسجيل والربط العائلي", icon: <GraduationCap size={18} />, color: "#059669" },
                  { step: 4, title: "4. النقل والحافلات", desc: "توزيع الأحياء والمسارات", icon: <Bus size={18} />, color: "#D97706" },
                ].map((st) => {
                  const active = wizardStep === st.step;
                  return (
                    <div
                      key={st.step}
                      onClick={() => setWizardStep(st.step as any)}
                      style={{
                        padding: "14px 16px", borderRadius: 12, cursor: "pointer",
                        background: active ? st.color + "12" : "var(--bg-page)",
                        border: `2px solid ${active ? st.color : "var(--border)"}`,
                        transition: "all 0.2s", display: "flex", alignItems: "center", gap: 12
                      }}
                    >
                      <div style={{
                        width: 40, height: 40, borderRadius: 10,
                        background: active ? st.color : "var(--border)",
                        color: active ? "#fff" : "var(--text-muted)",
                        display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 900
                      }}>
                        {st.icon}
                      </div>
                      <div>
                        <div style={{ fontSize: 13.5, fontWeight: 900, color: active ? st.color : "var(--text-dark)" }}>{st.title}</div>
                        <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{st.desc}</div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Wizard Step 1: Sections */}
            {wizardStep === 1 && (
              <div className="card" style={{ padding: 24 }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
                  <div>
                    <h3 style={{ fontSize: 18, fontWeight: 900, color: "#2563EB", marginBottom: 4 }}>🏛️ الخطوة 1: تأسيس الفصول والشعب الدراسية</h3>
                    <p style={{ fontSize: 13, color: "var(--text-muted)" }}>إنشاء الفصول كـ (وحدات رئيسية مستقلة) ستضم بداخلها أعداد الطلاب والمعلمين وترتبط بالحافلات لاحقاً.</p>
                  </div>
                  <span className="badge badge-blue" style={{ fontSize: 13, padding: "6px 14px" }}>إجمالي الشعب: {sections.length}</span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>
                  <div style={{ background: "#EFF6FF", padding: 20, borderRadius: 14, border: "1px solid #BFDBFE" }}>
                    <div style={{ fontWeight: 900, fontSize: 14, color: "#1D4ED8", marginBottom: 12 }}>➕ إضافة شعبة دراسية جديدة</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                      <div>
                        <label style={{ fontSize: 12, fontWeight: 700, color: "#1E3A8A", display: "block", marginBottom: 4 }}>اسم الشعبة:</label>
                        <input type="text" className="form-input" placeholder="مثال: الصف السادس / شعبة ج" value={newSectionName} onChange={e => setNewSectionName(e.target.value)} />
                      </div>
                      <div>
                        <label style={{ fontSize: 12, fontWeight: 700, color: "#1E3A8A", display: "block", marginBottom: 4 }}>المستوى الدراسي:</label>
                        <select className="form-select" value={newSectionGrade} onChange={e => setNewSectionGrade(e.target.value)}>
                          <option value="الصف الرابع">الصف الرابع</option>
                          <option value="الصف الخامس">الصف الخامس</option>
                          <option value="الصف السادس">الصف السادس</option>
                        </select>
                      </div>
                      <button
                        onClick={() => {
                          if (!newSectionName) { showToast("تنبيه", "يرجى كتابة اسم الشعبة", "warning"); return; }
                          showToast("تم إضافة الشعبة ✅", `تم إنشاء "${newSectionName}" بنجاح!`, "success");
                          setNewSectionName("");
                        }}
                        className="btn btn-primary" style={{ marginTop: 6, justifyContent: "center", fontWeight: 800 }}
                      >
                        <Plus size={16} /> إضافة الشعبة وحفظها
                      </button>
                    </div>
                  </div>

                  <div>
                    <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)", marginBottom: 10 }}>📋 الشعب المسجلة حالياً:</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 8, maxHeight: 280, overflowY: "auto" }}>
                      {sections.map((sec) => (
                        <div key={sec.id} style={{ padding: "12px 16px", borderRadius: 10, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                            <BookOpen size={18} color="#2563EB" />
                            <div>
                              <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{sec.name}</div>
                              <div style={{ fontSize: 11, color: "var(--text-muted)" }}>قاعة {sec.roomNumber} • مربي الفصل: {sec.classTeacherName}</div>
                            </div>
                          </div>
                          <span className="badge badge-blue">👥 {sec.enrolledCount} طالب</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 24, display: "flex", justifyContent: "flex-end" }}>
                  <button onClick={() => setWizardStep(2)} className="btn btn-primary" style={{ gap: 8, fontWeight: 800, padding: "10px 24px" }}>
                    الخطوة التالية: إضافة الكادر التعليمي <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

            {/* Wizard Step 2: Teachers */}
            {wizardStep === 2 && (
              <div className="card" style={{ padding: 24 }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
                  <div>
                    <h3 style={{ fontSize: 18, fontWeight: 900, color: "#7C3AED", marginBottom: 4 }}>👨‍🏫 الخطوة 2: إضافة المعلمين وربطهم بالشعب</h3>
                    <p style={{ fontSize: 13, color: "var(--text-muted)" }}>إسناد المعلمين للفصول مباشرة لتظهر أسماؤهم وأعدادهم داخل كارت الفصل في الرسم البياني.</p>
                  </div>
                  <span className="badge badge-purple" style={{ fontSize: 13, padding: "6px 14px" }}>إجمالي المعلمين: {teachers.length}</span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>
                  <div style={{ background: "#F5F3FF", padding: 20, borderRadius: 14, border: "1px solid #DDD6FE" }}>
                    <div style={{ fontWeight: 900, fontSize: 14, color: "#6D28D9", marginBottom: 12 }}>➕ إضافة معلم أو مربي فصل</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                      <div>
                        <label style={{ fontSize: 12, fontWeight: 700, color: "#4C1D95", display: "block", marginBottom: 4 }}>اسم المعلم:</label>
                        <input type="text" className="form-input" placeholder="مثال: الأستاذ عبدالله الشهراني" value={newTeacherName} onChange={e => setNewTeacherName(e.target.value)} />
                      </div>
                      <div>
                        <label style={{ fontSize: 12, fontWeight: 700, color: "#4C1D95", display: "block", marginBottom: 4 }}>التخصص الأكاديمي:</label>
                        <select className="form-select" value={newTeacherSpec} onChange={e => setNewTeacherSpec(e.target.value)}>
                          <option value="لغة عربية">لغة عربية</option><option value="رياضيات">رياضيات</option><option value="علوم وبحوث">علوم وبحوث</option><option value="لغة إنجليزية">لغة إنجليزية</option>
                        </select>
                      </div>
                      <button onClick={() => { if (!newTeacherName) return; showToast("تم إضافة المعلم ✅", "تم التسجيل بنجاح.", "success"); setNewTeacherName(""); }} className="btn btn-primary" style={{ marginTop: 6, justifyContent: "center", fontWeight: 800, background: "#7C3AED", borderColor: "#7C3AED" }}>
                        <Plus size={16} /> تسجيل المعلم في المدرسة
                      </button>
                    </div>
                  </div>

                  <div>
                    <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)", marginBottom: 10 }}>🔗 إسناد المعلمين للفصول:</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 8, maxHeight: 280, overflowY: "auto" }}>
                      {teachers.map((t) => (
                        <div key={t.id} style={{ padding: "12px 16px", borderRadius: 10, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                            <div style={{ width: 34, height: 34, borderRadius: 8, background: "#7C3AED20", color: "#7C3AED", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 900 }}>{t.avatarInitials}</div>
                            <div>
                              <div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{t.name}</div>
                              <div style={{ fontSize: 11, color: "var(--text-muted)" }}>{t.specialization}</div>
                            </div>
                          </div>
                          <select className="form-select" style={{ width: 160, fontSize: 11, padding: "4px 8px" }} defaultValue="connected" onChange={() => showToast("تم التحديث 🔗", "تم ربط المعلم بالشعبة.", "success")}>
                            <option value="connected">✓ مرتبط بـ 2 شعبة</option>
                            {sections.map(s => <option key={s.id} value={s.id}>＋ إسناد إلى: {s.name}</option>)}
                          </select>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 24, display: "flex", justifyContent: "space-between" }}>
                  <button onClick={() => setWizardStep(1)} className="btn btn-ghost" style={{ gap: 8 }}><ArrowRight style={{ transform: "rotate(180deg)" }} size={16} /> السابق</button>
                  <button onClick={() => setWizardStep(3)} className="btn btn-primary" style={{ gap: 8, fontWeight: 800, padding: "10px 24px" }}>الخطوة التالية: الطلاب وأولياء الأمور <ArrowRight size={16} /></button>
                </div>
              </div>
            )}

            {/* Wizard Step 3: Students & Parents */}
            {wizardStep === 3 && (
              <div className="card" style={{ padding: 24 }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
                  <div>
                    <h3 style={{ fontSize: 18, fontWeight: 900, color: "#059669", marginBottom: 4 }}>🎓 الخطوة 3: تسجيل الطلاب والربط التلقائي بأولياء الأمور</h3>
                    <p style={{ fontSize: 13, color: "var(--text-muted)" }}>يتم إضافة أعداد الطلاب وأولياء أمورهم تلقائياً كأيقونات إحصائية نظيفة داخل كل فصل.</p>
                  </div>
                  <span className="badge badge-green" style={{ fontSize: 13, padding: "6px 14px" }}>إجمالي الطلاب: {students.length}</span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>
                  <div style={{ background: "#ECFDF5", padding: 20, borderRadius: 14, border: "1px solid #A7F3D0" }}>
                    <div style={{ fontWeight: 900, fontSize: 14, color: "#047857", marginBottom: 12 }}>➕ إضافة طالب وربط عائلته</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                      <div><label style={{ fontSize: 12, fontWeight: 700, color: "#065F46", display: "block", marginBottom: 4 }}>اسم الطالب:</label><input type="text" className="form-input" placeholder="مثال: فهد عبدالعزيز المالكي" value={newStudentName} onChange={e => setNewStudentName(e.target.value)} /></div>
                      <div><label style={{ fontSize: 12, fontWeight: 700, color: "#065F46", display: "block", marginBottom: 4 }}>ولي الأمر:</label><input type="text" className="form-input" placeholder="مثال: د. عبدالعزيز المالكي (الأب)" value={newStudentParent} onChange={e => setNewStudentParent(e.target.value)} /></div>
                      <div>
                        <label style={{ fontSize: 12, fontWeight: 700, color: "#065F46", display: "block", marginBottom: 4 }}>📍 الحي السكني:</label>
                        <select className="form-select" value={newStudentNeighborhood} onChange={e => setNewStudentNeighborhood(e.target.value)}>{NEIGHBORHOODS.map(nh => <option key={nh} value={nh}>{nh}</option>)}</select>
                      </div>
                      <button onClick={() => { if (!newStudentName) return; showToast("تم إضافة الطالب ✅", "تم التسجيل والربط العائلي بنجاح.", "success"); setNewStudentName(""); setNewStudentParent(""); }} className="btn btn-primary" style={{ marginTop: 6, justifyContent: "center", fontWeight: 800, background: "#059669", borderColor: "#059669" }}><Plus size={16} /> تسجيل الطالب والربط الفوري</button>
                    </div>
                  </div>

                  <div>
                    <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)", marginBottom: 10 }}>👥 عينة الطلاب وتوزيع الأحياء:</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 8, maxHeight: 310, overflowY: "auto" }}>
                      {students.slice(0, 7).map((st, i) => (
                        <div key={st.id} style={{ padding: "10px 14px", borderRadius: 10, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                            <GraduationCap size={18} color="#059669" />
                            <div><div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)" }}>{st.name}</div><div style={{ fontSize: 11, color: "var(--text-muted)" }}>ولي الأمر: {st.parentName} • {st.sectionName}</div></div>
                          </div>
                          <span className="badge badge-orange" style={{ fontSize: 10 }}>📍 {NEIGHBORHOODS[i % NEIGHBORHOODS.length]}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 24, display: "flex", justifyContent: "space-between" }}>
                  <button onClick={() => setWizardStep(2)} className="btn btn-ghost" style={{ gap: 8 }}><ArrowRight style={{ transform: "rotate(180deg)" }} size={16} /> السابق</button>
                  <button onClick={() => setWizardStep(4)} className="btn btn-primary" style={{ gap: 8, fontWeight: 800, padding: "10px 24px" }}>الخطوة التالية: النقل والحافلات <ArrowRight size={16} /></button>
                </div>
              </div>
            )}

            {/* Wizard Step 4: Buses */}
            {wizardStep === 4 && (
              <div className="card" style={{ padding: 24 }}>
                <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
                  <div>
                    <h3 style={{ fontSize: 18, fontWeight: 900, color: "#D97706", marginBottom: 4 }}>🚌 الخطوة 4: أسطول النقل وتوزيع مسارات الأحياء السكنية</h3>
                    <p style={{ fontSize: 13, color: "var(--text-muted)" }}>تأسيس خطوط السير حسب الأحياء لربطها بالفصول الموافقة لها في الشاشة بسهولة.</p>
                  </div>
                  <span className="badge badge-orange" style={{ fontSize: 13, padding: "6px 14px" }}>إجمالي الخطوط: {busRoutes.length}</span>
                </div>

                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20 }}>
                  <div style={{ background: "#FFFBEB", padding: 20, borderRadius: 14, border: "1px solid #FDE68A" }}>
                    <div style={{ fontWeight: 900, fontSize: 14, color: "#B45309", marginBottom: 12 }}>➕ إضافة مسار حافلة جديد</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
                      <div><label style={{ fontSize: 12, fontWeight: 700, color: "#78350F", display: "block", marginBottom: 4 }}>اسم خط السير والحي المستهدف:</label><input type="text" className="form-input" placeholder="مثال: مسار حي الملقا (خط 5)" value={newBusRoute} onChange={e => setNewBusRoute(e.target.value)} /></div>
                      <div><label style={{ fontSize: 12, fontWeight: 700, color: "#78350F", display: "block", marginBottom: 4 }}>سائق الحافلة وجواله:</label><input type="text" className="form-input" placeholder="مثال: صالح الرشيدي - 0501122334" /></div>
                      <button onClick={() => { if (!newBusRoute) return; showToast("تم إضافة الحافلة ✅", "تم الاعتماد بنجاح.", "success"); setNewBusRoute(""); }} className="btn btn-primary" style={{ marginTop: 6, justifyContent: "center", fontWeight: 800, background: "#D97706", borderColor: "#D97706" }}><Plus size={16} /> اعتماد المسار والحافلة</button>
                    </div>
                  </div>

                  <div>
                    <div style={{ fontWeight: 800, fontSize: 13, color: "var(--text-dark)", marginBottom: 10 }}>🚌 الخطوط العاملة:</div>
                    <div style={{ display: "flex", flexDirection: "column", gap: 8, maxHeight: 280, overflowY: "auto" }}>
                      {busRoutes.map((bus) => (
                        <div key={bus.id} style={{ padding: "12px 16px", borderRadius: 10, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                            <Bus size={20} color="#D97706" />
                            <div><div style={{ fontWeight: 800, fontSize: 13.5, color: "var(--text-dark)" }}>{bus.routeName}</div><div style={{ fontSize: 11, color: "var(--text-muted)" }}>{bus.plateNumber} • السائق: {bus.driverName}</div></div>
                          </div>
                          <span className="badge badge-orange">👥 {bus.assignedStudentsCount} طالب مسجل</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                <div style={{ marginTop: 24, display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                  <button onClick={() => setWizardStep(3)} className="btn btn-ghost" style={{ gap: 8 }}><ArrowRight style={{ transform: "rotate(180deg)" }} size={16} /> السابق</button>
                  <button
                    onClick={() => {
                      showToast("اكتمل تأسيس الموارد 🎉", "أنت جاهز الآن للانتقال إلى مسار التكوين البصري النظيف!", "success");
                      setMainTab("canvas");
                      if (nodes.length === 0) loadFromData();
                    }}
                    className="btn btn-primary"
                    style={{ gap: 8, fontWeight: 900, padding: "12px 28px", background: "linear-gradient(135deg, #059669, #10B981)", borderColor: "#059669" }}
                  >
                    🎉 إتمام التأسيس والانتقال للرسم البياني النظيف <ArrowRight size={16} />
                  </button>
                </div>
              </div>
            )}

          </div>
        )}

        {/* ── TAB 2: DIAGRAM CANVAS ── */}
        {mainTab === "canvas" && (
          <div style={{ display: "flex", flexDirection: "column", height: "calc(100vh - 134px)", overflow: "hidden" }}>
            
            {/* 🌟 SMART FILTERS & CONTROLS BAR */}
            <div style={{
              display: "flex", alignItems: "center", justifyContent: "space-between",
              padding: "10px 20px", background: "var(--bg-page)", borderBottom: "1px solid var(--border)",
              flexWrap: "wrap", gap: 12
            }}>
              <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <span style={{ fontSize: 12, fontWeight: 900, color: "var(--text-dark)", display: "flex", alignItems: "center", gap: 5 }}>
                  <Filter size={15} color="var(--primary)" /> تصفية الشعب حسب المرحلة:
                </span>
                <div style={{ display: "flex", gap: 4, background: "var(--bg-surface)", padding: 3, borderRadius: 8, border: "1px solid var(--border)" }}>
                  <button
                    onClick={() => setGradeFilter("all")}
                    style={{
                      padding: "6px 14px", borderRadius: 6, fontSize: 11.5, fontWeight: 800,
                      border: "none", cursor: "pointer",
                      background: gradeFilter === "all" ? "var(--primary)" : "transparent",
                      color: gradeFilter === "all" ? "#fff" : "var(--text-dark)",
                      transition: "all 0.15s", boxShadow: gradeFilter === "all" ? "0 2px 6px rgba(23,107,154,0.25)" : "none"
                    }}
                  >
                    عرض الكل ({sections.length})
                  </button>
                  {availableGradeLevels.map((grade) => (
                    <button
                      key={grade}
                      onClick={() => setGradeFilter(grade)}
                      style={{
                        padding: "6px 14px", borderRadius: 6, fontSize: 11.5, fontWeight: 800,
                        border: "none", cursor: "pointer",
                        background: gradeFilter === grade ? "var(--primary)" : "transparent",
                        color: gradeFilter === grade ? "#fff" : "var(--text-dark)",
                        transition: "all 0.15s", boxShadow: gradeFilter === grade ? "0 2px 6px rgba(23,107,154,0.25)" : "none"
                      }}
                    >
                      {grade}
                    </button>
                  ))}
                </div>
              </div>

              <div style={{ display: "flex", alignItems: "center", gap: 10, flexWrap: "wrap" }}>
                <div style={{ display: "flex", gap: 8 }}>
                  <button onClick={handleSaveCanvas} className="btn btn-sm btn-outline">
                    <Save size={13} /> حفظ المخطط
                  </button>
                  <button onClick={loadSavedCanvas} className="btn btn-sm btn-ghost">
                    <Download size={13} /> استعادة المحفوظ
                  </button>
                  <button onClick={loadFromData} className="btn btn-sm btn-primary">
                    <Sparkles size={13} /> إعادة توزيع تلقائي
                  </button>
                </div>
              </div>
            </div>

            {/* Main Canvas + Left Sidebar Container */}
            <div style={{ display: "flex", flex: 1, overflow: "hidden" }}>
              
              {/* Ultra-Clean Toolbox */}
              <div style={{ width: 240, flexShrink: 0, borderLeft: "1px solid var(--border)", background: "var(--bg-surface)", display: "flex", flexDirection: "column" }}>
                <div style={{ display: "flex", borderBottom: "1px solid var(--border)", background: "var(--bg-page)" }}>
                  {[
                    { id: "sections", label: `الشعب الدراسية (${filteredSections.length})` },
                    { id: "buses", label: `خطوط الحافلات (${busRoutes.length})` },
                  ].map(tab => (
                    <button
                      key={tab.id}
                      onClick={() => setActiveToolboxTab(tab.id as any)}
                      style={{
                        flex: 1, padding: "10px 4px", fontSize: 11.5, fontWeight: 800,
                        border: "none", background: activeToolboxTab === tab.id ? "var(--bg-surface)" : "transparent",
                        color: activeToolboxTab === tab.id ? "var(--primary)" : "var(--text-muted)",
                        borderBottom: activeToolboxTab === tab.id ? "2px solid var(--primary)" : "none",
                        cursor: "pointer"
                      }}
                    >
                      {tab.label}
                    </button>
                  ))}
                </div>

                <div style={{ flex: 1, overflowY: "auto", padding: 12, display: "flex", flexDirection: "column", gap: 8 }}>
                  <div style={{ fontSize: 11, color: "var(--text-muted)", lineHeight: 1.5, background: "var(--bg-page)", padding: 10, borderRadius: 8, border: "1px solid var(--border)", marginBottom: 6 }}>
                    👈 <strong>اسحب العنصر إلى ساحة المخطط:</strong> لإدراجه وتوصيل مسارات النقل والكادر التعليمي.
                  </div>

                  {activeToolboxTab === "sections" && (
                    <>
                      {filteredSections.map(sec => (
                        <div
                          key={sec.id} draggable
                          onDragStart={e => {
                            e.dataTransfer.setData("nodeType", "section");
                            e.dataTransfer.setData("nodeLabel", sec.name);
                            e.dataTransfer.setData("nodeGrade", sec.gradeLevel);
                            e.dataTransfer.setData("nodeCount", sec.enrolledCount.toString());
                          }}
                          style={{ padding: "12px", borderRadius: 12, background: "#EFF6FF", border: "1.5px solid #BFDBFE", cursor: "grab", marginBottom: 6, boxShadow: "0 2px 4px rgba(0,0,0,0.02)" }}
                        >
                          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
                            <span style={{ fontSize: 13, fontWeight: 900, color: "#1D4ED8" }}>{sec.name}</span>
                            <span className="badge badge-blue" style={{ fontSize: 10 }}>{sec.enrolledCount} طالب</span>
                          </div>
                          <div style={{ fontSize: 10.5, color: "#64748B", fontWeight: 600 }}>قاعة {sec.roomNumber || "—"} • مربي الفصل: {sec.classTeacherName || "غير محدد"}</div>
                        </div>
                      ))}
                    </>
                  )}

                  {activeToolboxTab === "buses" && (
                    <>
                      {busRoutes.map((bus, idx) => (
                        <div
                          key={bus.id} draggable
                          onDragStart={e => {
                            e.dataTransfer.setData("nodeType", "bus");
                            e.dataTransfer.setData("nodeLabel", bus.routeName);
                            e.dataTransfer.setData("nodeNeighbor", NEIGHBORHOODS[idx % NEIGHBORHOODS.length]);
                            e.dataTransfer.setData("nodeCount", bus.assignedStudentsCount.toString());
                          }}
                          style={{ padding: "12px", borderRadius: 12, background: "#FFFBEB", border: "1.5px solid #FDE68A", cursor: "grab", marginBottom: 6, boxShadow: "0 2px 4px rgba(0,0,0,0.02)" }}
                        >
                          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 6 }}>
                            <span style={{ fontSize: 13, fontWeight: 900, color: "#B45309" }}>{bus.routeName}</span>
                            <span className="badge badge-orange" style={{ fontSize: 10 }}>{bus.assignedStudentsCount} راكب</span>
                          </div>
                          <div style={{ fontSize: 10.5, color: "#92400E", fontWeight: 600 }}>السائق: {bus.driverName || "—"} • اللوحة: {bus.plateNumber || "—"}</div>
                        </div>
                      ))}
                    </>
                  )}
                </div>
              </div>

              {/* 🌟 INTERACTIVE HTML/DOM CANVAS */}
              <div
                ref={containerRef}
                className="canvas-bg"
                style={{ flex: 1, position: "relative", overflow: "hidden", background: "radial-gradient(circle at 50% 50%, #F8FAFC 0%, #EEF2FF 100%)", userSelect: "none" }}
                onDragOver={e => e.preventDefault()}
                onDrop={handleDrop}
                onMouseDown={handleCanvasMouseDown}
                onWheel={handleWheel}
              >
                {/* SVG Line Layer (Background Only!) */}
                <svg style={{ position: "absolute", inset: 0, width: "100%", height: "100%", pointerEvents: "none", zIndex: 1 }}>
                  <defs>
                    <pattern id="grid" x={pan.x % (20 * zoom)} y={pan.y % (20 * zoom)} width={20 * zoom} height={20 * zoom} patternUnits="userSpaceOnUse">
                      <circle cx={1} cy={1} r={1} fill="#CBD5E1" />
                    </pattern>
                  </defs>
                  <rect width="100%" height="100%" fill="url(#grid)" />

                  <g transform={`translate(${pan.x}, ${pan.y}) scale(${zoom})`}>
                    {connections.map(conn => {
                      const from = nodes.find(n => n.id === conn.fromId);
                      const to   = nodes.find(n => n.id === conn.toId);
                      if (!from || !to) return null;
                      if (gradeFilter !== "all" && ((from.gradeLevel && from.gradeLevel !== gradeFilter) || (to.gradeLevel && to.gradeLevel !== gradeFilter))) return null;

                      const x1 = from.x + 280; const y1 = from.y + 75;
                      const x2 = to.x;         const y2 = to.y + 75;
                      const midX = (x1 + x2) / 2; const midY = (y1 + y2) / 2;

                      return (
                        <g key={conn.id} style={{ pointerEvents: "auto" }}>
                          <path d={bezierPath(x1, y1, x2, y2)} fill="none" stroke={conn.color} strokeWidth={3.5} strokeOpacity={0.85} />
                          <circle cx={midX} cy={midY} r={11} fill="white" stroke={conn.color} strokeWidth={2.5} style={{ cursor: "pointer" }} onClick={(e) => { e.stopPropagation(); deleteConnection(conn.id); }} />
                          <text x={midX} y={midY + 4} textAnchor="middle" fontSize={11} fill={conn.color} fontWeight="900" style={{ pointerEvents: "none" }}>✕</text>
                        </g>
                      );
                    })}
                  </g>
                </svg>

                {nodes.length === 0 && (
                  <div style={{ position: "absolute", inset: 0, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", pointerEvents: "none", gap: 16, zIndex: 5 }}>
                    <div style={{ background: "rgba(255,255,255,0.96)", backdropFilter: "blur(12px)", border: "2px dashed #CBD5E1", borderRadius: 20, padding: "36px 48px", textAlign: "center", maxWidth: 460, boxShadow: "0 10px 30px rgba(0,0,0,0.05)" }}>
                      <div style={{ fontSize: 40, marginBottom: 12 }}>🏛️</div>
                      <div style={{ fontWeight: 900, fontSize: 18, color: "var(--text-dark)", marginBottom: 8 }}>
                        مخطط الهيكل التنظيمي للمدرسة
                      </div>
                      <div style={{ fontSize: 13, color: "var(--text-muted)", lineHeight: 1.7, marginBottom: 20 }}>
                        يمكنك سحب الفصول وخطوط الحافلات من القائمة الجانبية أو توليد المخطط التلقائي من بيانات المدرسة المسجلة حالياً.
                      </div>
                      <button style={{ pointerEvents: "auto" }} onClick={loadFromData} className="btn btn-primary">
                        <Sparkles size={16} /> توليد المخطط من بيانات المدرسة
                      </button>
                    </div>
                  </div>
                )}

                {/* 🌟 FOREGROUND HTML DOM CARDS (No SVG Text Overlap Ever Again!) */}
                <div style={{
                  position: "absolute", left: 0, top: 0, width: "100%", height: "100%",
                  transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
                  transformOrigin: "0 0", pointerEvents: "none", zIndex: 10
                }}>
                  {sortedDisplayNodes.map(node => {
                    const isSelected = node.id === selectedId;
                    const isConnecting = node.id === connectingFromId;
                    const connectedBusId = connections.find(c => c.fromId === node.id || c.toId === node.id)?.toId || "";

                    return (
                      <div
                        key={node.id}
                        onMouseDown={e => handleNodeMouseDown(e, node.id)}
                        onClick={e => { e.stopPropagation(); handleNodeClick(e, node.id); }}
                        style={{
                          position: "absolute",
                          left: node.x, top: node.y,
                          width: node.type === "section" ? 280 : 260,
                          background: node.type === "section" ? (isSelected ? "#2563EB" : "#fff") : (isSelected ? "#D97706" : "#fff"),
                          borderRadius: 16,
                          border: `2px solid ${node.type === "section" ? (isSelected ? "#1D4ED8" : "#BFDBFE") : (isSelected ? "#B45309" : "#FDE68A")}`,
                          boxShadow: isSelected ? "0 10px 25px rgba(0,0,0,0.15)" : "0 4px 12px rgba(0,0,0,0.06)",
                          padding: 14,
                          cursor: draggingId ? "grabbing" : "grab",
                          pointerEvents: "auto",
                          transition: "border 0.15s, box-shadow 0.15s",
                          color: isSelected ? "#fff" : "var(--text-dark)",
                          zIndex: isSelected ? 20 : 10
                        }}
                      >
                        {/* Top Header Bar with Prominent DELETE BUTTON! */}
                        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 10, paddingBottom: 8, borderBottom: `1px solid ${isSelected ? "rgba(255,255,255,0.2)" : "var(--border)"}` }}>
                          <span style={{ fontSize: 11.5, fontWeight: 900, color: isSelected ? "#fff" : (node.type === "section" ? "#1E40AF" : "#92400E"), display: "flex", alignItems: "center", gap: 6 }}>
                            {node.type === "section" ? <BookOpen size={14} /> : <Bus size={14} />}
                            {node.gradeLevel || "مسار نقل مدرسي"} • {node.roomNumber ? `قاعة ${node.roomNumber}` : node.plateNumber}
                          </span>
                          
                          {/* 🛑 PROMINENT RED DELETE BUTTON ON CARD CORNER! */}
                          <button
                            onClick={(e) => { e.stopPropagation(); deleteNode(node.id); }}
                            title="حذف هذا الكارت من الشاشة"
                            style={{
                              background: "#FEE2E2", color: "#DC2626", border: "1px solid #FECACA",
                              borderRadius: 6, padding: "3px 8px", fontSize: 11, fontWeight: 900,
                              cursor: "pointer", display: "flex", alignItems: "center", gap: 3,
                              transition: "background 0.15s"
                            }}
                          >
                            ✕ حذف
                          </button>
                        </div>

                        {/* Title */}
                        <div style={{ fontSize: 16, fontWeight: 900, marginBottom: 12, color: isSelected ? "#fff" : "var(--text-dark)" }}>
                          {node.label}
                        </div>

                        {/* STATS BADGES (Flexbox, Zero Overlap!) */}
                        <div style={{ display: "flex", flexWrap: "wrap", gap: 6, marginBottom: 12 }}>
                          {node.type === "section" ? (
                            <>
                              <span style={{ background: isSelected ? "rgba(255,255,255,0.2)" : "#F5F3FF", color: isSelected ? "#fff" : "#6D28D9", border: "1px solid #DDD6FE", padding: "4px 8px", borderRadius: 6, fontSize: 11, fontWeight: 800 }}>
                                👨‍🏫 {node.teachersCount} معلم
                              </span>
                              <span style={{ background: isSelected ? "rgba(255,255,255,0.2)" : "#ECFDF5", color: isSelected ? "#fff" : "#047857", border: "1px solid #A7F3D0", padding: "4px 8px", borderRadius: 6, fontSize: 11, fontWeight: 800 }}>
                                🎓 {node.studentsCount} طالب
                              </span>
                              <span style={{ background: isSelected ? "rgba(255,255,255,0.2)" : "#FFFBEB", color: isSelected ? "#fff" : "#B45309", border: "1px solid #FDE68A", padding: "4px 8px", borderRadius: 6, fontSize: 11, fontWeight: 800 }}>
                                👪 {node.parentsCount} أسرة
                              </span>
                            </>
                          ) : (
                            <>
                              <span style={{ background: isSelected ? "rgba(255,255,255,0.2)" : "#FFF", color: isSelected ? "#fff" : "#B45309", border: "1px solid #FDE68A", padding: "4px 8px", borderRadius: 6, fontSize: 11, fontWeight: 800 }}>
                                👤 {node.driverName || "صالح الرشيدي"}
                              </span>
                              <span style={{ background: isSelected ? "rgba(255,255,255,0.2)" : "#FEF3C7", color: isSelected ? "#fff" : "#92400E", border: "1px solid #FCD34D", padding: "4px 8px", borderRadius: 6, fontSize: 11, fontWeight: 800 }}>
                                👥 {node.studentsCount} منقول
                              </span>
                            </>
                          )}
                        </div>

                        {/* Interactive Dropdown Footer for Sections (The Easiest Linking UX Ever!) */}
                        {node.type === "section" ? (
                          <div style={{ background: isSelected ? "rgba(0,0,0,0.15)" : "#F8FAFC", padding: 8, borderRadius: 10, border: "1px solid var(--border)" }} onClick={e => e.stopPropagation()}>
                            <div style={{ fontSize: 10.5, fontWeight: 800, color: isSelected ? "#E2E8F0" : "#64748B", marginBottom: 4 }}>
                              🚌 حافلة النقل المرتبطة بالفصل:
                            </div>
                            <select
                              className="form-select"
                              style={{ width: "100%", fontSize: 11.5, fontWeight: 800, padding: "4px 8px", background: "#fff", color: "#1E293B" }}
                              value={connectedBusId}
                              onChange={(e) => {
                                const val = e.target.value;
                                setConnections(prev => prev.filter(c => c.fromId !== node.id && c.toId !== node.id));
                                if (val) {
                                  setConnections(prev => [...prev, { id: `c_${node.id}_${val}`, fromId: node.id, toId: val, color: "#D97706" }]);
                                  showToast("تم ربط الحافلة 🚌", `تم توصيل هذا الفصل بـ مسار النقل المحدد!`, "success");
                                } else {
                                  showToast("تم فصل الحافلة", `تم إلغاء الرابط بنجاح.`, "info");
                                }
                              }}
                            >
                              <option value="">-- بدون حافلة (اختر للربط) --</option>
                              {nodes.filter(n => n.type === "bus").map(b => (
                                <option key={b.id} value={b.id}>🚌 {b.label} (يخدم {b.neighborhood})</option>
                              ))}
                            </select>
                          </div>
                        ) : (
                          <div style={{ fontSize: 11.5, fontWeight: 800, color: isSelected ? "#fff" : "#B45309", background: isSelected ? "rgba(0,0,0,0.15)" : "#FFFBEB", padding: "6px 10px", borderRadius: 8 }}>
                            📍 يخدم منطقة: {node.neighborhood}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>

                {connectingFromId && (
                  <div style={{ position: "absolute", top: 16, left: "50%", transform: "translateX(-50%)", background: "#D97706", color: "#fff", borderRadius: 12, padding: "10px 20px", fontSize: 13, fontWeight: 800, boxShadow: "0 8px 25px rgba(217,119,6,0.35)", display: "flex", alignItems: "center", gap: 10, zIndex: 10 }}>
                    <Link2 size={18} />
                    <span>وضع ربط الحافلة: اضغط الآن على الوحدة الأخرى لتوصيلهما فوراً!</span>
                    <button onClick={() => setConnectingFromId(null)} style={{ background: "rgba(255,255,255,0.2)", border: "none", borderRadius: 6, color: "#fff", cursor: "pointer", padding: "4px 8px", fontSize: 12 }}>إلغاء</button>
                  </div>
                )}
              </div>

              {/* 🌟 THE INTERACTIVE INSPECTOR DRAWER (When Clicking a Section/Bus) */}
              {selectedNode ? (
                <div style={{ width: 310, flexShrink: 0, borderRight: "1px solid var(--border)", background: "var(--bg-surface)", overflowY: "auto", padding: 18, display: "flex", flexDirection: "column", gap: 16, boxShadow: "-4px 0 15px rgba(0,0,0,0.03)" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <div style={{ fontWeight: 900, fontSize: 15, color: "var(--text-dark)", display: "flex", alignItems: "center", gap: 6 }}>
                      {selectedNode.type === "section" ? "🏛️ إدارة كادر وطلاب الفصل" : "🚌 تفاصيل وركاب الحافلة"}
                    </div>
                    <button onClick={() => setSelectedId(null)} style={{ background: "none", border: "none", cursor: "pointer", color: "var(--text-muted)" }}><X size={18} /></button>
                  </div>

                  <div style={{ padding: 14, borderRadius: 14, background: selectedNode.color + "14", border: `1.5px solid ${selectedNode.color}40` }}>
                    <div style={{ fontSize: 11, color: selectedNode.color, fontWeight: 800, marginBottom: 2 }}>{selectedNode.gradeLevel || "مسار نقل مدرسي"}</div>
                    <div style={{ fontWeight: 900, fontSize: 16, color: "var(--text-dark)" }}>{selectedNode.label}</div>
                    <div style={{ fontSize: 11.5, color: "var(--text-muted)", marginTop: 4 }}>📍 المنطقة المستهدفة: {selectedNode.neighborhood}</div>
                  </div>

                  {/* IF SECTION: SHOW THREE CLEAN TABS */}
                  {selectedNode.type === "section" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                      <div style={{ background: "var(--bg-page)", padding: 12, borderRadius: 12, border: "1px solid var(--border)" }}>
                        <div style={{ fontSize: 12, fontWeight: 900, color: "#6D28D9", marginBottom: 8, display: "flex", alignItems: "center", gap: 6 }}>
                          <Users size={15} /> معلّمو المادة ومربي الفصل ({selectedNode.assignedTeachers.length}):
                        </div>
                        <div style={{ display: "flex", flexDirection: "column", gap: 6, marginBottom: 10 }}>
                          {selectedNode.assignedTeachers.map((tch, idx) => (
                            <div key={idx} style={{ padding: "8px 10px", borderRadius: 8, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: 11.5, fontWeight: 700 }}>
                              <span>👨‍🏫 {tch}</span>
                              <button
                                onClick={() => {
                                  setNodes(prev => prev.map(n => n.id === selectedNode.id ? { ...n, assignedTeachers: n.assignedTeachers.filter((_, i) => i !== idx), teachersCount: Math.max(0, n.teachersCount - 1) } : n));
                                  showToast("تم الحذف", "تم إزالة المعلم من الفصل.", "info");
                                }}
                                style={{ background: "none", border: "none", color: "#EF4444", cursor: "pointer", fontSize: 11, fontWeight: 800 }}
                              >✕ حذف</button>
                            </div>
                          ))}
                        </div>
                        <button
                          onClick={() => {
                            const newTch = "الأستاذ فهد العتيبي (جديد)";
                            setNodes(prev => prev.map(n => n.id === selectedNode.id ? { ...n, assignedTeachers: [...n.assignedTeachers, newTch], teachersCount: n.teachersCount + 1 } : n));
                            showToast("تم الإسناد ✅", "تم إضافة معلم جديد لقائمة الفصل.", "success");
                          }}
                          className="btn btn-ghost btn-sm" style={{ width: "100%", justifyContent: "center", fontSize: 11, fontWeight: 800, color: "#6D28D9", border: "1px dashed #DDD6FE" }}
                        >
                          <UserPlus size={14} /> ＋ إسناد معلم آخر للفصل
                        </button>
                      </div>

                      <div style={{ background: "var(--bg-page)", padding: 12, borderRadius: 12, border: "1px solid var(--border)" }}>
                        <div style={{ fontSize: 12, fontWeight: 900, color: "#047857", marginBottom: 8, display: "flex", alignItems: "center", gap: 6 }}>
                          <GraduationCap size={15} /> طلاب الشعبة وأولياء أمورهم ({selectedNode.studentsCount}):
                        </div>
                        <div style={{ display: "flex", flexDirection: "column", gap: 6, marginBottom: 10, maxHeight: 150, overflowY: "auto" }}>
                          {selectedNode.assignedStudents.map((st, idx) => (
                            <div key={idx} style={{ padding: "8px 10px", borderRadius: 8, background: "var(--bg-surface)", border: "1px solid var(--border)", display: "flex", justifyContent: "space-between", alignItems: "center", fontSize: 11.5, fontWeight: 700 }}>
                              <div>
                                <span style={{ color: "var(--text-dark)" }}>🎓 {st}</span>
                                <div style={{ fontSize: 9.5, color: "#64748B" }}>ولي الأمر: د. والد {st.split(" ")[0]}</div>
                              </div>
                              <button
                                onClick={() => {
                                  setNodes(prev => prev.map(n => n.id === selectedNode.id ? { ...n, assignedStudents: n.assignedStudents.filter((_, i) => i !== idx), studentsCount: Math.max(0, n.studentsCount - 1), parentsCount: Math.max(0, n.parentsCount - 1) } : n));
                                  showToast("تم النقل/الحذف", "تم نقل الطالب من هذه الشعبة.", "info");
                                }}
                                style={{ background: "none", border: "none", color: "#EF4444", cursor: "pointer", fontSize: 11, fontWeight: 800 }}
                              >✕ نقل</button>
                            </div>
                          ))}
                        </div>
                        <button
                          onClick={() => {
                            const newSt = "طالب جديد المنصور";
                            setNodes(prev => prev.map(n => n.id === selectedNode.id ? { ...n, assignedStudents: [...n.assignedStudents, newSt], studentsCount: n.studentsCount + 1, parentsCount: n.parentsCount + 1 } : n));
                            showToast("تم التسجيل ✅", "تم إضافة طالب جديد وأسرته إلى الفصل.", "success");
                          }}
                          className="btn btn-ghost btn-sm" style={{ width: "100%", justifyContent: "center", fontSize: 11, fontWeight: 800, color: "#047857", border: "1px dashed #A7F3D0" }}
                        >
                          <UserPlus size={14} /> ＋ تسجيل طالب جديد في الشعبة
                        </button>
                      </div>
                    </div>
                  )}

                  {selectedNode.type === "bus" && (
                    <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
                      <div style={{ background: "var(--bg-page)", padding: 12, borderRadius: 12, border: "1px solid var(--border)" }}>
                        <div style={{ fontSize: 12, fontWeight: 900, color: "#D97706", marginBottom: 8 }}>👨‍✈️ معلومات السائق والمركبة:</div>
                        <div style={{ fontSize: 12, fontWeight: 700, color: "var(--text-dark)", marginBottom: 4 }}>السائق: {selectedNode.driverName || "صالح الرشيدي"}</div>
                        <div style={{ fontSize: 11.5, color: "var(--text-muted)", marginBottom: 4 }}>رقم اللوحة: {selectedNode.plateNumber || "أ ب ج 1234"}</div>
                        <div style={{ fontSize: 11.5, color: "#059669", display: "flex", alignItems: "center", gap: 6, fontWeight: 800 }}>
                          <Phone size={14} /> رقم التواصل: 0501122334
                        </div>
                      </div>
                    </div>
                  )}

                  <div style={{ marginTop: "auto", display: "flex", flexDirection: "column", gap: 8 }}>
                    <button onClick={() => deleteNode(selectedNode.id)} className="btn btn-ghost btn-sm" style={{ justifyContent: "center", gap: 6, color: "#EF4444", fontWeight: 700, background: "#FEE2E2" }}>
                      <Trash2 size={14} /> حذف هذا الكارت نهائياً من الشاشة
                    </button>
                  </div>
                </div>
              ) : (
                <div style={{ width: 250, flexShrink: 0, borderRight: "1px solid var(--border)", background: "var(--bg-surface)", padding: 20, textAlign: "center", display: "flex", flexDirection: "column", justifyContent: "center", alignItems: "center", gap: 12 }}>
                  <div style={{ width: 48, height: 48, borderRadius: "50%", background: "var(--bg-page)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 24 }}>👆</div>
                  <div style={{ fontWeight: 900, fontSize: 14, color: "var(--text-dark)" }}>اختر أي فصل أو حافلة</div>
                  <div style={{ fontSize: 12, color: "var(--text-muted)", lineHeight: 1.6 }}>
                    اضغط على كارت الفصل لعرض قائمة معلميه وطلابه، أو لربطه مباشرة بخط السير من القائمة المنسدلة!
                  </div>
                </div>
              )}

            </div>
          </div>
        )}

        <Footer />
      </div>
    </div>
  );
}
