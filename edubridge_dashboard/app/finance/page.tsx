"use client";

import React, { useMemo, useState } from "react";
import Sidebar from "@/components/Sidebar";
import Header from "@/components/Header";
import Footer from "@/components/Footer";
import { useDashboard } from "@/context/DashboardContext";
import { CreditCard, FileText, Percent, Plus, RefreshCw, RotateCcw, TrendingUp, Wallet } from "lucide-react";

function money(value: number | undefined, currency = "SAR") {
  const currLabel = currency === "SAR" ? "ر.س" : currency;
  return `${(value ?? 0).toLocaleString("ar-SA")} ${currLabel}`;
}

function StatusBadge({ status }: { status?: string | null }) {
  const cls =
    status === "paid"
      ? "badge-green"
      : status === "partial"
        ? "badge-orange"
        : status === "cancelled"
          ? "badge-gray"
          : "badge-red";

  const label =
    status === "paid"
      ? "مسددة بالكامل"
      : status === "partial"
        ? "سداد جزئي"
        : status === "cancelled"
          ? "ملغاة"
          : "مستحقة";

  return <span className={`badge ${cls}`}><span className="dot" />{label}</span>;
}

export default function FinancePage() {
  const {
    financeSummary,
    financeInvoices,
    financePayments,
    financeDiscounts,
    financeRefunds,
    students,
    refreshDashboardData,
    createFinanceInvoiceForStudent,
    recordFinancePayment,
    createFinanceDiscountForStudent,
    cancelFinanceInvoice,
    archiveFinanceDiscount,
    createFinanceRefundForPayment,
  } = useDashboard();
  const [selectedStudentId, setSelectedStudentId] = useState("");
  const [financeAmount, setFinanceAmount] = useState("500");
  const [financeTitle, setFinanceTitle] = useState("رسوم دراسية");

  const backendStudents = useMemo(() => students.filter((student) => /^\d+$/.test(student.id)), [students]);
  const effectiveStudentId = selectedStudentId || backendStudents[0]?.id || students[0]?.id || "";
  const payableInvoice = financeInvoices.find((invoice) => /^\d+$/.test(invoice.id) && (invoice.remaining ?? invoice.total ?? 0) > 0);
  const refundablePayment = financePayments.find((payment) => /^\d+$/.test(payment.id) && (payment.amount ?? 0) > 0);
  const amountValue = Number(financeAmount);

  const currency = financeSummary?.currency ?? financeInvoices[0]?.currency ?? "SAR";
  const totalDue = financeSummary?.total_due ?? financeInvoices.reduce((sum, item) => sum + (item.total ?? 0), 0);
  const totalPaid = financeSummary?.total_paid ?? financeInvoices.reduce((sum, item) => sum + (item.paid ?? 0), 0);
  const overdueAmount = financeSummary?.overdue_amount ?? financeInvoices.reduce((sum, item) => sum + (item.remaining ?? 0), 0);

  const handleRefundPayment = (paymentId: string, maxAmount: number | undefined) => {
    const amountText = window.prompt("أدخل مبلغ الاسترداد:", String(maxAmount ?? amountValue));
    if (!amountText) return;
    const refundAmount = Number(amountText);
    if (!Number.isFinite(refundAmount) || refundAmount <= 0) return;
    const reason = window.prompt("سبب الاسترداد:", "طلب استرداد من ولي الأمر");
    if (!reason) return;
    void createFinanceRefundForPayment(paymentId, refundAmount, reason);
  };

  const [showInvoiceModal, setShowInvoiceModal] = useState(false);
  const [showDiscountModal, setShowDiscountModal] = useState(false);

  return (
    <div className="dashboard-shell">
      <Sidebar />
      <div className="main-content">
        <Header
          title="المالية والفواتير"
          subtitle="إدارة المطالبات المالية، سداد الرسوم الدراسية، الخصومات والاستردادات المعتمدة"
        />
        <main className="page-body">
          <div className="kpi-grid" style={{ marginBottom: 24 }}>
            {[
              { label: "إجمالي المستحق", value: money(totalDue, currency), icon: <Wallet size={22} />, bg: "#EFF6FF", color: "#176B9A" },
              { label: "إجمالي المحصل", value: money(totalPaid, currency), icon: <CreditCard size={22} />, bg: "#F0FDF4", color: "#16A34A" },
              { label: "متأخرات ومتبقيات", value: money(overdueAmount, currency), icon: <TrendingUp size={22} />, bg: "#FFF7ED", color: "#F59E0B" },
              { label: "نسبة التحصيل", value: `${financeSummary?.collection_rate ?? 0}%`, icon: <Percent size={22} />, bg: "#F8F4FF", color: "#8B5CF6" },
            ].map((stat) => (
              <div key={stat.label} className="kpi-card">
                <div className="kpi-icon" style={{ background: stat.bg, color: stat.color }}>{stat.icon}</div>
                <div className="kpi-content">
                  <div className="kpi-value" style={{ fontSize: 19 }}>{stat.value}</div>
                  <div className="kpi-label">{stat.label}</div>
                </div>
              </div>
            ))}
          </div>

          {/* Action Bar */}
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20, flexWrap: "wrap", gap: 12 }}>
            <div>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>سجل العمليات المالية والمطالبات</div>
              <div style={{ fontSize: 12, color: "var(--text-muted)" }}>إصدار المطالبات، تسجيل دفعات السداد، ومتابعة الخصومات</div>
            </div>
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
              <button className="btn btn-primary" onClick={() => setShowInvoiceModal(true)}>
                <Plus size={15} /> إصدار فاتورة جديدة
              </button>
              {payableInvoice && (
                <button
                  className="btn btn-green"
                  onClick={() => void recordFinancePayment(payableInvoice.id, Math.min(amountValue, payableInvoice.remaining ?? payableInvoice.total ?? amountValue))}
                >
                  <CreditCard size={15} /> تسجيل دفعة سداد
                </button>
              )}
              <button className="btn btn-outline" onClick={() => setShowDiscountModal(true)}>
                <Percent size={15} /> تطبيق خصم لطالب
              </button>
            </div>
          </div>

          <div className="grid-2" style={{ marginBottom: 20 }}>
            {/* Invoices List */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">سجل الفواتير والمطالبات</div>
                  <div className="card-subtitle">قائمة الفواتير الصادرة للطلاب وحالة السداد الحالية</div>
                </div>
                <button className="btn btn-outline btn-sm" onClick={() => void refreshDashboardData()}>
                  <RefreshCw size={14} /> تحديث
                </button>
              </div>
              <div>
                {financeInvoices.slice(0, 8).map((invoice, index) => (
                  <div key={invoice.id} className="feed-item" style={{ borderBottom: index < Math.min(financeInvoices.length, 8) - 1 ? "1px solid var(--border-light)" : "none" }}>
                    <div style={{ width: 40, height: 40, borderRadius: "var(--radius-sm)", background: "var(--primary-50)", display: "flex", alignItems: "center", justifyContent: "center", color: "var(--primary)" }}>
                      <FileText size={18} />
                    </div>
                    <div style={{ flex: 1 }}>
                      <div style={{ display: "flex", justifyContent: "space-between", gap: 12, marginBottom: 4 }}>
                        <strong>{invoice.invoice_number ?? invoice.id}</strong>
                        <StatusBadge status={invoice.status} />
                      </div>
                      <div style={{ fontSize: 12, color: "var(--text-light)" }}>{invoice.student_name ?? "طالب غير محدد"} {invoice.parent_name ? `— ${invoice.parent_name}` : ""}</div>
                      <div style={{ fontSize: 12, color: "var(--text-muted)", marginTop: 5 }}>
                        الإجمالي: {money(invoice.total, invoice.currency ?? currency)} — المتبقي: {money(invoice.remaining, invoice.currency ?? currency)}
                      </div>
                      {invoice.status !== "cancelled" && (
                        <button className="btn btn-ghost btn-sm" style={{ marginTop: 8 }} onClick={() => void cancelFinanceInvoice(invoice.id)}>
                          إلغاء الفاتورة
                        </button>
                      )}
                    </div>
                  </div>
                ))}
                {financeInvoices.length === 0 && <div style={{ padding: 18, color: "var(--text-light)", fontSize: 13 }}>لا توجد فواتير مسجلة حالياً.</div>}
              </div>
            </div>

            {/* Payments & Discounts & Refunds */}
            <div className="card">
              <div className="card-header">
                <div>
                  <div className="card-title">المدفوعات والخصومات والاسترداد</div>
                  <div className="card-subtitle">تفاصيل عمليات التحصيل والخصومات الممنوحة والاسترداد المالي</div>
                </div>
              </div>
              <div style={{ display: "grid", gap: 14 }}>
                {/* Latest Payments */}
                <div>
                  <div style={{ fontWeight: 800, fontSize: 13, marginBottom: 8 }}>آخر المدفوعات المسددة</div>
                  {financePayments.slice(0, 5).map((payment) => (
                    <div key={payment.id} style={{ display: "flex", justifyContent: "space-between", padding: "8px 0", borderBottom: "1px solid var(--border-light)", fontSize: 12.5 }}>
                      <span>فاتورة: {payment.invoice_number ?? payment.invoice_id}</span>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <strong>{money(payment.amount, currency)}</strong>
                        <button className="btn btn-ghost btn-sm" onClick={() => handleRefundPayment(payment.id, payment.amount)}>
                          <RotateCcw size={12} /> استرداد
                        </button>
                      </div>
                    </div>
                  ))}
                  {financePayments.length === 0 && <div style={{ color: "var(--text-light)", fontSize: 12 }}>لا توجد مدفوعات مسجلة.</div>}
                </div>

                {/* Active Discounts */}
                <div>
                  <div style={{ fontWeight: 800, fontSize: 13, marginBottom: 8 }}>الخصومات النشطة</div>
                  {financeDiscounts.slice(0, 5).map((discount) => (
                    <div key={discount.id} style={{ display: "flex", justifyContent: "space-between", gap: 10, padding: "8px 0", borderBottom: "1px solid var(--border-light)", fontSize: 12.5 }}>
                      <span>{discount.title ?? discount.student_name ?? discount.id}</span>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <strong>{money(discount.amount, currency)}</strong>
                        {discount.status !== "archived" && (
                          <button className="btn btn-ghost btn-sm" onClick={() => void archiveFinanceDiscount(discount.id)}>
                            أرشفة
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                  {financeDiscounts.length === 0 && <div style={{ color: "var(--text-light)", fontSize: 12 }}>لا توجد خصومات مسجلة.</div>}
                </div>

                {/* Refunds */}
                <div>
                  <div style={{ fontWeight: 800, fontSize: 13, marginBottom: 8 }}>عمليات الاسترداد المعتمدة</div>
                  {financeRefunds.slice(0, 5).map((refund) => (
                    <div key={refund.id} style={{ display: "flex", justifyContent: "space-between", gap: 10, padding: "8px 0", borderBottom: "1px solid var(--border-light)", fontSize: 12.5 }}>
                      <span>{refund.reason ?? refund.reference ?? refund.id}</span>
                      <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                        <span className="badge badge-blue">
                          {refund.status === "completed" ? "مكتمل" : "معتمد"}
                        </span>
                        <strong>{money(refund.amount, refund.currency ?? currency)}</strong>
                      </div>
                    </div>
                  ))}
                  {financeRefunds.length === 0 && <div style={{ color: "var(--text-light)", fontSize: 12 }}>لا توجد عمليات استرداد مسجلة.</div>}
                </div>
              </div>
            </div>
          </div>
        </main>
        <Footer />
      </div>

      {/* Create Invoice Modal */}
      {showInvoiceModal && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>إصدار فاتورة / مطالبة دراسية جديدة</div>
              <button onClick={() => setShowInvoiceModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18, color: "var(--text-muted)" }}>✕</button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                void createFinanceInvoiceForStudent(effectiveStudentId, financeTitle, amountValue);
                setShowInvoiceModal(false);
              }}
              style={{ display: "flex", flexDirection: "column", gap: 14 }}
            >
              <div className="form-group">
                <label className="form-label">الطالب المستهدف</label>
                <select className="form-select" value={effectiveStudentId} onChange={(e) => setSelectedStudentId(e.target.value)}>
                  {(backendStudents.length ? backendStudents : students).slice(0, 50).map((s) => (
                    <option key={s.id} value={s.id}>{s.name} ({s.studentCode || s.sectionName})</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">بند الفاتورة أو الرسوم</label>
                <input
                  required
                  className="form-input"
                  placeholder="مثال: رسوم دراسية للفصل الثاني / رسوم النقل المدرسي"
                  value={financeTitle}
                  onChange={(e) => setFinanceTitle(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label">المبلغ المطلوب (ر.س)</label>
                <input
                  required
                  type="number"
                  min="1"
                  className="form-input"
                  placeholder="500"
                  value={financeAmount}
                  onChange={(e) => setFinanceAmount(e.target.value)}
                />
              </div>

              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <Plus size={15} /> إصدار واعتماد الفاتورة
                </button>
                <button type="button" onClick={() => setShowInvoiceModal(false)} className="btn btn-ghost">
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Apply Discount Modal */}
      {showDiscountModal && (
        <div className="modal-overlay">
          <div className="modal-content">
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 20 }}>
              <div style={{ fontSize: 16, fontWeight: 800, color: "var(--text-dark)" }}>تطبيق خصم أو منحة لطالب</div>
              <button onClick={() => setShowDiscountModal(false)} style={{ background: "none", border: "none", cursor: "pointer", fontSize: 18, color: "var(--text-muted)" }}>✕</button>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                void createFinanceDiscountForStudent(effectiveStudentId, financeTitle, amountValue);
                setShowDiscountModal(false);
              }}
              style={{ display: "flex", flexDirection: "column", gap: 14 }}
            >
              <div className="form-group">
                <label className="form-label">الطالب المستفيد</label>
                <select className="form-select" value={effectiveStudentId} onChange={(e) => setSelectedStudentId(e.target.value)}>
                  {(backendStudents.length ? backendStudents : students).slice(0, 50).map((s) => (
                    <option key={s.id} value={s.id}>{s.name} ({s.studentCode || s.sectionName})</option>
                  ))}
                </select>
              </div>

              <div className="form-group">
                <label className="form-label">سبب / مسمى الخصم</label>
                <input
                  required
                  className="form-input"
                  placeholder="مثال: خصم تفوق أكاديمي / خصم الإخوة"
                  value={financeTitle}
                  onChange={(e) => setFinanceTitle(e.target.value)}
                />
              </div>

              <div className="form-group">
                <label className="form-label">مبلغ الخصم (ر.س)</label>
                <input
                  required
                  type="number"
                  min="1"
                  className="form-input"
                  placeholder="250"
                  value={financeAmount}
                  onChange={(e) => setFinanceAmount(e.target.value)}
                />
              </div>

              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <button type="submit" className="btn btn-primary" style={{ flex: 1, justifyContent: "center" }}>
                  <Percent size={15} /> اعتماد الخصم للطالب
                </button>
                <button type="button" onClick={() => setShowDiscountModal(false)} className="btn btn-ghost">
                  إلغاء
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
