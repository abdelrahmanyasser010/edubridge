<?php

namespace App\Auth;

final class SystemRoleCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public static function permissionsByRole(): array
    {
        return [
            'school_admin' => PermissionCatalog::keys(),
            'academic_admin' => ['academic.view', 'academic.manage', 'academic.publish', 'people.view', 'schedule.view', 'schedule.manage', 'schedule.publish', 'operations.substitution_manage', 'grade.view', 'grade.approve', 'grade.publish', 'grade.lock', 'grade.appeal_review', 'report.view'],
            'student_affairs' => ['people.view', 'operations.view', 'attendance.view', 'attendance.amend', 'attendance.review_excuse', 'behavior.view', 'behavior.review', 'behavior.publish', 'behavior.resolve', 'operations.leave_review', 'operations.summons_manage'],
            'finance_officer' => ['wallet.view', 'wallet.limit_manage', 'finance.view', 'finance.manage', 'finance.payments.record', 'finance.reports.view', 'payment.view', 'payment.collect', 'payment.refund', 'payment.reconcile', 'report.view', 'report.export'],
            'transport_supervisor' => ['transport.view', 'transport.manage', 'transport.track', 'transport.alert', 'transport.alerts.send', 'people.view'],
            'teacher' => ['schedule.view', 'attendance.view', 'attendance.draft', 'attendance.submit', 'assignment.view', 'assignment.create', 'assignment.update', 'assignment.publish', 'assignment.archive', 'behavior.view', 'behavior.create', 'message.view', 'message.send', 'grade.view', 'grade.enter'],
            'parent' => ['attendance.view', 'assignment.view', 'behavior.view', 'behavior.acknowledge', 'message.view', 'message.send', 'grade.view', 'operations.leave_review', 'transport.view', 'wallet.view', 'payment.view', 'payment.collect'],
            'student' => ['schedule.view', 'assignment.view', 'message.view', 'grade.view'],
            'canteen_operator' => ['wallet.deduct'],
            'integration_client' => [],
        ];
    }
}
