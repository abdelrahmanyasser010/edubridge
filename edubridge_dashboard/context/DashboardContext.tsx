"use client";

import React, { createContext, useCallback, useContext, useEffect, useState } from "react";
import type {
  Student, Teacher, SchoolSection, Subject, BehaviorNote, BusRoute, BroadcastMessage, AttendanceSummary, Parent
} from "@/data/mockData";
import {
  DashboardApiStatus,
  DASHBOARD_AUTH_EXPIRED_EVENT,
  dashboardErrorMessage,
  AuditLog,
  BroadcastDeliveryCounts,
  DashboardAdminAccount,
  DashboardAssessment,
  DashboardBehaviorNote,
  DashboardBroadcast,
  DashboardCalendarEvent,
  DashboardCanvasConfig,
  DashboardDeviceSession,
  DashboardGradeEntry,
  DashboardLeavePermit,
  DashboardNotification,
  DashboardReportExport,
  DashboardSchool,
  DashboardScheduleConflictResult,
  DashboardScheduleSlot,
  DashboardTransportAssignment,
  DashboardSummary,
  DashboardTransportEvent,
  DashboardTransportPassenger,
  DashboardTransportSummary,
  FinanceDiscount,
  FinanceInvoice,
  FinancePayment,
  FinanceRefund,
  FinanceSummary,
  RbacMatrix,
  RbacPermission,
  RbacRole,
  SchoolIntegration,
  SchoolSettings,
  addBehaviorRecommendation,
  approveAssessment,
  cancelBroadcast as cancelBroadcastRequest,
  createFinanceDiscount as createFinanceDiscountRequest,
  createFinanceInvoice as createFinanceInvoiceRequest,
  createFinancePayment as createFinancePaymentRequest,
  createBroadcast,
  createCalendarEvent,
  createDashboardAdminAccount,
  createParent,
  createParentSummons,
  createRbacRole as createRbacRoleRequest,
  createSection,
  createStudent,
  createSubject,
  createTeacher,
  createTeacherSubstitution,
  DashboardUser,
  approveLeavePermit as approveLeavePermitRequest,
  approveMedicalExcuse as approveMedicalExcuseRequest,
  clearDashboardAuth,
  assignTransportStudent as assignTransportStudentRequest,
  fetchAcademicStructure,
  fetchAuditLogs,
  fetchBroadcasts,
  fetchBroadcastDeliveries,
  fetchCalendarEvents,
  fetchCanvasConfig,
  fetchDashboardSummary,
  fetchDashboardAdminAccounts,
  fetchDashboardAssessments,
  fetchDashboardBehaviorNotes,
  fetchDashboardLeavePermits,
  fetchDashboardSchedules,
  fetchDeviceSessions,
  fetchFinanceDiscounts,
  fetchFinanceInvoices,
  fetchFinancePayments,
  fetchFinanceRefunds,
  fetchFinanceSummary,
  fetchDashboardAssessment as fetchDashboardAssessmentRequest,
  fetchMedicalExcuses,
  fetchNotifications,
  fetchParents,
  fetchRbacMatrix,
  fetchRbacPermissions,
  fetchRbacRoles,
  fetchSchoolIntegrations,
  fetchSchoolSettings,
  fetchStudents,
  fetchTeachers,
  fetchTransportEvents,
  fetchTransportPassengers,
  fetchTransportRoutes,
  fetchTransportSummary,
  fetchReportExport as fetchReportExportRequest,
  getStoredDashboardAuth,
  hasDashboardToken,
  loginDashboard as loginDashboardRequest,
  logTransportDriverContact as logTransportDriverContactRequest,
  logoutDashboard as logoutDashboardRequest,
  checkScheduleConflicts,
  lockAssessment,
  mapApiBehaviorNotes,
  mapApiBroadcasts,
  mapApiMedicalExcuses,
  mapApiParents,
  mapApiSections,
  mapApiStudents,
  mapApiSubjects,
  mapApiTeachers,
  mapApiTransportRoutes,
  markNotificationRead as markNotificationReadRequest,
  parentsFromStudents,
  refreshDashboardIdentity,
  rejectMedicalExcuse as rejectMedicalExcuseRequest,
  rejectLeavePermit as rejectLeavePermitRequest,
  requestAssessmentGradeExport as requestAssessmentGradeExportRequest,
  publishBehaviorNote,
  publishAssessment,
  resolveBehaviorNote as resolveBehaviorNoteRequest,
  saveCanvasConfig,
  sendBroadcastNow,
  sendTransportDelayAlert as sendTransportDelayAlertRequest,
  testSchoolIntegration as testSchoolIntegrationRequest,
  createFinanceRefund as createFinanceRefundRequest,
  createTransportRoute as createTransportRouteRequest,
  updateDashboardAdminRole,
  updateDashboardAdminStatus,
  deleteFinanceInvoice as deleteFinanceInvoiceRequest,
  deleteTransportAssignment as deleteTransportAssignmentRequest,
  deleteTransportRoute as deleteTransportRouteRequest,
  updateFinanceDiscount as updateFinanceDiscountRequest,
  updateDashboardAssessmentGrades as updateDashboardAssessmentGradesRequest,
  updateCalendarEvent,
  deleteCalendarEvent,
  updateRbacMatrix,
  updateSchoolSettings as updateSchoolSettingsRequest,
  updateTransportAssignment as updateTransportAssignmentRequest,
  updateTransportRoute as updateTransportRouteRequest,
  updatePushToken as updatePushTokenRequest,
  fetchDashboardDailyAttendance,
  fetchDashboardAttendanceAtRisk,
  fetchDashboardEarlyWarnings,
  DashboardDailyAttendanceResponse,
  DashboardAtRiskAttendanceResponse,
  DashboardEarlyWarningsResponse,
  DashboardEarlyWarningItem,
} from "@/lib/dashboardApi";

// ── Role-Based Access Control (RBAC) Types ───────────────────────

export interface Role {
  key: "school_admin" | "academic_admin" | "student_affairs" | "finance_officer";
  label: string;
  short: string;
  description: string;
}

export const systemRoles: Role[] = [
  { key: "school_admin", label: "مدير المدرسة", short: "School Admin", description: "صلاحيات الإدارة الكاملة وفق صلاحيات الخادم ومصفوفة RBAC." },
  { key: "academic_admin", label: "المشرف الأكاديمي", short: "Academic Admin", description: "إدارة الهيكل الأكاديمي والجداول والدرجات والتقارير الأكاديمية." },
  { key: "student_affairs", label: "شؤون الطلاب", short: "Student Affairs", description: "متابعة الحضور والسلوك والأعذار وتصاريح الخروج واستدعاءات أولياء الأمور." },
  { key: "finance_officer", label: "المسؤول المالي", short: "Finance Officer", description: "إدارة الفواتير والمدفوعات والاستردادات والتقارير المالية حسب الصلاحيات." },
];

export interface PermissionMatrix {
  [roleKey: string]: {
    can_manage_behavior: boolean;
    can_manage_attendance: boolean;
    can_manage_academic: boolean;
    can_manage_grades: boolean;
    can_manage_operations: boolean;
    can_manage_fleet: boolean;
    can_manage_rbac: boolean;
    can_send_broadcasts: boolean;
  };
}

const initialPermissionMatrix: PermissionMatrix = {
  school_admin: { can_manage_behavior: true, can_manage_attendance: true, can_manage_academic: true, can_manage_grades: true, can_manage_operations: true, can_manage_fleet: true, can_manage_rbac: true, can_send_broadcasts: true },
  academic_admin: { can_manage_behavior: false, can_manage_attendance: false, can_manage_academic: true, can_manage_grades: true, can_manage_operations: false, can_manage_fleet: false, can_manage_rbac: false, can_send_broadcasts: false },
  student_affairs: { can_manage_behavior: true, can_manage_attendance: true, can_manage_academic: false, can_manage_grades: false, can_manage_operations: true, can_manage_fleet: false, can_manage_rbac: false, can_send_broadcasts: false },
  finance_officer: { can_manage_behavior: false, can_manage_attendance: false, can_manage_academic: false, can_manage_grades: false, can_manage_operations: false, can_manage_fleet: false, can_manage_rbac: false, can_send_broadcasts: false },
};

export interface AdminAccount {
  id: string;
  name: string;
  email: string;
  phone: string;
  roleKey: Role["key"];
  roleLabel: string;
  status: "active" | "inactive";
  lastLogin: string;
}

const initialAdminAccounts: AdminAccount[] = [];

// ── Production-Grade Educational Entities ────────────────────────

export interface MedicalExcuse {
  id: string;
  studentId: string;
  studentName: string;
  sectionName: string;
  absenceDate: string;
  hospitalName: string;
  reason: string;
  status: "pending" | "approved" | "rejected";
  submittedBy: string;
}

export interface LeavePermit {
  id: string;
  studentId: string;
  studentName: string;
  sectionName: string;
  requestTime: string;
  parentName: string;
  reason: string;
  pickupType: "ولي الأمر شخصياً" | "سائق مفوض" | "أحد الأقارب";
  status: "waiting_gate" | "released" | "rejected";
  gatePassCode: string;
}

export interface ParentSummons {
  id: string;
  studentId: string;
  studentName: string;
  sectionName: string;
  parentName: string;
  parentPhone: string;
  reason: string;
  meetingDate: string;
  meetingTime: string;
  supervisorName: string;
  status: "scheduled" | "attended" | "rescheduled";
}

export interface SubstituteAssignment {
  id: string;
  absentTeacherId: string;
  absentTeacherName: string;
  substituteTeacherId: string;
  substituteTeacherName: string;
  sectionName: string;
  subjectName: string;
  period: number;
  date: string;
  status: "assigned" | "completed";
}

// ── Initial Mock Data for New Operations ─────────────────────────

const initialMedicalExcuses: MedicalExcuse[] = [
  { id: "exc1", studentId: "st3", studentName: "فيصل سعد الدوسري", sectionName: "الصف الخامس / شعبة ب", absenceDate: "2026-07-05", hospitalName: "مستشفى الحبيب الطيبي", reason: "التهاب لوزتين وحمى حادة (تقرير منصة صحتي)", status: "pending", submittedBy: "سعد الدوسري (ولي الأمر)" },
  { id: "exc2", studentId: "st7", studentName: "راشد إبراهيم الشهري", sectionName: "الصف السادس / شعبة ب", absenceDate: "2026-07-04", hospitalName: "مجمع عيادات الأسرة", reason: "إصابة رياضية في الكاحل مع راحة لمدة يومين", status: "pending", submittedBy: "إبراهيم الشهري (ولي الأمر)" },
  { id: "exc3", studentId: "st5", studentName: "تركي ناصر القرني", sectionName: "الصف السادس / شعبة أ", absenceDate: "2026-07-03", hospitalName: "مركز صحي الياسمين", reason: "فحص دوري واستشارة طبيب أسنان", status: "approved", submittedBy: "ناصر القرني (ولي الأمر)" },
];

const initialLeavePermits: LeavePermit[] = [
  { id: "perm1", studentId: "st2", studentName: "سارة عبدالله المالكي", sectionName: "الصف الخامس / شعبة أ", requestTime: "11:30 ص", parentName: "عبدالله المالكي", reason: "موعد مراجعة في السفارة للحصول على تأشيرة", pickupType: "ولي الأمر شخصياً", status: "waiting_gate", gatePassCode: "PASS-8842" },
  { id: "perm2", studentId: "st8", studentName: "دانا فهد السلمي", sectionName: "الصف الرابع / شعبة أ", requestTime: "12:15 م", parentName: "فهد السلمي", reason: "ظرف عائلي طارئ", pickupType: "سائق مفوض", status: "released", gatePassCode: "PASS-9103" },
];

const initialSummons: ParentSummons[] = [
  { id: "sum1", studentId: "st3", studentName: "فيصل سعد الدوسري", sectionName: "الصف الخامس / شعبة ب", parentName: "سعد الدوسري", parentPhone: "0501112233", reason: "تكرار المشاجرات الجسدية وتراجع المستوى الدراسي", meetingDate: "2026-07-08", meetingTime: "10:00 صباحاً", supervisorName: "الأستاذ فهد العتيبي (وكيل شؤون الطلاب)", status: "scheduled" },
  { id: "sum2", studentId: "st7", studentName: "راشد إبراهيم الشهري", sectionName: "الصف السادس / شعبة ب", parentName: "إبراهيم الشهري", parentPhone: "0502223344", reason: "تجاوز غياب الطالب 6 أيام بدون تقديم أعذار رسمية", meetingDate: "2026-07-09", meetingTime: "11:30 صباحاً", supervisorName: "الأستاذ فهد العتيبي (وكيل شؤون الطلاب)", status: "scheduled" },
];

const initialSubstitutes: SubstituteAssignment[] = [
  { id: "sub_a1", absentTeacherId: "t7", absentTeacherName: "هند علي المطيري", substituteTeacherId: "t1", substituteTeacherName: "نورة خالد الشمري", sectionName: "الصف الخامس / شعبة أ", subjectName: "التربية الاجتماعية (تغطية)", period: 3, date: "2026-07-06", status: "assigned" },
  { id: "sub_a2", absentTeacherId: "t7", absentTeacherName: "هند علي المطيري", substituteTeacherId: "t2", substituteTeacherName: "سامي عبدالله العتيبي", sectionName: "الصف السادس / شعبة أ", subjectName: "التربية الاجتماعية (تغطية)", period: 5, date: "2026-07-06", status: "assigned" },
];

// ── Context Interface ────────────────────────────────────────────

interface ToastMsg {
  id: string;
  title: string;
  message: string;
  type: "success" | "info" | "warning" | "error";
}

type StudentCreateInput = Omit<Student, "id" | "studentCode"> & {
  parentPhone?: string;
  parentEmail?: string;
};

type TransportRouteInput = {
  name: string;
  code?: string;
  capacity?: number;
  driver_name?: string | null;
  plate_number?: string | null;
  driver_phone?: string | null;
  supervisor_name?: string | null;
  estimated_arrival_time?: string | null;
};

type AssessmentGradeEntryInput = {
  student_id: string | number;
  score?: number | null;
  feedback?: string | null;
  note?: string | null;
  revision?: number | null;
};

interface DashboardContextType {
  // RBAC & Roles
  currentRole: Role;
  switchRole: (role: Role) => void;
  permissionMatrix: PermissionMatrix;
  updatePermission: (roleKey: string, permKey: string, value: boolean) => void;
  hasPermission: (permKey: keyof PermissionMatrix[string]) => boolean;
  adminAccounts: AdminAccount[];
  addAdminAccount: (acc: Omit<AdminAccount, "id" | "lastLogin">) => void;
  updateAdminRole: (accId: string, roleKey: Role["key"]) => void;

  // Data
  students: Student[];
  teachers: Teacher[];
  parents: Parent[];
  sections: SchoolSection[];
  subjects: Subject[];
  behaviorNotes: BehaviorNote[];
  busRoutes: BusRoute[];
  messages: BroadcastMessage[];
  attendance: AttendanceSummary;
  medicalExcuses: MedicalExcuse[];
  leavePermits: LeavePermit[];
  parentSummons: ParentSummons[];
  substitutes: SubstituteAssignment[];
  toasts: ToastMsg[];

  // Backend Integration
  apiStatus: DashboardApiStatus;
  apiError: string | null;
  isAuthenticated: boolean;
  currentUser: DashboardUser | null;
  currentSchool: DashboardSchool | null;
  backendPermissions: string[];
  hasApiPermission: (permission: string) => boolean;
  dashboardSummary: DashboardSummary | null;
  deviceSessions: DashboardDeviceSession[];
  notifications: DashboardNotification[];
  financeSummary: FinanceSummary | null;
  financeInvoices: FinanceInvoice[];
  financePayments: FinancePayment[];
  financeDiscounts: FinanceDiscount[];
  financeRefunds: FinanceRefund[];
  broadcastDeliveries: Record<string, BroadcastDeliveryCounts>;
  transportSummary: DashboardTransportSummary | null;
  transportPassengers: Record<string, DashboardTransportPassenger[]>;
  transportEvents: Record<string, DashboardTransportEvent[]>;
  schoolSettings: SchoolSettings | null;
  schoolIntegrations: SchoolIntegration[];
  auditLogs: AuditLog[];
  rbacRoles: RbacRole[];
  rbacPermissions: RbacPermission[];
  rbacMatrix: RbacMatrix | null;
  broadcasts: DashboardBroadcast[];
  dashboardBehaviorNotes: DashboardBehaviorNote[];
  dashboardLeavePermits: DashboardLeavePermit[];
  dashboardSchedules: DashboardScheduleSlot[];
  scheduleConflictResult: DashboardScheduleConflictResult | null;
  calendarEvents: DashboardCalendarEvent[];
  dashboardAssessments: DashboardAssessment[];
  reportExports: Record<string, DashboardReportExport>;
  canvasConfig: DashboardCanvasConfig | null;
  dailyAttendance: DashboardDailyAttendanceResponse | null;
  attendanceAtRisk: DashboardAtRiskAttendanceResponse | null;
  earlyWarnings: DashboardEarlyWarningsResponse | null;
  fetchDailyAttendanceByDateAndSection: (date?: string, sectionId?: string, status?: string) => Promise<DashboardDailyAttendanceResponse | null>;
  fetchAtRiskStudents: (termId: number | string, sectionId?: string) => Promise<DashboardAtRiskAttendanceResponse | null>;
  fetchEarlyWarningsData: (sectionId?: string, minScore?: number) => Promise<DashboardEarlyWarningsResponse | null>;
  
  // Actions
  showToast: (title: string, message: string, type?: "success" | "info" | "warning" | "error") => void;
  removeToast: (id: string) => void;
  loginDashboard: (email: string, password: string) => Promise<void>;
  logoutDashboard: () => Promise<void>;
  refreshDashboardData: () => Promise<void>;
  markNotificationRead: (id: string) => Promise<void>;
  updatePushToken: (token: string) => Promise<void>;
  refreshTransportRouteDetails: (routeId: string) => Promise<void>;
  createDashboardTransportRoute: (input: TransportRouteInput) => Promise<void>;
  updateDashboardTransportRoute: (routeId: string, input: Partial<TransportRouteInput>) => Promise<void>;
  archiveDashboardTransportRoute: (routeId: string) => Promise<void>;
  assignStudentToTransportRoute: (routeId: string, studentId: string) => Promise<DashboardTransportAssignment | null>;
  updateStudentTransportAssignment: (routeId: string, assignmentId: string, input: { valid_from?: string; valid_until?: string | null; status?: "active" | "archived" }) => Promise<void>;
  archiveStudentTransportAssignment: (routeId: string, assignmentId: string) => Promise<void>;
  sendTransportDelayAlert: (routeId: string, delayMinutes: number, message: string) => Promise<void>;
  logTransportDriverContact: (routeId: string, outcome?: "called" | "no_answer" | "message_sent" | "wrong_number", notes?: string) => Promise<void>;
  saveSchoolSettings: (settings: Partial<SchoolSettings>) => Promise<void>;
  testSchoolIntegration: (integration: string) => Promise<void>;
  createFinanceInvoiceForStudent: (studentId: string, title: string, amount: number) => Promise<void>;
  recordFinancePayment: (invoiceId: string, amount: number) => Promise<void>;
  createFinanceDiscountForStudent: (studentId: string, title: string, amount: number) => Promise<void>;
  cancelFinanceInvoice: (invoiceId: string) => Promise<void>;
  archiveFinanceDiscount: (discountId: string) => Promise<void>;
  createFinanceRefundForPayment: (paymentId: string, amount: number, reason: string) => Promise<void>;
  cancelDashboardBroadcast: (broadcastId: string) => Promise<void>;
  loadBroadcastDeliveries: (broadcastId: string) => Promise<void>;
  updateSchoolCalendarEvent: (id: string, input: {
    title?: string;
    date?: string;
    time?: string;
    location?: string;
    category?: string;
    target?: string;
  }) => Promise<void>;
  deleteSchoolCalendarEvent: (id: string) => Promise<void>;
  updateAdminStatus: (accountId: string, status: "active" | "suspended") => Promise<void>;
  createDashboardRole: (key: string, name: string) => Promise<void>;
  addAcademicSection: (name: string, code: string, gradeLevelId?: string, capacity?: number) => void;
  addAcademicSubject: (name: string, code: string, gradeLevelIds?: string[]) => void;
  runScheduleConflictCheck: () => Promise<void>;
  createSchoolCalendarEvent: (input: {
    title: string;
    date: string;
    time?: string;
    location?: string;
    category?: string;
    target?: string;
  }) => Promise<void>;
  saveConfiguratorCanvas: (payload: Record<string, unknown>, expectedVersion?: number | null) => Promise<void>;
  
  // Student & Family Linking
  addStudent: (student: StudentCreateInput) => void;
  sendParentWarning: (studentId: string, reason: string) => void;
  
  // Teacher Management
  addTeacher: (teacher: Omit<Teacher, "id" | "kpiScore" | "lessonsThisWeek" | "notesCount">) => void;
  assignSubstitute: (absentTeacherId: string, subTeacherId: string, sectionName: string, period: number) => void;
  
  // Behavior & Conduct Governance
  approveBehaviorNote: (noteId: string) => void;
  attachRecommendation: (noteId: string, recTitle: string, recDesc: string) => void;
  resolveBehaviorNote: (noteId: string) => void;
  
  // Attendance & Excuses Governance
  approveMedicalExcuse: (excuseId: string) => void;
  rejectMedicalExcuse: (excuseId: string) => void;
  issueParentSummons: (studentId: string, reason: string, meetingDate: string, meetingTime: string) => void;
  
  // Security Gate & Leave Permits
  approveLeavePermit: (permitId: string) => void;
  
  // Broadcasts & Notifications
  sendBroadcast: (title: string, body: string, target: string, type: "تعميم" | "تنبيه" | "تهنئة") => void;
  scheduleBroadcast: (title: string, body: string, target: string, type: "تعميم" | "تنبيه" | "تهنئة", scheduledAt: string) => void;
  
  // Grades Control
  approveSectionGrades: (sectionName: string) => void;
  updateAssessmentGradesFromDashboard: (assessmentId: string, entries: AssessmentGradeEntryInput[]) => Promise<DashboardGradeEntry[]>;
  requestGradeSheetExport: (assessmentId: string) => Promise<DashboardReportExport | null>;
  refreshReportExport: (exportId: string) => Promise<DashboardReportExport | null>;

  // Responsive Mobile Menu & Sidebar Collapse
  mobileMenuOpen: boolean;
  setMobileMenuOpen: (open: boolean) => void;
  sidebarCollapsed: boolean;
  setSidebarCollapsed: (collapsed: boolean) => void;
  toggleSidebar: () => void;
}

const DashboardContext = createContext<DashboardContextType | undefined>(undefined);
const permissionAliases: Record<keyof PermissionMatrix[string], string[]> = {
  can_manage_behavior: ["behavior.review", "behavior.publish", "behavior.resolve"],
  can_manage_attendance: ["attendance.amend", "attendance.review_excuse"],
  can_manage_academic: ["academic.manage", "schedule.manage"],
  can_manage_grades: ["grade.enter", "grade.approve", "grade.publish", "grade.lock"],
  can_manage_operations: ["operations.leave_review", "operations.summons_manage", "operations.substitution_manage"],
  can_manage_fleet: ["transport.manage", "transport.alerts.send"],
  can_manage_rbac: ["rbac.manage"],
  can_send_broadcasts: ["broadcasts.send", "broadcasts.schedule"],
};

function matrixFromBackend(matrix: RbacMatrix): PermissionMatrix {
  const next: PermissionMatrix = { ...initialPermissionMatrix };

  matrix.roles.forEach((role) => {
    const roleKey = role.key as Role["key"];
    if (!systemRoles.some((item) => item.key === roleKey)) return;

    next[roleKey] = Object.fromEntries(
      Object.entries(permissionAliases).map(([localKey, aliases]) => [
        localKey,
        aliases.some((permission) => role.permissions[permission]),
      ]),
    ) as PermissionMatrix[string];
  });

  return next;
}

function adminAccountsFromBackend(accounts: DashboardAdminAccount[]): AdminAccount[] {
  return accounts.map((account) => {
    const role = systemRoles.find((item) => item.key === account.role_key) ?? systemRoles[0];

    return {
      id: account.id,
      name: account.name ?? account.email ?? "Dashboard admin",
      email: account.email ?? "",
      phone: account.phone ?? "",
      roleKey: role.key,
      roleLabel: account.role_label ?? role.label,
      status: account.status === "suspended" ? "inactive" : "active",
      lastLogin: account.last_login_at ?? "Not recorded",
    };
  });
}

function backendPermissionsFromLocal(localPermissions: PermissionMatrix[string], availablePermissions: string[]): string[] {
  return Object.entries(localPermissions)
    .flatMap(([localKey, enabled]) => {
      if (!enabled) return [];
      const aliases = permissionAliases[localKey as keyof PermissionMatrix[string]] ?? [];
      const match = aliases.find((permission) => availablePermissions.includes(permission));
      return match ? [match] : [];
    })
    .sort();
}

function dashboardBroadcastType(type: string): "announcement" | "alert" | "reminder" {
  return type === "تنبيه" ? "alert" : "announcement";
}

function dashboardBroadcastTarget(target: string): { type: string; ids: string[] } {
  if (target.includes("معلم")) return { type: "teachers", ids: [] };
  if (target.includes("ولي") || target.includes("أولياء")) return { type: "parents", ids: [] };
  if (target.includes("طالب") || target.includes("طلاب")) return { type: "students", ids: [] };
  return { type: "all", ids: [] };
}

function isBackendId(id: string | undefined | null) {
  return typeof id === "string" && /^\d+$/.test(id);
}

function generatedNumber(prefix: string) {
  return `${prefix}-${Date.now().toString(36).toUpperCase()}`;
}

function futureScheduledAt(meetingDate: string, meetingTime: string) {
  const timeMatch = meetingTime.match(/(\d{1,2}):(\d{2})/);
  const hours = timeMatch ? Number(timeMatch[1]) : 10;
  const minutes = timeMatch ? Number(timeMatch[2]) : 0;
  const scheduled = new Date(`${meetingDate}T00:00:00`);

  if (Number.isNaN(scheduled.getTime())) {
    scheduled.setTime(Date.now());
  }

  scheduled.setHours(hours, minutes, 0, 0);

  if (scheduled.getTime() <= Date.now()) {
    scheduled.setTime(Date.now());
    scheduled.setDate(scheduled.getDate() + 1);
    scheduled.setHours(hours, minutes, 0, 0);
  }

  return scheduled.toISOString();
}

function displayDate(value?: string | null) {
  return value ? value.split("T")[0] : new Date().toISOString().split("T")[0];
}

function displayTime(value?: string | null) {
  if (!value) return "";
  return new Date(value).toLocaleTimeString("ar-EG", { hour: "2-digit", minute: "2-digit" });
}

function mapDashboardLeavePermits(permits: DashboardLeavePermit[]): LeavePermit[] {
  return permits.map((permit) => ({
    id: permit.id,
    studentId: permit.student_id ?? "",
    studentName: permit.student_name ?? "Unassigned student",
    sectionName: permit.section_name ?? "Unassigned section",
    requestTime: displayTime(permit.requested_leave_at),
    parentName: permit.parent_name ?? "Unassigned parent",
    reason: permit.reason ?? "",
    pickupType: "ولي الأمر شخصياً",
    status:
      permit.status === "rejected"
        ? "rejected"
        : permit.status === "approved" || permit.status === "used"
          ? "released"
          : "waiting_gate",
    gatePassCode: `PERMIT-${permit.id}`,
  }));
}

function calendarType(category?: string): "event" | "holiday" | "exam" | "meeting" | "deadline" {
  if (category?.includes("قياس") || category?.includes("اختبار")) return "exam";
  if (category?.includes("مجالس") || category?.includes("لقاء")) return "meeting";
  if (category?.includes("عطلة")) return "holiday";
  if (category?.includes("موعد")) return "deadline";
  return "event";
}

function calendarAudience(target?: string): "all" | "grade_level" | "section" | "roles" | "custom_users" {
  if (target?.includes("شعبة")) return "section";
  if (target?.includes("الصف")) return "grade_level";
  if (target?.includes("معلم")) return "roles";
  return "all";
}

export function DashboardProvider({ children }: { children: React.ReactNode }) {
  // RBAC State
  const [currentRole, setCurrentRole] = useState<Role>(systemRoles[0]);
  const [permissionMatrix, setPermissionMatrix] = useState<PermissionMatrix>(initialPermissionMatrix);
  const [adminAccounts, setAdminAccounts] = useState<AdminAccount[]>(initialAdminAccounts);
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState<boolean>(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem("edubridge_sidebar_collapsed");
      if (stored === "true") {
        setSidebarCollapsed(true);
        if (typeof document !== "undefined") {
          document.body.classList.add("sidebar-collapsed");
        }
      }
    } catch {}
  }, []);

  const toggleSidebar = useCallback(() => {
    setSidebarCollapsed((prev) => {
      const next = !prev;
      try {
        localStorage.setItem("edubridge_sidebar_collapsed", String(next));
        if (typeof document !== "undefined") {
          if (next) {
            document.body.classList.add("sidebar-collapsed");
          } else {
            document.body.classList.remove("sidebar-collapsed");
          }
        }
      } catch {}
      return next;
    });
  }, []);
  const [currentUser, setCurrentUser] = useState<DashboardUser | null>(() => getStoredDashboardAuth().user);
  const [currentSchool, setCurrentSchool] = useState<DashboardSchool | null>(() => getStoredDashboardAuth().school);
  const [backendPermissions, setBackendPermissions] = useState<string[]>([]);
  const [dashboardSummary, setDashboardSummary] = useState<DashboardSummary | null>(null);
  const [deviceSessions, setDeviceSessions] = useState<DashboardDeviceSession[]>([]);
  const [notifications, setNotifications] = useState<DashboardNotification[]>([]);
  const [financeSummary, setFinanceSummary] = useState<FinanceSummary | null>(null);
  const [financeInvoices, setFinanceInvoices] = useState<FinanceInvoice[]>([]);
  const [financePayments, setFinancePayments] = useState<FinancePayment[]>([]);
  const [financeDiscounts, setFinanceDiscounts] = useState<FinanceDiscount[]>([]);
  const [financeRefunds, setFinanceRefunds] = useState<FinanceRefund[]>([]);
  const [broadcastDeliveries, setBroadcastDeliveries] = useState<Record<string, BroadcastDeliveryCounts>>({});
  const [transportSummary, setTransportSummary] = useState<DashboardTransportSummary | null>(null);
  const [transportPassengers, setTransportPassengers] = useState<Record<string, DashboardTransportPassenger[]>>({});
  const [transportEvents, setTransportEvents] = useState<Record<string, DashboardTransportEvent[]>>({});
  const [schoolSettings, setSchoolSettings] = useState<SchoolSettings | null>(null);
  const [schoolIntegrations, setSchoolIntegrations] = useState<SchoolIntegration[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>([]);
  const [rbacRoles, setRbacRoles] = useState<RbacRole[]>([]);
  const [rbacPermissions, setRbacPermissions] = useState<RbacPermission[]>([]);
  const [rbacMatrix, setRbacMatrix] = useState<RbacMatrix | null>(null);
  const [broadcasts, setBroadcasts] = useState<DashboardBroadcast[]>([]);
  const [dashboardBehaviorNotes, setDashboardBehaviorNotes] = useState<DashboardBehaviorNote[]>([]);
  const [dashboardLeavePermits, setDashboardLeavePermits] = useState<DashboardLeavePermit[]>([]);
  const [dashboardSchedules, setDashboardSchedules] = useState<DashboardScheduleSlot[]>([]);
  const [scheduleConflictResult, setScheduleConflictResult] = useState<DashboardScheduleConflictResult | null>(null);
  const [calendarEvents, setCalendarEvents] = useState<DashboardCalendarEvent[]>([]);
  const [dashboardAssessments, setDashboardAssessments] = useState<DashboardAssessment[]>([]);
  const [reportExports, setReportExports] = useState<Record<string, DashboardReportExport>>({});
  const [canvasConfig, setCanvasConfig] = useState<DashboardCanvasConfig | null>(null);
  const [apiStatus, setApiStatus] = useState<DashboardApiStatus>(() => (hasDashboardToken() ? "loading" : "mock"));
  const [apiError, setApiError] = useState<string | null>(null);

  // Entities State
  const [students, setStudents] = useState<Student[]>([]);
  const [teachers, setTeachers] = useState<Teacher[]>([]);
  const [parents, setParents] = useState<Parent[]>([]);
  const [sections, setSections] = useState<SchoolSection[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [behaviorNotes, setBehaviorNotes] = useState<BehaviorNote[]>([]);
  const [busRoutes, setBusRoutes] = useState<BusRoute[]>([]);
  const [messages, setMessages] = useState<BroadcastMessage[]>([]);
  const [attendance, setAttendance] = useState<AttendanceSummary>({
    date: new Date().toISOString().slice(0, 10), total: 0, present: 0, absent: 0, late: 0, excused: 0, sectionBreakdown: [],
  });
  const [medicalExcuses, setMedicalExcuses] = useState<MedicalExcuse[]>([]);
  const [leavePermits, setLeavePermits] = useState<LeavePermit[]>([]);
  const [parentSummons, setParentSummons] = useState<ParentSummons[]>([]);
  const [substitutes, setSubstitutes] = useState<SubstituteAssignment[]>([]);
  const [dailyAttendance, setDailyAttendance] = useState<DashboardDailyAttendanceResponse | null>(null);
  const [attendanceAtRisk, setAttendanceAtRisk] = useState<DashboardAtRiskAttendanceResponse | null>(null);
  const [earlyWarnings, setEarlyWarnings] = useState<DashboardEarlyWarningsResponse | null>(null);
  const [toasts, setToasts] = useState<ToastMsg[]>([]);

  // Toast Helper
  const showToast = (title: string, message: string, type: "success" | "info" | "warning" | "error" = "success") => {
    const id = Math.random().toString(36).substring(2, 9);
    setToasts((prev) => [{ id, title, message, type }, ...prev]);
    setTimeout(() => removeToast(id), 5000);
  };

  const removeToast = (id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  };

  const refreshDashboardData = useCallback(async () => {
    if (!hasDashboardToken()) {
      setApiStatus("mock");
      setApiError(null);
      setBackendPermissions([]);
      return;
    }

    setApiStatus("loading");
    setApiError(null);

    try {
      const [
        identityResult,
        summaryResult,
        academicResult,
        teachersResult,
        studentsResult,
        parentsResult,
        sessionsResult,
        notificationsResult,
        medicalExcusesResult,
        financeSummaryResult,
        financeInvoicesResult,
        financePaymentsResult,
        financeDiscountsResult,
        financeRefundsResult,
        transportSummaryResult,
        transportRoutesResult,
        schoolSettingsResult,
        schoolIntegrationsResult,
        auditLogsResult,
        rbacRolesResult,
        rbacPermissionsResult,
        rbacMatrixResult,
        adminAccountsResult,
        broadcastsResult,
        behaviorNotesResult,
        leavePermitsResult,
        schedulesResult,
        calendarEventsResult,
        assessmentsResult,
        canvasConfigResult,
        dailyAttendanceResult,
        earlyWarningsResult,
      ] = await Promise.allSettled([
        refreshDashboardIdentity(),
        fetchDashboardSummary(),
        fetchAcademicStructure(),
        fetchTeachers({ per_page: 100 }),
        fetchStudents({ per_page: 100 }),
        fetchParents({ per_page: 100 }),
        fetchDeviceSessions(),
        fetchNotifications({ per_page: 30 }),
        fetchMedicalExcuses({ per_page: 100 }),
        fetchFinanceSummary(),
        fetchFinanceInvoices({ per_page: 25 }),
        fetchFinancePayments({ per_page: 25 }),
        fetchFinanceDiscounts({ per_page: 25 }),
        fetchFinanceRefunds({ per_page: 25 }),
        fetchTransportSummary(),
        fetchTransportRoutes({ per_page: 100 }),
        fetchSchoolSettings(),
        fetchSchoolIntegrations(),
        fetchAuditLogs({ per_page: 25 }),
        fetchRbacRoles(),
        fetchRbacPermissions(),
        fetchRbacMatrix(),
        fetchDashboardAdminAccounts(),
        fetchBroadcasts({ per_page: 50 }),
        fetchDashboardBehaviorNotes({ per_page: 100 }),
        fetchDashboardLeavePermits({ per_page: 100 }),
        fetchDashboardSchedules({ per_page: 100 }),
        fetchCalendarEvents({ per_page: 100 }),
        fetchDashboardAssessments({ per_page: 100 }),
        fetchCanvasConfig("main-configurator"),
        fetchDashboardDailyAttendance(),
        fetchDashboardEarlyWarnings(),
      ] as const);

      let successCount = 0;
      let liveSections: SchoolSection[] | null = null;
      let liveParents: Parent[] | null = null;
      let liveStudents: Student[] | null = null;

      if (identityResult.status === "fulfilled") {
        successCount += 1;
        setCurrentUser(identityResult.value.user);
        setCurrentSchool(identityResult.value.school);
        setBackendPermissions(identityResult.value.permissions ?? []);
        const resolvedRole = systemRoles.find((role) => role.key === identityResult.value.role?.key);
        if (resolvedRole) setCurrentRole(resolvedRole);
        const deviceSession = identityResult.value.device_session;
        if (deviceSession) {
          setDeviceSessions((prev) => {
            const exists = prev.some((session) => session.id === deviceSession.id);
            return exists ? prev : [deviceSession, ...prev];
          });
        }
      }

      if (summaryResult.status === "fulfilled") {
        successCount += 1;
        setDashboardSummary(summaryResult.value);
        if (summaryResult.value.attendance_today) {
          const a = summaryResult.value.attendance_today;
          setAttendance({
            date: new Date().toISOString().slice(0, 10),
            total: a.total,
            present: a.present,
            absent: a.absent,
            late: a.late,
            excused: a.excused,
            sectionBreakdown: [],
          });
        }
      }

      if (academicResult.status === "fulfilled") {
        successCount += 1;
        const nextSections = mapApiSections(
          academicResult.value.sections ?? [],
          academicResult.value.grade_levels ?? [],
        );
        const nextSubjects = mapApiSubjects(academicResult.value.subjects ?? []);

        liveSections = nextSections;
        setSections(nextSections);
        setSubjects(nextSubjects);
      }

      if (parentsResult.status === "fulfilled") {
        successCount += 1;
        const nextParents = mapApiParents(parentsResult.value);
        liveParents = nextParents;
        setParents(nextParents);
      }

      if (teachersResult.status === "fulfilled") {
        successCount += 1;
        const nextTeachers = mapApiTeachers(teachersResult.value);
        setTeachers(nextTeachers);
      }

      if (earlyWarningsResult.status === "fulfilled") {
        successCount += 1;
        setEarlyWarnings(earlyWarningsResult.value);
      }

      if (dailyAttendanceResult.status === "fulfilled") {
        successCount += 1;
        setDailyAttendance(dailyAttendanceResult.value);
      }

      if (studentsResult.status === "fulfilled") {
        successCount += 1;
        const warningsList = earlyWarningsResult.status === "fulfilled" ? earlyWarningsResult.value.students : [];
        const nextStudents = mapApiStudents(
          studentsResult.value,
          liveSections ?? [],
          liveParents ?? [],
          warningsList,
        );

        liveStudents = nextStudents;
        setStudents(nextStudents);
        if (!liveParents) {
          setParents(parentsFromStudents(nextStudents));
        }
        if (liveSections) {
          const counts = new Map<string, number>();
          nextStudents.forEach((student) => counts.set(student.sectionId, (counts.get(student.sectionId) ?? 0) + 1));
          const countedSections = liveSections.map((section) => ({ ...section, enrolledCount: counts.get(section.id) ?? 0 }));
          liveSections = countedSections;
          setSections(countedSections);
        }
      }

      if (sessionsResult.status === "fulfilled") {
        successCount += 1;
        setDeviceSessions(sessionsResult.value);
      }

      if (notificationsResult.status === "fulfilled") {
        successCount += 1;
        setNotifications(notificationsResult.value);
      }

      if (medicalExcusesResult.status === "fulfilled") {
        successCount += 1;
        setMedicalExcuses(
          mapApiMedicalExcuses(
            medicalExcusesResult.value,
            liveStudents ?? [],
            liveParents ?? parentsFromStudents(liveStudents ?? []),
            liveSections ?? [],
          ),
        );
      }

      if (financeSummaryResult.status === "fulfilled") {
        successCount += 1;
        setFinanceSummary(financeSummaryResult.value);
      }

      if (financeInvoicesResult.status === "fulfilled") {
        successCount += 1;
        setFinanceInvoices(financeInvoicesResult.value);
      }

      if (financePaymentsResult.status === "fulfilled") {
        successCount += 1;
        setFinancePayments(financePaymentsResult.value);
      }

      if (financeDiscountsResult.status === "fulfilled") {
        successCount += 1;
        setFinanceDiscounts(financeDiscountsResult.value);
      }

      if (financeRefundsResult.status === "fulfilled") {
        successCount += 1;
        setFinanceRefunds(financeRefundsResult.value);
      }

      if (transportSummaryResult.status === "fulfilled") {
        successCount += 1;
        setTransportSummary(transportSummaryResult.value);
      }

      if (transportRoutesResult.status === "fulfilled") {
        successCount += 1;
        const nextBusRoutes = mapApiTransportRoutes(transportRoutesResult.value);
        setBusRoutes(nextBusRoutes);
      }

      if (schoolSettingsResult.status === "fulfilled") {
        successCount += 1;
        setSchoolSettings(schoolSettingsResult.value);
      }

      if (schoolIntegrationsResult.status === "fulfilled") {
        successCount += 1;
        setSchoolIntegrations(schoolIntegrationsResult.value);
      }

      if (auditLogsResult.status === "fulfilled") {
        successCount += 1;
        setAuditLogs(auditLogsResult.value);
      }

      if (rbacRolesResult.status === "fulfilled") {
        successCount += 1;
        setRbacRoles(rbacRolesResult.value);
      }

      if (rbacPermissionsResult.status === "fulfilled") {
        successCount += 1;
        setRbacPermissions(rbacPermissionsResult.value);
      }

      if (rbacMatrixResult.status === "fulfilled") {
        successCount += 1;
        setRbacMatrix(rbacMatrixResult.value);
        setPermissionMatrix(matrixFromBackend(rbacMatrixResult.value));
      }

      if (adminAccountsResult.status === "fulfilled") {
        successCount += 1;
        setAdminAccounts(adminAccountsFromBackend(adminAccountsResult.value));
      }

      if (broadcastsResult.status === "fulfilled") {
        successCount += 1;
        setBroadcasts(broadcastsResult.value);
        const nextMessages = mapApiBroadcasts(broadcastsResult.value);
        setMessages(nextMessages);
      }

      if (behaviorNotesResult.status === "fulfilled") {
        successCount += 1;
        setDashboardBehaviorNotes(behaviorNotesResult.value);
        const nextBehaviorNotes = mapApiBehaviorNotes(behaviorNotesResult.value);
        setBehaviorNotes(nextBehaviorNotes);
      }

      if (leavePermitsResult.status === "fulfilled") {
        successCount += 1;
        setDashboardLeavePermits(leavePermitsResult.value);
        const nextLeavePermits = mapDashboardLeavePermits(leavePermitsResult.value);
        setLeavePermits(nextLeavePermits);
      }

      if (schedulesResult.status === "fulfilled") {
        successCount += 1;
        setDashboardSchedules(schedulesResult.value);
      }

      if (calendarEventsResult.status === "fulfilled") {
        successCount += 1;
        setCalendarEvents(calendarEventsResult.value);
      }

      if (assessmentsResult.status === "fulfilled") {
        successCount += 1;
        setDashboardAssessments(assessmentsResult.value);
      }

      if (canvasConfigResult.status === "fulfilled") {
        successCount += 1;
        setCanvasConfig(canvasConfigResult.value);
      }

      if (successCount > 0) {
        setApiStatus("live");
      } else {
        setApiStatus("error");
        setApiError("Unable to reach the dashboard API with the current token.");
      }
    } catch (error) {
      setApiStatus("error");
      setApiError(error instanceof Error ? error.message : "Unable to refresh dashboard API data.");
    }
  }, []);

  useEffect(() => {
    const handleExpiredSession = () => {
      setCurrentUser(null);
      setCurrentSchool(null);
      setBackendPermissions([]);
      setApiStatus("error");
      setApiError("انتهت الجلسة. سجّل الدخول من جديد.");
    };
    window.addEventListener(DASHBOARD_AUTH_EXPIRED_EVENT, handleExpiredSession);
    return () => window.removeEventListener(DASHBOARD_AUTH_EXPIRED_EVENT, handleExpiredSession);
  }, []);

  useEffect(() => {
    void refreshDashboardData();
  }, [refreshDashboardData]);

  const loginDashboard = useCallback(
    async (email: string, password: string) => {
      setApiStatus("loading");
      setApiError(null);

      try {
        const result = await loginDashboardRequest(email, password);
        setCurrentUser(result.user);
        setCurrentSchool(result.school);
        if (result.device_session) {
          setDeviceSessions([result.device_session]);
        }
        await refreshDashboardData();
        showToast("Dashboard login connected", "Authentication and dashboard modules are now synced.", "success");
      } catch (error) {
        const message = dashboardErrorMessage(error);
        setApiStatus("error");
        setApiError(message);
        throw error;
      }
    },
    [refreshDashboardData],
  );

  const logoutDashboard = useCallback(async () => {
    try {
      await logoutDashboardRequest();
    } finally {
      clearDashboardAuth();
      setCurrentUser(null);
      setCurrentSchool(null);
      setBackendPermissions([]);
      setDashboardSummary(null);
      setDeviceSessions([]);
      setNotifications([]);
      setFinanceSummary(null);
      setFinanceInvoices([]);
      setFinancePayments([]);
      setFinanceDiscounts([]);
      setFinanceRefunds([]);
      setBroadcastDeliveries({});
      setTransportSummary(null);
      setTransportPassengers({});
      setTransportEvents({});
      setSchoolSettings(null);
      setSchoolIntegrations([]);
      setAuditLogs([]);
      setRbacRoles([]);
      setRbacPermissions([]);
      setRbacMatrix(null);
      setBroadcasts([]);
      setDashboardBehaviorNotes([]);
      setDashboardLeavePermits([]);
      setDashboardSchedules([]);
      setScheduleConflictResult(null);
      setCalendarEvents([]);
      setDashboardAssessments([]);
      setReportExports({});
      setCanvasConfig(null);
      setStudents([]);
      setTeachers([]);
      setParents([]);
      setSections([]);
      setSubjects([]);
      setBehaviorNotes([]);
      setBusRoutes([]);
      setMessages([]);
      setMedicalExcuses([]);
      setLeavePermits([]);
      setParentSummons([]);
      setSubstitutes([]);
      setAttendance({ date: new Date().toISOString().slice(0, 10), total: 0, present: 0, absent: 0, late: 0, excused: 0, sectionBreakdown: [] });
      setApiStatus("mock");
      setApiError(null);
      showToast("تم تسجيل الخروج", "تم إنهاء جلسة لوحة التحكم بنجاح.", "info");
    }
  }, []);

  const markNotificationRead = useCallback(async (id: string) => {
    await markNotificationReadRequest(id);
    setNotifications((prev) =>
      prev.map((item) => (item.id === id ? { ...item, read_at: item.read_at ?? new Date().toISOString() } : item)),
    );
  }, []);

  const updatePushToken = useCallback(async (token: string) => {
    await updatePushTokenRequest(token);
  }, []);

  const refreshTransportRouteDetails = useCallback(async (routeId: string) => {
    if (!hasDashboardToken()) return;

    const [passengersResult, eventsResult] = await Promise.allSettled([
      fetchTransportPassengers(routeId),
      fetchTransportEvents(routeId),
    ]);

    if (passengersResult.status === "fulfilled") {
      setTransportPassengers((prev) => ({ ...prev, [routeId]: passengersResult.value }));
    }

    if (eventsResult.status === "fulfilled") {
      setTransportEvents((prev) => ({ ...prev, [routeId]: eventsResult.value }));
    }
  }, []);

  const createDashboardTransportRoute = useCallback(async (input: TransportRouteInput) => {
    if (!hasDashboardToken()) {
      showToast("Transport API", "Mock mode is active. Login first to create a live route.", "warning");
      return;
    }

    const capacity = Number(input.capacity ?? 40);
    if (!input.name.trim() || !Number.isFinite(capacity) || capacity <= 0) {
      showToast("Transport API", "Live route creation needs a route name and a valid capacity.", "warning");
      return;
    }

    const route = await createTransportRouteRequest({
      name: input.name.trim(),
      code: input.code?.trim() || generatedNumber("BUS"),
      capacity,
      driver_name: input.driver_name?.trim() || null,
      plate_number: input.plate_number?.trim() || null,
      driver_phone: input.driver_phone?.trim() || null,
      supervisor_name: input.supervisor_name?.trim() || null,
      estimated_arrival_time: input.estimated_arrival_time || null,
    });

    const [mappedRoute] = mapApiTransportRoutes([route]);
    if (mappedRoute) {
      setBusRoutes((prev) => [mappedRoute, ...prev.filter((item) => item.id !== mappedRoute.id)]);
    }
    await refreshDashboardData();
    showToast("Transport route created", "The route was created through /dashboard/transport/routes.", "success");
  }, [refreshDashboardData]);

  const updateDashboardTransportRoute = useCallback(async (routeId: string, input: Partial<TransportRouteInput>) => {
    if (!hasDashboardToken() || !isBackendId(routeId)) {
      showToast("Transport API", "Live route updates need a backend route id.", "warning");
      return;
    }

    const body: {
      name?: string;
      code?: string;
      capacity?: number;
      driver_name?: string | null;
      plate_number?: string | null;
      driver_phone?: string | null;
      supervisor_name?: string | null;
      estimated_arrival_time?: string | null;
    } = {};

    if (input.name !== undefined) body.name = input.name.trim();
    if (input.code !== undefined) body.code = input.code.trim();
    if (input.capacity !== undefined) {
      const capacity = Number(input.capacity);
      if (!Number.isFinite(capacity) || capacity <= 0) {
        showToast("Transport API", "Live route updates need a valid capacity.", "warning");
        return;
      }
      body.capacity = capacity;
    }
    if (input.driver_name !== undefined) body.driver_name = input.driver_name?.trim() || null;
    if (input.plate_number !== undefined) body.plate_number = input.plate_number?.trim() || null;
    if (input.driver_phone !== undefined) body.driver_phone = input.driver_phone?.trim() || null;
    if (input.supervisor_name !== undefined) body.supervisor_name = input.supervisor_name?.trim() || null;
    if (input.estimated_arrival_time !== undefined) body.estimated_arrival_time = input.estimated_arrival_time || null;

    const route = await updateTransportRouteRequest(routeId, body);

    const [mappedRoute] = mapApiTransportRoutes([route]);
    if (mappedRoute) {
      setBusRoutes((prev) => prev.map((item) => (item.id === mappedRoute.id ? mappedRoute : item)));
    }
    await refreshDashboardData();
    showToast("Transport route updated", "The route was updated through /dashboard/transport/routes/{route}.", "success");
  }, [refreshDashboardData]);

  const archiveDashboardTransportRoute = useCallback(async (routeId: string) => {
    if (!hasDashboardToken() || !isBackendId(routeId)) {
      showToast("Transport API", "Live route archiving needs a backend route id.", "warning");
      return;
    }

    const route = await deleteTransportRouteRequest(routeId);
    const [mappedRoute] = mapApiTransportRoutes([route]);
    if (mappedRoute) {
      setBusRoutes((prev) => prev.filter((item) => item.id !== mappedRoute.id));
    }
    await refreshDashboardData();
    showToast("Transport route archived", "The route was archived through /dashboard/transport/routes/{route}.", "success");
  }, [refreshDashboardData]);

  const assignStudentToTransportRoute = useCallback(async (routeId: string, studentId: string) => {
    if (!hasDashboardToken() || !isBackendId(routeId) || !isBackendId(studentId)) {
      showToast("Transport API", "Live route assignment needs backend route and student ids.", "warning");
      return null;
    }

    const assignment = await assignTransportStudentRequest(routeId, {
      student_id: Number(studentId),
      valid_from: new Date().toISOString().split("T")[0],
    });
    await refreshTransportRouteDetails(routeId);
    await refreshDashboardData();
    showToast("Student assigned to route", "The assignment was created through /dashboard/transport/routes/{route}/assignments.", "success");
    return assignment;
  }, [refreshDashboardData, refreshTransportRouteDetails]);

  const updateStudentTransportAssignment = useCallback(async (
    routeId: string,
    assignmentId: string,
    input: { valid_from?: string; valid_until?: string | null; status?: "active" | "archived" },
  ) => {
    if (!hasDashboardToken() || !isBackendId(routeId) || !isBackendId(assignmentId)) {
      showToast("Transport API", "Live assignment updates need backend route and assignment ids.", "warning");
      return;
    }

    await updateTransportAssignmentRequest(routeId, assignmentId, input);
    await refreshTransportRouteDetails(routeId);
    await refreshDashboardData();
    showToast("Transport assignment updated", "The assignment was updated through /dashboard/transport/routes/{route}/assignments/{assignment}.", "success");
  }, [refreshDashboardData, refreshTransportRouteDetails]);

  const archiveStudentTransportAssignment = useCallback(async (routeId: string, assignmentId: string) => {
    if (!hasDashboardToken() || !isBackendId(routeId) || !isBackendId(assignmentId)) {
      showToast("Transport API", "Live assignment archiving needs backend route and assignment ids.", "warning");
      return;
    }

    await deleteTransportAssignmentRequest(routeId, assignmentId);
    await refreshTransportRouteDetails(routeId);
    await refreshDashboardData();
    showToast("Transport assignment archived", "The assignment was archived through /dashboard/transport/routes/{route}/assignments/{assignment}.", "success");
  }, [refreshDashboardData, refreshTransportRouteDetails]);

  const sendTransportDelayAlert = useCallback(async (routeId: string, delayMinutes: number, message: string) => {
    if (!hasDashboardToken()) {
      showToast("Transport alert", "Mock mode is active. Login first to send a live delay alert.", "warning");
      return;
    }

    await sendTransportDelayAlertRequest(routeId, {
      message,
      delay_minutes: delayMinutes,
      channels: ["database", "push"],
    });
    await refreshDashboardData();
    showToast("Transport alert sent", "Delay alert was sent through the dashboard transport API.", "success");
  }, [refreshDashboardData]);

  const logTransportDriverContact = useCallback(async (
    routeId: string,
    outcome: "called" | "no_answer" | "message_sent" | "wrong_number" = "called",
    notes = "Logged from dashboard",
  ) => {
    if (!hasDashboardToken()) {
      showToast("Driver contact", "Mock mode is active. Login first to write a live contact log.", "warning");
      return;
    }

    await logTransportDriverContactRequest(routeId, { outcome, notes });
    await refreshTransportRouteDetails(routeId);
    showToast("Driver contact logged", "Driver contact outcome was saved to the backend.", "success");
  }, [refreshTransportRouteDetails]);

  const saveSchoolSettings = useCallback(async (settings: Partial<SchoolSettings>) => {
    if (!hasDashboardToken()) return;
    const nextSettings = await updateSchoolSettingsRequest(settings);
    setSchoolSettings(nextSettings);
    showToast("School settings saved", "Settings were updated through the dashboard API.", "success");
  }, []);

  const testSchoolIntegration = useCallback(async (integration: string) => {
    if (!hasDashboardToken()) return;
    const result = await testSchoolIntegrationRequest(integration);
    setSchoolIntegrations((prev) =>
      prev.map((item) =>
        item.key === integration
          ? { ...item, last_test_status: result.status, last_tested_at: new Date().toISOString() }
          : item,
      ),
    );
    showToast("Integration test finished", result.message, result.status === "ok" ? "success" : "warning");
  }, []);

  const createFinanceInvoiceForStudent = useCallback(async (studentId: string, title: string, amount: number) => {
    if (!hasDashboardToken()) {
      showToast("Finance API", "Mock mode is active. Login first to create a live invoice.", "warning");
      return;
    }
    if (!isBackendId(studentId) || !Number.isFinite(amount) || amount <= 0) {
      showToast("Finance API", "Live invoice creation needs a backend student id and a positive amount.", "warning");
      return;
    }

    const issueDate = new Date();
    const dueDate = new Date(issueDate);
    dueDate.setDate(dueDate.getDate() + 30);
    const invoice = await createFinanceInvoiceRequest({
      student_id: Number(studentId),
      issue_date: issueDate.toISOString().split("T")[0],
      due_date: dueDate.toISOString().split("T")[0],
      currency: financeSummary?.currency ?? currentSchool?.currency ?? "SAR",
      discount: 0,
      tax: 0,
      notes: "Created from dashboard finance page.",
      lines: [{ title: title || "Dashboard invoice", amount }],
    });

    setFinanceInvoices((prev) => [invoice, ...prev.filter((item) => item.id !== invoice.id)]);
    await refreshDashboardData();
    showToast("Finance invoice created", "The invoice was created through /dashboard/finance/invoices.", "success");
  }, [currentSchool?.currency, financeSummary?.currency, refreshDashboardData]);

  const recordFinancePayment = useCallback(async (invoiceId: string, amount: number) => {
    if (!hasDashboardToken()) {
      showToast("Finance API", "Mock mode is active. Login first to record a live payment.", "warning");
      return;
    }
    if (!isBackendId(invoiceId) || !Number.isFinite(amount) || amount <= 0) {
      showToast("Finance API", "Live payment recording needs a backend invoice id and a positive amount.", "warning");
      return;
    }

    const payment = await createFinancePaymentRequest({
      invoice_id: Number(invoiceId),
      amount,
      method: "cash",
      paid_at: new Date().toISOString(),
      reference: `dashboard-${Date.now()}`,
      notes: "Recorded from dashboard finance page.",
    });

    setFinancePayments((prev) => [payment, ...prev.filter((item) => item.id !== payment.id)]);
    await refreshDashboardData();
    showToast("Finance payment recorded", "The payment was saved through /dashboard/finance/payments.", "success");
  }, [refreshDashboardData]);

  const createFinanceDiscountForStudent = useCallback(async (studentId: string, title: string, amount: number) => {
    if (!hasDashboardToken()) {
      showToast("Finance API", "Mock mode is active. Login first to create a live discount.", "warning");
      return;
    }
    if (!isBackendId(studentId) || !Number.isFinite(amount) || amount <= 0) {
      showToast("Finance API", "Live discount creation needs a backend student id and a positive amount.", "warning");
      return;
    }

    const discount = await createFinanceDiscountRequest({
      student_id: Number(studentId),
      title: title || "Dashboard discount",
      amount,
      type: "fixed",
      status: "active",
      valid_from: new Date().toISOString().split("T")[0],
      notes: "Created from dashboard finance page.",
    });

    setFinanceDiscounts((prev) => [discount, ...prev.filter((item) => item.id !== discount.id)]);
    await refreshDashboardData();
    showToast("Finance discount created", "The discount was created through /dashboard/finance/discounts.", "success");
  }, [refreshDashboardData]);

  const cancelFinanceInvoice = useCallback(async (invoiceId: string) => {
    if (!hasDashboardToken() || !isBackendId(invoiceId)) {
      showToast("Finance API", "Live invoice cancellation needs a backend invoice id.", "warning");
      return;
    }
    const invoice = await deleteFinanceInvoiceRequest(invoiceId);
    setFinanceInvoices((prev) => prev.map((item) => (item.id === invoice.id ? invoice : item)));
    await refreshDashboardData();
    showToast("Finance invoice cancelled", "The invoice was cancelled through /dashboard/finance/invoices/{id}.", "success");
  }, [refreshDashboardData]);

  const archiveFinanceDiscount = useCallback(async (discountId: string) => {
    if (!hasDashboardToken() || !isBackendId(discountId)) {
      showToast("Finance API", "Live discount archiving needs a backend discount id.", "warning");
      return;
    }
    const discount = await updateFinanceDiscountRequest(discountId, { status: "archived" });
    setFinanceDiscounts((prev) => prev.map((item) => (item.id === discount.id ? discount : item)));
    showToast("Finance discount archived", "The discount status was updated through /dashboard/finance/discounts/{id}.", "success");
  }, []);

  const createFinanceRefundForPayment = useCallback(async (paymentId: string, amount: number, reason: string) => {
    if (!hasDashboardToken() || !isBackendId(paymentId)) {
      showToast("Finance API", "Live refunds need a backend payment id.", "warning");
      return;
    }
    if (!Number.isFinite(amount) || amount <= 0 || !reason.trim()) {
      showToast("Finance API", "Live refund creation needs a positive amount and a reason.", "warning");
      return;
    }

    const refund = await createFinanceRefundRequest(paymentId, {
      amount,
      reason: reason.trim(),
      reference: generatedNumber("refund"),
    });

    setFinanceRefunds((prev) => [refund, ...prev.filter((item) => item.id !== refund.id)]);
    await refreshDashboardData();
    showToast("Finance refund created", "The refund was created through /dashboard/finance/payments/{payment}/refunds.", "success");
  }, [refreshDashboardData]);

  const cancelDashboardBroadcast = useCallback(async (broadcastId: string) => {
    if (!hasDashboardToken() || !isBackendId(broadcastId)) {
      showToast("Broadcast API", "Live cancellation needs a backend broadcast id.", "warning");
      return;
    }
    const broadcast = await cancelBroadcastRequest(broadcastId);
    setBroadcasts((prev) => prev.map((item) => (item.id === broadcast.id ? broadcast : item)));
    const nextMessages = mapApiBroadcasts([broadcast, ...broadcasts.filter((item) => item.id !== broadcast.id)]);
    if (nextMessages.length) setMessages(nextMessages);
    showToast("Broadcast cancelled", "The broadcast was cancelled through /dashboard/broadcasts/{id}/cancel.", "success");
  }, [broadcasts]);

  const loadBroadcastDeliveries = useCallback(async (broadcastId: string) => {
    if (!hasDashboardToken() || !isBackendId(broadcastId)) {
      showToast("Broadcast API", "Live deliveries need a backend broadcast id.", "warning");
      return;
    }
    const deliveries = await fetchBroadcastDeliveries(broadcastId);
    setBroadcastDeliveries((prev) => ({ ...prev, [broadcastId]: deliveries }));
    showToast(
      "Broadcast deliveries",
      `Sent: ${deliveries.sent}, failed: ${deliveries.failed}, read: ${deliveries.read}.`,
      deliveries.failed > 0 ? "warning" : "success",
    );
  }, []);

  const updateSchoolCalendarEvent = useCallback(async (id: string, input: {
    title?: string;
    date?: string;
    time?: string;
    location?: string;
    category?: string;
    target?: string;
  }) => {
    if (!hasDashboardToken() || !isBackendId(id)) {
      showToast("Calendar API", "Live calendar update needs a backend calendar event id.", "warning");
      return;
    }
    const current = calendarEvents.find((event) => event.id === id);
    const event = await updateCalendarEvent(id, {
      ...(input.title ? { title: input.title } : {}),
      ...(input.location !== undefined ? { location: input.location || null } : {}),
      ...(input.category !== undefined ? { type: calendarType(input.category) } : {}),
      ...(input.target !== undefined ? { audience_type: calendarAudience(input.target) } : {}),
      ...(input.date || input.time
        ? { starts_at: futureScheduledAt(input.date || displayDate(current?.starts_at), input.time || displayTime(current?.starts_at) || "09:00") }
        : {}),
    });
    setCalendarEvents((prev) => prev.map((item) => (item.id === event.id ? event : item)));
    showToast("Calendar event updated", "The event was updated through /dashboard/calendar/events/{id}.", "success");
  }, [calendarEvents]);

  const deleteSchoolCalendarEvent = useCallback(async (id: string) => {
    if (!hasDashboardToken() || !isBackendId(id)) {
      showToast("Calendar API", "Live calendar delete needs a backend calendar event id.", "warning");
      return;
    }
    await deleteCalendarEvent(id);
    setCalendarEvents((prev) => prev.filter((item) => item.id !== id));
    showToast("Calendar event cancelled", "The event was cancelled through /dashboard/calendar/events/{id}.", "success");
  }, []);

  const updateAdminStatus = useCallback(async (accountId: string, status: "active" | "suspended") => {
    if (!hasDashboardToken() || !isBackendId(accountId)) {
      showToast("RBAC API", "Live account status update needs a backend admin account id.", "warning");
      return;
    }
    const account = await updateDashboardAdminStatus(accountId, status);
    setAdminAccounts((prev) =>
      adminAccountsFromBackend([account]).concat(prev.filter((item) => item.id !== account.id)),
    );
    showToast("Admin account updated", "The account status was updated through /dashboard/admin-accounts/{id}/status.", "success");
  }, []);

  const createDashboardRole = useCallback(async (key: string, name: string) => {
    if (!hasDashboardToken()) {
      showToast("RBAC API", "Mock mode is active. Login first to create a live dashboard role.", "warning");
      return;
    }
    const normalizedKey = key.trim().toLowerCase().replace(/[^a-z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
    if (!normalizedKey || !name.trim()) {
      showToast("RBAC API", "Role key and name are required.", "warning");
      return;
    }
    const role = await createRbacRoleRequest({ key: normalizedKey, name: name.trim(), permissions: [] });
    setRbacRoles((prev) => [role, ...prev.filter((item) => item.key !== role.key)]);
    setPermissionMatrix((prev) => ({ ...prev, [role.key]: prev[role.key] ?? {} }));
    showToast("Dashboard role created", "The role was created through /dashboard/rbac/roles.", "success");
  }, []);

  // RBAC Helpers
  const switchRole = (_role: Role) => {
    showToast("الدور من الخادم", "لا يمكن تبديل دور الجلسة من الواجهة. الدور والصلاحيات يتم تحميلهما من /auth/me.", "warning");
  };

  const updatePermission = (roleKey: string, permKey: string, value: boolean) => {
    if (!hasDashboardToken() || !rbacMatrix) {
      showToast("مصفوفة الصلاحيات", "يلزم اتصال مباشر بالخادم لتعديل الصلاحيات.", "warning");
      return;
    }

    const targetRole = rbacMatrix.roles.find((role) => role.key === roleKey);
    const aliases = permissionAliases[permKey as keyof PermissionMatrix[string]] ?? [];
    if (!targetRole || aliases.length === 0) {
      showToast("مصفوفة الصلاحيات", "تعذر تحديد الدور أو مجموعة الصلاحيات المطلوبة.", "error");
      return;
    }

    const nextPermissions = new Set(
      Object.entries(targetRole.permissions)
        .filter(([, enabled]) => enabled)
        .map(([permission]) => permission),
    );

    aliases
      .filter((permission) => rbacMatrix.permissions.includes(permission))
      .forEach((permission) => {
        if (value) nextPermissions.add(permission);
        else nextPermissions.delete(permission);
      });

    if (roleKey === "school_admin") {
      nextPermissions.add("rbac.view");
      nextPermissions.add("rbac.manage");
    }

    const validPermissions = Array.from(nextPermissions)
      .filter((permission) => rbacMatrix.permissions.includes(permission))
      .sort();

    void updateRbacMatrix({
      roles: [
        {
          key: roleKey,
          permissions: validPermissions,
        },
      ],
    })
      .then((matrix) => {
        setRbacMatrix(matrix);
        setPermissionMatrix(matrixFromBackend(matrix));
        showToast("تم تحديث الصلاحيات بنجاح ✓", `تم حفظ صلاحيات دور (${targetRole.label || roleKey}) في مصفوفة النظام.`, "success");
      })
      .catch((error) => {
        showToast("تعذر تحديث الصلاحيات", dashboardErrorMessage(error), "error");
      });
  };

  const hasApiPermission = useCallback((permission: string): boolean => {
    return backendPermissions.includes(permission);
  }, [backendPermissions]);

  const hasPermission = (permKey: keyof PermissionMatrix[string]): boolean => {
    const aliases = permissionAliases[permKey] ?? [];
    if (backendPermissions.length || hasDashboardToken()) {
      return aliases.some((permission) => hasApiPermission(permission));
    }
    return Boolean(permissionMatrix[currentRole.key]?.[permKey]);
  };

  const addAcademicSection = (name: string, code: string, gradeLevelId?: string, capacity = 30) => {
    if (!hasPermission("can_manage_academic")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية تعديل الهيكل الأكاديمي.", "error");
      return;
    }

    const gradeSection = sections.find((section) => section.gradeLevelId === gradeLevelId) ?? sections[0];

    if (hasDashboardToken()) {
      if (!gradeLevelId || !isBackendId(gradeLevelId)) {
        showToast("تعذر إنشاء الشعبة", "اختر مرحلة دراسية حقيقية من الهيكل الأكاديمي أولاً.", "warning");
        return;
      }

      void createSection({ grade_level_id: gradeLevelId, name, code, capacity })
        .then((section) => {
          const [mappedSection] = mapApiSections([section], [{ id: gradeLevelId, name: gradeSection?.gradeLevel ?? "Grade" }]);
          if (mappedSection) {
            setSections((prev) => [mappedSection, ...prev.filter((item) => item.id !== mappedSection.id)]);
          }
          showToast("تم إنشاء الشعبة", `تم حفظ الشعبة "${name}" في الخادم بنجاح.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر إنشاء الشعبة", message, "error");
        });
      return;
    }

    const localSection: SchoolSection = {
      id: `sec_${Date.now()}`,
      gradeLevelId,
      name,
      gradeLevel: gradeSection?.gradeLevel ?? "Grade",
      roomNumber: code,
      capacity,
      enrolledCount: 0,
      classTeacherId: "",
      classTeacherName: "Unassigned teacher",
    };
    setSections((prev) => [localSection, ...prev]);
    showToast("وضع تجريبي", "تم إنشاء الشعبة محلياً فقط لأن جلسة API غير موجودة.", "info");
  };

  const addAcademicSubject = (name: string, code: string, gradeLevelIds: string[] = []) => {
    if (!hasPermission("can_manage_academic")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية تعديل المواد الأكاديمية.", "error");
      return;
    }

    if (hasDashboardToken()) {
      void createSubject({ name, code, grade_level_ids: gradeLevelIds.filter(isBackendId) })
        .then((subject) => {
          const [mappedSubject] = mapApiSubjects([subject]);
          if (mappedSubject) {
            setSubjects((prev) => [mappedSubject, ...prev.filter((item) => item.id !== mappedSubject.id)]);
          }
          showToast("تمت إضافة المادة", `تم حفظ مادة "${name}" في الخادم بنجاح.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر إضافة المادة", message, "error");
        });
      return;
    }

    const localSubject: Subject = {
      id: `sub_${Date.now()}`,
      name,
      code,
      weeklyPeriods: 0,
      icon: "📘",
      color: "#176B9A",
    };
    setSubjects((prev) => [localSubject, ...prev]);
    showToast("وضع تجريبي", "تمت إضافة المادة محلياً فقط لأن جلسة API غير موجودة.", "info");
  };

  const runScheduleConflictCheck = useCallback(async () => {
    if (!hasDashboardToken()) {
      showToast("Schedule API", "Mock mode is active. Login first to run live conflict checks.", "warning");
      return;
    }

    const slot = dashboardSchedules.find((item) =>
      item.academic_term_id &&
      item.allocation_id &&
      item.weekday &&
      item.starts_at &&
      item.ends_at,
    );

    if (!slot?.academic_term_id || !slot.allocation_id || !slot.weekday || !slot.starts_at || !slot.ends_at) {
      showToast("Schedule API", "No live schedule slot is available for conflict checking yet.", "warning");
      return;
    }

    const result = await checkScheduleConflicts({
      academic_term_id: slot.academic_term_id,
      allocation_id: slot.allocation_id,
      weekday: slot.weekday,
      starts_at: slot.starts_at,
      ends_at: slot.ends_at,
      ignore_slot_id: slot.schedule_slot_id,
    });

    setScheduleConflictResult(result);
    showToast(
      "Schedule conflict check",
      result.has_conflict ? `${result.conflicts.length} live conflicts were found.` : "No live conflicts were found.",
      result.has_conflict ? "warning" : "success",
    );
  }, [dashboardSchedules]);

  const createSchoolCalendarEvent = useCallback(async (input: {
    title: string;
    date: string;
    time?: string;
    location?: string;
    category?: string;
    target?: string;
  }) => {
    if (!hasDashboardToken()) {
      showToast("Calendar API", "Mock mode is active. Login first to create a live calendar event.", "warning");
      return;
    }

    const startsAt = futureScheduledAt(input.date, input.time || "09:00");
    const event = await createCalendarEvent({
      title: input.title,
      description: input.category ?? null,
      type: calendarType(input.category),
      starts_at: startsAt,
      ends_at: null,
      all_day: !input.time,
      audience_type: calendarAudience(input.target),
      audience_ids: [],
      location: input.location || null,
    });

    setCalendarEvents((prev) => [event, ...prev.filter((item) => item.id !== event.id)]);
    showToast("Calendar event saved", "The event was created through the dashboard calendar API.", "success");
  }, []);

  const saveConfiguratorCanvas = useCallback(async (payload: Record<string, unknown>, expectedVersion?: number | null) => {
    if (!hasDashboardToken()) {
      showToast("Canvas API", "Mock mode is active. Login first to save the configurator canvas.", "warning");
      return;
    }

    const config = await saveCanvasConfig("main-configurator", {
      name: "Main dashboard configurator",
      payload,
      expected_version: expectedVersion ?? canvasConfig?.version ?? null,
    });

    setCanvasConfig(config);
    showToast("Canvas saved", "Configurator state was saved to the dashboard canvas API.", "success");
  }, [canvasConfig?.version]);

  const addAdminAccount = (newAcc: Omit<AdminAccount, "id" | "lastLogin">) => {
    if (!hasApiPermission("rbac.manage")) {
      showToast("RBAC", "لا تملك صلاحية إدارة الحسابات الإدارية.", "error");
      return;
    }

    if (hasDashboardToken()) {
      void createDashboardAdminAccount({
        name: newAcc.name,
        email: newAcc.email,
        role_key: newAcc.roleKey,
        status: newAcc.status === "inactive" ? "suspended" : "active",
      })
        .then((account) => {
          const [mapped] = adminAccountsFromBackend([account]);
          if (mapped) setAdminAccounts((prev) => [mapped, ...prev.filter((item) => item.id !== mapped.id)]);
          showToast("تم إنشاء الحساب الإداري", `تم حفظ حساب "${newAcc.name}" ودوره على الخادم.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر إنشاء الحساب", message, "error");
        });
      return;
    }

    const localAccount: AdminAccount = { ...newAcc, id: `adm_${Date.now()}`, lastLogin: "لم يسجل دخول بعد" };
    setAdminAccounts((prev) => [localAccount, ...prev]);
    showToast("وضع تجريبي", "تم إنشاء الحساب محلياً فقط لأن جلسة API غير موجودة.", "info");
  };

  const updateAdminRole = (accId: string, roleKey: Role["key"]) => {
    if (!hasApiPermission("rbac.manage")) {
      showToast("RBAC", "لا تملك صلاحية تعديل أدوار الحسابات.", "error");
      return;
    }
    const targetRole = systemRoles.find((r) => r.key === roleKey) || systemRoles[0];
    const acc = adminAccounts.find((a) => a.id === accId);

    if (hasDashboardToken() && isBackendId(accId)) {
      void updateDashboardAdminRole(accId, roleKey)
        .then((account) => {
          const [mapped] = adminAccountsFromBackend([account]);
          if (mapped) setAdminAccounts((prev) => prev.map((item) => (item.id === accId ? mapped : item)));
          showToast("تم تعديل الدور", `أصبح دور ${acc?.name || "الحساب"}: ${targetRole.label}.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر تعديل الدور", message, "error");
        });
      return;
    }

    setAdminAccounts((prev) => prev.map((account) => account.id === accId ? { ...account, roleKey, roleLabel: targetRole.label } : account));
    showToast("وضع تجريبي", "تم تعديل الدور محلياً فقط لأن الحساب ليس سجلاً حقيقياً من الخادم.", "info");
  };

  const addStudent = (newStu: StudentCreateInput) => {
    if (!hasApiPermission("people.manage")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إدارة سجلات الطلاب.", "error");
      return;
    }

    const section = sections.find((item) => item.id === newStu.sectionId);
    const gradeLevelId = section?.gradeLevelId ?? "";

    if (hasDashboardToken()) {
      if (!isBackendId(gradeLevelId) || !isBackendId(newStu.sectionId)) {
        showToast("تعذر تسجيل الطالب", "اختر مرحلة وشعبة حقيقيتين من الهيكل الأكاديمي قبل الحفظ.", "warning");
        return;
      }

      void (async () => {
        let linkedParentId = isBackendId(newStu.parentId) ? newStu.parentId : "";
        let mappedParent: Parent | undefined;

        if (!linkedParentId && newStu.parentPhone) {
          const createdParent = await createParent({
            full_name: newStu.parentName,
            email: newStu.parentEmail || null,
            phone: newStu.parentPhone,
            status: "active",
          });
          [mappedParent] = mapApiParents([createdParent]);
          if (mappedParent) {
            linkedParentId = mappedParent.id;
            setParents((prev) => [mappedParent!, ...prev.filter((parent) => parent.id !== mappedParent!.id)]);
          }
        }

        const createdStudent = await createStudent({
          admission_number: generatedNumber("STU"),
          full_name: newStu.name,
          grade_level_id: gradeLevelId,
          section_id: newStu.sectionId,
          status: "active",
          parent_ids: linkedParentId ? [linkedParentId] : [],
        });

        const parentSnapshot = mappedParent
          ? [mappedParent, ...parents.filter((parent) => parent.id !== mappedParent!.id)]
          : parents;
        const [mappedStudent] = mapApiStudents([createdStudent], sections, parentSnapshot);
        if (mappedStudent) {
          setStudents((prev) => [mappedStudent, ...prev.filter((student) => student.id !== mappedStudent.id)]);
          setSections((prev) => prev.map((item) => item.id === mappedStudent.sectionId ? { ...item, enrolledCount: item.enrolledCount + 1 } : item));
        }

        showToast("تم تسجيل الطالب", `تم حفظ الطالب ${newStu.name} في الخادم${linkedParentId ? " وربطه بولي الأمر" : ""}.`, "success");
      })().catch((error) => {
        const message = dashboardErrorMessage(error);
        setApiError(message);
        showToast("تعذر تسجيل الطالب", message, "error");
      });
      return;
    }

    const id = `st_${Date.now()}`;
    const fullStu: Student = { ...newStu, id, studentCode: `STU-${10030 + students.length + 1}` };
    setStudents((prev) => [fullStu, ...prev]);
    showToast("وضع تجريبي", "تم إنشاء الطالب محلياً فقط لأن جلسة API غير موجودة.", "info");
  };

  const sendParentWarning = (studentId: string, reason: string) => {
    if (!hasApiPermission("broadcasts.send")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إرسال التنبيهات.", "error");
      return;
    }
    const stu = students.find((s) => s.id === studentId);
    if (!stu) return;
    const parent = parents.find((item) => item.id === stu.parentId);

    if (hasDashboardToken()) {
      if (!parent?.centralUserId || !isBackendId(parent.centralUserId)) {
        showToast("تعذر إرسال التنبيه", "ولي الأمر المرتبط لا يملك central_user_id صالحاً للإشعارات المباشرة.", "warning");
        return;
      }

      void createBroadcast({
        title: "Attendance warning",
        body: `تنبيه حضور للطالب ${stu.name}: ${reason}`,
        type: "alert",
        target: { type: "custom_users", ids: [parent.centralUserId] },
        channels: ["database", "push"],
        priority: "high",
      })
        .then((broadcast) => sendBroadcastNow(broadcast.id))
        .then((broadcast) => {
          setBroadcasts((prev) => [broadcast, ...prev.filter((item) => item.id !== broadcast.id)]);
          showToast("تم إرسال التنبيه", `تم إرسال إشعار مباشر لولي أمر الطالب ${stu.name}.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر إرسال التنبيه", message, "error");
        });
      return;
    }

    showToast("وضع تجريبي", `لم يُرسل شيء للخادم. التنبيه الخاص بـ ${stu.name} محلي فقط.`, "info");
  };

  const addTeacher = (newT: Omit<Teacher, "id" | "kpiScore" | "lessonsThisWeek" | "notesCount">) => {
    if (!hasApiPermission("people.manage")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إدارة الكوادر التعليمية.", "error");
      return;
    }

    if (hasDashboardToken()) {
      void createTeacher({
        employee_number: generatedNumber("TCH"),
        full_name: newT.name,
        email: newT.email || null,
        phone: newT.phone || null,
        specialization: newT.specialization,
        status: "active",
        section_ids: newT.assignedSections.filter(isBackendId),
        subject_ids: newT.assignedSubjects.filter(isBackendId),
      })
        .then((teacher) => {
          const [mappedTeacher] = mapApiTeachers([teacher]);
          if (mappedTeacher) setTeachers((prev) => [mappedTeacher, ...prev.filter((item) => item.id !== mappedTeacher.id)]);
          showToast("تم تسجيل المعلم", `تم حفظ الأستاذ/ة ${newT.name} وربط التكليفات المحددة على الخادم.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر تسجيل المعلم", message, "error");
        });
      return;
    }

    const fullT: Teacher = { ...newT, id: `t_${Date.now()}`, kpiScore: 0, lessonsThisWeek: 0, notesCount: 0 };
    setTeachers((prev) => [fullT, ...prev]);
    showToast("وضع تجريبي", "تم إنشاء المعلم محلياً فقط لأن جلسة API غير موجودة.", "info");
  };

  const assignSubstitute = (absentTeacherId: string, subTeacherId: string, sectionName: string, period: number) => {
    if (!hasApiPermission("operations.substitution_manage")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إسناد حصص الاحتياط.", "error");
      return;
    }
    const absT = teachers.find((t) => t.id === absentTeacherId);
    const subT = teachers.find((t) => t.id === subTeacherId);
    if (!absT || !subT) return;

    const liveSlot = dashboardSchedules.find((slot) =>
      slot.teacher_id === absentTeacherId &&
      (!sectionName || slot.section_name === sectionName || sectionName.includes(slot.section_name ?? "")) &&
      (slot.teaching_session_ids?.length || slot.sessions?.length),
    );
    const teachingSessionId = liveSlot?.teaching_session_ids?.[0] ?? liveSlot?.sessions?.[0]?.id;

    if (hasDashboardToken()) {
      if (!teachingSessionId || !isBackendId(String(teachingSessionId)) || !isBackendId(subTeacherId)) {
        showToast("تعذر إسناد الاحتياط", "لا يوجد teaching_session_id حقيقي مطابق للحصة المحددة في بيانات الجدول الحالية.", "warning");
        return;
      }

      void createTeacherSubstitution({
        teaching_session_id: teachingSessionId,
        substitute_teacher_id: subTeacherId,
        reason: `Dashboard substitution for ${sectionName}, period ${period}.`,
      })
        .then((created) => {
          const assignment: SubstituteAssignment = {
            id: String(created.id ?? `sub_${Date.now()}`),
            absentTeacherId,
            absentTeacherName: absT.name,
            substituteTeacherId: subTeacherId,
            substituteTeacherName: subT.name,
            sectionName,
            subjectName: `${absT.specialization} (تغطية احتياط)`,
            period,
            date: new Date().toISOString().split("T")[0],
            status: "assigned",
          };
          setSubstitutes((prev) => [assignment, ...prev.filter((item) => item.id !== assignment.id)]);
          showToast("تم إسناد حصة الاحتياط", `تم تكليف ${subT.name} بالحصة ${period} للشعبة ${sectionName}.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error);
          setApiError(message);
          showToast("تعذر إسناد الاحتياط", message, "error");
        });
      return;
    }

    showToast("وضع تجريبي", "لم يتم إرسال إسناد احتياط إلى الخادم لأن جلسة API غير موجودة.", "info");
  };

  const approveBehaviorNote = (noteId: string) => {
    if (!hasApiPermission("behavior.publish")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية اعتماد الملاحظات السلوكية.", "error");
      return;
    }
    const note = behaviorNotes.find((n) => n.id === noteId);
    if (hasDashboardToken() && isBackendId(noteId)) {
      void publishBehaviorNote(noteId, "Published from dashboard review.")
        .then(() => {
          setBehaviorNotes((prev) => prev.map((item) => item.id === noteId ? { ...item, statusLabel: "قيد المعالجة" } : item));
          showToast("تم اعتماد الملاحظة", `تم نشر ملاحظة الطالب ${note?.studentName || ""} من خلال الخادم.`, "success");
        })
        .catch((error) => {
          const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر اعتماد الملاحظة", message, "error");
        });
      return;
    }
    showToast("تعذر اعتماد الملاحظة", "الملاحظة لا تحمل معرف Backend صالحاً.", "warning");
  };

  const attachRecommendation = (noteId: string, recTitle: string, recDesc: string) => {
    if (!hasApiPermission("behavior.review")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إرفاق التوصيات التربوية.", "error");
      return;
    }
    const note = behaviorNotes.find((n) => n.id === noteId);
    if (hasDashboardToken() && isBackendId(noteId)) {
      void addBehaviorRecommendation(noteId, `${recTitle}\n\n${recDesc}`)
        .then(() => {
          setBehaviorNotes((prev) => prev.map((item) => item.id === noteId ? { ...item, hasRecommendation: true, statusLabel: "قيد المعالجة" } : item));
          showToast("تم حفظ التوصية", `تم إرفاق التوصية بملاحظة ${note?.studentName || ""} على الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر حفظ التوصية", message, "error"); });
      return;
    }
    showToast("تعذر حفظ التوصية", "الملاحظة لا تحمل معرف Backend صالحاً.", "warning");
  };

  const resolveBehaviorNote = (noteId: string) => {
    if (!hasApiPermission("behavior.resolve")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية حل الملاحظات السلوكية.", "error");
      return;
    }
    const note = behaviorNotes.find((n) => n.id === noteId);
    if (hasDashboardToken() && isBackendId(noteId)) {
      void resolveBehaviorNoteRequest(noteId, "Resolved from dashboard follow-up.")
        .then(() => {
          setBehaviorNotes((prev) => prev.map((item) => item.id === noteId ? { ...item, statusLabel: "محلولة" } : item));
          showToast("تم حل الملاحظة", `تم إغلاق ملاحظة الطالب ${note?.studentName || ""} على الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر حل الملاحظة", message, "error"); });
      return;
    }
    showToast("تعذر حل الملاحظة", "الملاحظة لا تحمل معرف Backend صالحاً.", "warning");
  };

  const approveMedicalExcuse = (excuseId: string) => {
    if (!hasApiPermission("attendance.review_excuse")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية تدقيق التقارير الطبية.", "error");
      return;
    }
    const exc = medicalExcuses.find((e) => e.id === excuseId);
    if (hasDashboardToken() && isBackendId(excuseId)) {
      void approveMedicalExcuseRequest(excuseId, "Approved from dashboard review.")
        .then((updatedExcuse) => {
          const [mappedExcuse] = mapApiMedicalExcuses([updatedExcuse], students, parents, sections);
          if (mappedExcuse) setMedicalExcuses((prev) => prev.map((item) => item.id === excuseId ? mappedExcuse : item));
          showToast("تم اعتماد العذر الطبي", `تم اعتماد عذر الطالب ${exc?.studentName || ""} على الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر اعتماد العذر", message, "error"); });
      return;
    }
    showToast("تعذر اعتماد العذر", "العذر لا يحمل معرف Backend صالحاً.", "warning");
  };

  const rejectMedicalExcuse = (excuseId: string) => {
    if (!hasApiPermission("attendance.review_excuse")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية تدقيق التقارير الطبية.", "error");
      return;
    }
    const exc = medicalExcuses.find((e) => e.id === excuseId);
    if (hasDashboardToken() && isBackendId(excuseId)) {
      void rejectMedicalExcuseRequest(excuseId, "Rejected from dashboard review.")
        .then((updatedExcuse) => {
          const [mappedExcuse] = mapApiMedicalExcuses([updatedExcuse], students, parents, sections);
          if (mappedExcuse) setMedicalExcuses((prev) => prev.map((item) => item.id === excuseId ? mappedExcuse : item));
          showToast("تم رفض العذر", `تم رفض عذر الطالب ${exc?.studentName || ""} على الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر رفض العذر", message, "error"); });
      return;
    }
    showToast("تعذر رفض العذر", "العذر لا يحمل معرف Backend صالحاً.", "warning");
  };

  const issueParentSummons = (studentId: string, reason: string, meetingDate: string, meetingTime: string) => {
    if (!hasApiPermission("operations.summons_manage")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إصدار استدعاءات أولياء الأمور.", "error");
      return;
    }
    const stu = students.find((s) => s.id === studentId);
    if (!stu) return;
    const parent = parents.find((item) => item.id === stu.parentId);

    if (hasDashboardToken()) {
      if (!isBackendId(stu.id) || !isBackendId(stu.parentId)) {
        showToast("تعذر إصدار الاستدعاء", "الطالب أو ولي الأمر لا يحمل معرف Backend صالحاً.", "warning");
        return;
      }
      void createParentSummons({
        student_id: stu.id,
        parent_id: stu.parentId,
        scheduled_at: futureScheduledAt(meetingDate, meetingTime),
        reason,
      })
        .then((summons) => {
          const created: ParentSummons = {
            id: String(summons.id),
            studentId: stu.id,
            studentName: stu.name,
            sectionName: stu.sectionName,
            parentName: stu.parentName,
            parentPhone: parent?.phone ?? "",
            reason,
            meetingDate,
            meetingTime,
            supervisorName: currentUser?.name ?? "إدارة المدرسة",
            status: summons.status === "attended" || summons.status === "rescheduled" ? summons.status : "scheduled",
          };
          setParentSummons((prev) => [created, ...prev.filter((item) => item.id !== created.id)]);
          showToast("تم إصدار الاستدعاء", `تم حفظ موعد ${meetingDate} وإصدار الاستدعاء من الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر إصدار الاستدعاء", message, "error"); });
      return;
    }
    showToast("وضع تجريبي", "لم يتم إنشاء استدعاء على الخادم لأن جلسة API غير موجودة.", "info");
  };

  const approveLeavePermit = (permitId: string) => {
    if (!hasApiPermission("operations.leave_review")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية تصريح أذونات الخروج.", "error");
      return;
    }
    const permit = leavePermits.find((p) => p.id === permitId);
    if (hasDashboardToken() && isBackendId(permitId)) {
      void approveLeavePermitRequest(permitId, "Approved from dashboard gate review.")
        .then(() => {
          setLeavePermits((prev) => prev.map((item) => item.id === permitId ? { ...item, status: "released" } : item));
          showToast("تم اعتماد إذن الخروج", `تم اعتماد إذن ${permit?.studentName || ""} من الخادم.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر اعتماد إذن الخروج", message, "error"); });
      return;
    }
    showToast("تعذر اعتماد إذن الخروج", "الإذن لا يحمل معرف Backend صالحاً.", "warning");
  };

  const sendBroadcast = (title: string, body: string, target: string, type: "تعميم" | "تنبيه" | "تهنئة") => {
    if (!hasApiPermission("broadcasts.send")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية إرسال البث المباشر أو التعاميم العامة.", "error");
      return;
    }
    if (hasDashboardToken()) {
      void createBroadcast({
        title,
        body,
        type: dashboardBroadcastType(type),
        target: dashboardBroadcastTarget(target),
        channels: ["database", "push"],
        priority: type === "تنبيه" ? "high" : "normal",
      })
        .then((broadcast) => sendBroadcastNow(broadcast.id))
        .then((broadcast) => {
          setBroadcasts((prev) => [broadcast, ...prev.filter((item) => item.id !== broadcast.id)]);
          const [mappedMessage] = mapApiBroadcasts([broadcast]);
          if (mappedMessage) setMessages((prev) => [mappedMessage, ...prev.filter((item) => item.id !== mappedMessage.id)]);
          showToast("تم إرسال البث", `تم إرسال ${type} إلى (${target}) عبر قنوات الخادم المفعلة.`, "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر إرسال البث", message, "error"); });
      return;
    }
    const localMessage: BroadcastMessage = {
      id: `msg_${Date.now()}`, title, body, target, sentBy: "إدارة المدرسة",
      date: new Date().toISOString().split("T")[0], type, reachCount: 0,
    };
    setMessages((prev) => [localMessage, ...prev]);
    showToast("وضع تجريبي", "تمت إضافة الرسالة محلياً فقط ولم تُرسل إلى أي مستخدم.", "info");
  };

  const scheduleBroadcast = (title: string, body: string, target: string, type: "تعميم" | "تنبيه" | "تهنئة", scheduledAt: string) => {
    if (!hasApiPermission("broadcasts.schedule")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية جدولة التعاميم.", "error");
      return;
    }
    if (hasDashboardToken()) {
      void createBroadcast({
        title,
        body,
        type: dashboardBroadcastType(type),
        target: dashboardBroadcastTarget(target),
        channels: ["database", "push"],
        scheduled_at: scheduledAt,
        priority: type === "تنبيه" ? "high" : "normal",
      })
        .then((broadcast) => {
          setBroadcasts((prev) => [broadcast, ...prev.filter((item) => item.id !== broadcast.id)]);
          const [mappedMessage] = mapApiBroadcasts([broadcast]);
          if (mappedMessage) setMessages((prev) => [mappedMessage, ...prev.filter((item) => item.id !== mappedMessage.id)]);
          showToast("تمت جدولة الإرسال", "تم حفظ الرسالة المجدولة على الخادم.", "success");
        })
        .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر جدولة الرسالة", message, "error"); });
      return;
    }
    showToast("وضع تجريبي", "لم تُجدول الرسالة على الخادم لأن جلسة API غير موجودة.", "info");
  };

  const updateAssessmentGradesFromDashboard = useCallback(async (
    assessmentId: string,
    entries: AssessmentGradeEntryInput[],
  ) => {
    if (!hasApiPermission("grade.approve")) {
      showToast("Grades API", "Your current role cannot edit dashboard grades.", "error");
      return [];
    }
    if (!hasDashboardToken() || !isBackendId(assessmentId)) {
      showToast("Grades API", "Live grade editing needs a backend assessment id.", "warning");
      return [];
    }

    const normalizedEntries = entries
      .filter((entry) => isBackendId(String(entry.student_id)))
      .map((entry) => ({
        student_id: Number(entry.student_id),
        score: entry.score ?? null,
        feedback: entry.feedback ?? entry.note ?? null,
        note: entry.note ?? entry.feedback ?? null,
        revision: entry.revision ?? null,
      }));

    if (!normalizedEntries.length) {
      showToast("Grades API", "Live grade editing needs at least one backend student id.", "warning");
      return [];
    }

    const savedEntries = await updateDashboardAssessmentGradesRequest(assessmentId, { entries: normalizedEntries });
    const detail = await fetchDashboardAssessmentRequest(assessmentId).catch(() => null);
    if (detail) {
      setDashboardAssessments((prev) => prev.map((item) => (item.id === detail.id ? detail : item)));
    }
    showToast("Grades saved", "Grade entries were updated through /dashboard/assessments/{assessment}/grades.", "success");
    return savedEntries;
  }, [hasApiPermission]);

  const requestGradeSheetExport = useCallback(async (assessmentId: string) => {
    if (!hasDashboardToken() || !isBackendId(assessmentId)) {
      showToast("Grades API", "Live grade export needs a backend assessment id.", "warning");
      return null;
    }

    const reportExport = await requestAssessmentGradeExportRequest(assessmentId);
    setReportExports((prev) => ({ ...prev, [reportExport.export_id]: reportExport }));
    showToast("Grade export requested", `Export ${reportExport.export_id} is ${reportExport.status ?? "queued"}.`, "success");
    return reportExport;
  }, []);

  const refreshReportExport = useCallback(async (exportId: string) => {
    if (!hasDashboardToken() || !exportId.trim()) {
      showToast("Reports API", "Live export refresh needs an export id.", "warning");
      return null;
    }

    const reportExport = await fetchReportExportRequest(exportId);
    setReportExports((prev) => ({ ...prev, [reportExport.export_id]: reportExport }));
    showToast("Export refreshed", `Export ${reportExport.export_id} is ${reportExport.status ?? "unknown"}.`, "info");
    return reportExport;
  }, []);

  const approveSectionGrades = (sectionName: string) => {
    if (!hasApiPermission("grade.approve")) {
      showToast("تنبيه صلاحيات (RBAC)", "عفواً، حسابك الحالي لا يملك صلاحية اعتماد الدرجات ونشرها.", "error");
      return;
    }
    if (!hasDashboardToken()) {
      showToast("Grades API", "يجب تسجيل الدخول للقيام باعتماد حقيقي للدرجات.", "warning");
      return;
    }

    const assessment = dashboardAssessments.find((item) =>
      item.section?.name && sectionName.includes(item.section.name) &&
      item.available_actions?.some((action) => ["approve", "publish", "lock"].includes(action)),
    );
    if (!assessment) {
      showToast("تعذر اعتماد الدرجات", "لا يوجد تقييم Backend متاح للإجراء المطلوب في هذا الفصل.", "warning");
      return;
    }

    const action = assessment.available_actions?.find((item) => ["approve", "publish", "lock"].includes(item));
    const request = action === "publish" ? publishAssessment : action === "lock" ? lockAssessment : approveAssessment;
    void request(assessment.id)
      .then((updatedAssessment) => {
        setDashboardAssessments((prev) => prev.map((item) => item.id === updatedAssessment.id ? { ...item, ...updatedAssessment } : item));
        showToast("تم اعتماد الدرجات", `تم تنفيذ إجراء ${action ?? "approve"} للفصل ${sectionName} على الخادم.`, "success");
      })
      .catch((error) => { const message = dashboardErrorMessage(error); setApiError(message); showToast("تعذر اعتماد الدرجات", message, "error"); });
  };

  const fetchDailyAttendanceByDateAndSection = useCallback(async (date?: string, sectionId?: string, status?: string) => {
    try {
      const res = await fetchDashboardDailyAttendance({ date, section_id: sectionId, status });
      setDailyAttendance(res);
      return res;
    } catch {
      return null;
    }
  }, []);

  const fetchAtRiskStudents = useCallback(async (termId: number | string, sectionId?: string) => {
    try {
      const res = await fetchDashboardAttendanceAtRisk({ academic_term_id: termId, section_id: sectionId });
      setAttendanceAtRisk(res);
      return res;
    } catch {
      return null;
    }
  }, []);

  const fetchEarlyWarningsData = useCallback(async (sectionId?: string, minScore?: number) => {
    try {
      const res = await fetchDashboardEarlyWarnings({ section_id: sectionId, min_score: minScore });
      setEarlyWarnings(res);
      return res;
    } catch {
      return null;
    }
  }, []);

  const isAuthenticated = Boolean(currentUser && hasDashboardToken());

  return (
    <DashboardContext.Provider
      value={{
        currentRole, switchRole, permissionMatrix, updatePermission, hasPermission,
        adminAccounts, addAdminAccount, updateAdminRole,
        students, teachers, parents, sections, subjects, behaviorNotes, busRoutes, messages, attendance,
        medicalExcuses, leavePermits, parentSummons, substitutes, toasts,
        apiStatus, apiError, isAuthenticated, currentUser, currentSchool, backendPermissions, hasApiPermission, dashboardSummary,
        deviceSessions, notifications,
        financeSummary, financeInvoices, financePayments, financeDiscounts, financeRefunds,
        broadcastDeliveries,
        transportSummary, transportPassengers, transportEvents,
        schoolSettings, schoolIntegrations, auditLogs, rbacRoles, rbacPermissions, rbacMatrix, broadcasts,
        dashboardBehaviorNotes, dashboardLeavePermits, dashboardSchedules, scheduleConflictResult,
        calendarEvents, dashboardAssessments, reportExports, canvasConfig,
        dailyAttendance, attendanceAtRisk, earlyWarnings,
        fetchDailyAttendanceByDateAndSection, fetchAtRiskStudents, fetchEarlyWarningsData,
        showToast, removeToast, loginDashboard, logoutDashboard, refreshDashboardData,
        markNotificationRead, updatePushToken, refreshTransportRouteDetails,
        createDashboardTransportRoute, updateDashboardTransportRoute, archiveDashboardTransportRoute,
        assignStudentToTransportRoute, updateStudentTransportAssignment, archiveStudentTransportAssignment,
        sendTransportDelayAlert,
        logTransportDriverContact, saveSchoolSettings, testSchoolIntegration,
        createFinanceInvoiceForStudent, recordFinancePayment, createFinanceDiscountForStudent,
        cancelFinanceInvoice, archiveFinanceDiscount, createFinanceRefundForPayment,
        cancelDashboardBroadcast, loadBroadcastDeliveries,
        updateSchoolCalendarEvent, deleteSchoolCalendarEvent, updateAdminStatus,
        createDashboardRole,
        addAcademicSection, addAcademicSubject, runScheduleConflictCheck, createSchoolCalendarEvent, saveConfiguratorCanvas,
        addStudent, sendParentWarning, addTeacher, assignSubstitute,
        approveBehaviorNote, attachRecommendation, resolveBehaviorNote, approveMedicalExcuse,
        rejectMedicalExcuse, issueParentSummons, approveLeavePermit, sendBroadcast, scheduleBroadcast, approveSectionGrades,
        updateAssessmentGradesFromDashboard, requestGradeSheetExport, refreshReportExport,
        mobileMenuOpen, setMobileMenuOpen,
        sidebarCollapsed, setSidebarCollapsed, toggleSidebar
      }}
    >
      {children}
    </DashboardContext.Provider>
  );
}

export function useDashboard() {
  const context = useContext(DashboardContext);
  if (!context) throw new Error("useDashboard must be used within a DashboardProvider");
  return context;
}
