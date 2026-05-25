<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Ships the platform with a complete set of production-ready transactional
 * templates. Every row is flagged `is_system_template = true` so the admin
 * UI hides the destructive "delete" button — operators can still edit the
 * copy, but the keys remain stable so application code can reference them.
 *
 * Each template body uses `{{ app_name }}` as the canonical product-name
 * placeholder; {@see VariableParser::systemDefaults()} resolves it to
 * `config('app.name')` at send-time, with `company_name` kept as an alias
 * for older templates that may still reference it.
 */
final class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $row) {
            EmailTemplate::updateOrCreate(
                ['template_key' => $row['template_key']],
                $row + [
                    'status' => EmailTemplateStatus::Active->value,
                    'is_system_template' => true,
                    'locale' => 'en',
                    'version' => 1,
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            $this->welcome(),
            $this->otpVerification(),
            $this->passwordReset(),
            $this->workspaceInvitation(),
            $this->taskAssignment(),
            $this->taskStatusUpdate(),
            $this->dueDateReminder(),
            $this->commentNotification(),
            $this->boardInvitation(),
            $this->accountVerification(),
        ];
    }

    /** @return array<string, mixed> */
    private function welcome(): array
    {
        return [
            'template_name' => 'Welcome Email',
            'template_key' => 'welcome.user',
            'slug' => 'welcome-email',
            'category' => EmailTemplateCategory::Onboarding->value,
            'subject' => 'Welcome to {{ app_name }} 🎉',
            'description' => 'Sent immediately after a user finishes signing up.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'dashboard_link', 'label' => 'Dashboard link', 'sample' => 'https://kanban.test/dashboard'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>Welcome to <strong>{{ app_name }}</strong>! We are excited to have you as part of our community.</p>

<p>Your account has been created successfully, and you can now access all platform features including workspaces, boards, tasks, and team collaboration tools.</p>

<p>We recommend completing your profile setup and exploring the dashboard to get started smoothly.</p>

<p><strong>Dashboard Link:</strong></p>
<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ dashboard_link }}">Open Dashboard</a>
</p>

<p>If you need any assistance, feel free to contact our support team anytime.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

Welcome to {{ app_name }}! We are excited to have you as part of our community.

Your account has been created successfully, and you can now access all platform features including workspaces, boards, tasks, and team collaboration tools.

We recommend completing your profile setup and exploring the dashboard to get started smoothly.

Dashboard Link:
{{ dashboard_link }}

If you need any assistance, feel free to contact our support team anytime.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function otpVerification(): array
    {
        return [
            'template_name' => 'OTP Verification',
            'template_key' => 'auth.otp',
            'slug' => 'otp-verification',
            'category' => EmailTemplateCategory::Authentication->value,
            'subject' => 'Your OTP Verification Code',
            'description' => 'One-time password sent during two-factor / sign-in confirmation.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'otp_code', 'label' => 'OTP code', 'sample' => '482917'],
                ['key' => 'otp_expiry', 'label' => 'Expiry minutes', 'sample' => '10'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>We received a request to verify your identity for secure access to your account.</p>

<p>Please use the following One-Time Password (OTP) to continue the verification process:</p>

<div class="card" style="text-align:center;">
    <div style="font-size: 12px; color:#64748b; text-transform: uppercase; letter-spacing: 1.5px;">OTP Code</div>
    <div style="font-size: 28px; font-weight: 700; letter-spacing: 6px; color:#0f172a; margin-top: 6px;">{{ otp_code }}</div>
</div>

<p>This OTP is valid for <strong>{{ otp_expiry }} minutes</strong>. Please do not share this code with anyone for security reasons.</p>

<p>If you did not request this verification, please ignore this email or contact support immediately.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

We received a request to verify your identity for secure access to your account.

Please use the following One-Time Password (OTP) to continue the verification process:

OTP Code: {{ otp_code }}

This OTP is valid for {{ otp_expiry }} minutes. Please do not share this code with anyone for security reasons.

If you did not request this verification, please ignore this email or contact support immediately.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function passwordReset(): array
    {
        return [
            'template_name' => 'Password Reset',
            'template_key' => 'auth.password_reset',
            'slug' => 'password-reset',
            'category' => EmailTemplateCategory::Authentication->value,
            'subject' => 'Reset Your Password Request',
            'description' => 'Triggered when a user requests a password reset link.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'reset_link', 'label' => 'Reset link', 'sample' => 'https://kanban.test/reset/xyz'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>We received a request to reset the password associated with your account.</p>

<p>To create a new password and regain access to your account, please use the password reset link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ reset_link }}">Reset Password</a>
</p>

<p>For security purposes, this link may expire after a limited time. If you did not request a password reset, please ignore this email.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

We received a request to reset the password associated with your account.

To create a new password and regain access to your account, please use the password reset link below:

{{ reset_link }}

For security purposes, this link may expire after a limited time. If you did not request a password reset, please ignore this email.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function workspaceInvitation(): array
    {
        return [
            'template_name' => 'Workspace Invitation',
            'template_key' => 'workspace.invitation',
            'slug' => 'workspace-invitation',
            'category' => EmailTemplateCategory::Workspace->value,
            'subject' => "You've Been Invited to Join a Workspace",
            'description' => 'Sent when a workspace admin invites someone via email.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'workspace_name', 'label' => 'Workspace name', 'sample' => 'Acme Corp'],
                ['key' => 'workspace_link', 'label' => 'Workspace link', 'sample' => 'https://kanban.test/workspaces/acme'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>You have been invited to join the workspace <strong>"{{ workspace_name }}"</strong> on <strong>{{ app_name }}</strong>.</p>

<p>By joining this workspace, you will be able to collaborate with team members, manage projects, track tasks, and stay updated with ongoing activities.</p>

<p>Please use the link below to accept the invitation and access the workspace:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ workspace_link }}">Accept Invitation</a>
</p>

<p>We look forward to your collaboration with the team.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

You have been invited to join the workspace "{{ workspace_name }}" on {{ app_name }}.

By joining this workspace, you will be able to collaborate with team members, manage projects, track tasks, and stay updated with ongoing activities.

Please use the link below to accept the invitation and access the workspace:

{{ workspace_link }}

We look forward to your collaboration with the team.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function taskAssignment(): array
    {
        return [
            'template_name' => 'Task Assignment',
            'template_key' => 'task.assigned',
            'slug' => 'task-assignment',
            'category' => EmailTemplateCategory::Task->value,
            'subject' => 'New Task Assigned to You',
            'description' => 'Sent to a user when a new card is assigned to them.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'task_title', 'label' => 'Task title', 'sample' => 'Build login page'],
                ['key' => 'assigned_by', 'label' => 'Assigned by', 'sample' => 'Maya Sharma'],
                ['key' => 'due_date', 'label' => 'Due date', 'sample' => '2026-06-01'],
                ['key' => 'task_link', 'label' => 'Task link', 'sample' => 'https://kanban.test/tasks/123'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>A new task has been assigned to you and requires your attention.</p>

<div class="card">
    <strong>Task Title:</strong> {{ task_title }}<br>
    <strong>Assigned By:</strong> {{ assigned_by }}<br>
    <strong>Due Date:</strong> {{ due_date }}
</div>

<p>You can review the task details, attachments, and progress updates using the link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ task_link }}">Open Task</a>
</p>

<p>Please ensure the task is completed within the given timeline.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

A new task has been assigned to you and requires your attention.

Task Title: {{ task_title }}
Assigned By: {{ assigned_by }}
Due Date: {{ due_date }}

You can review the task details, attachments, and progress updates using the link below:

{{ task_link }}

Please ensure the task is completed within the given timeline.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function taskStatusUpdate(): array
    {
        return [
            'template_name' => 'Task Status Update',
            'template_key' => 'task.status_update',
            'slug' => 'task-status-update',
            'category' => EmailTemplateCategory::Task->value,
            'subject' => 'Task Status Updated',
            'description' => 'Notification fired when a card transitions to a new workflow stage.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'task_title', 'label' => 'Task title', 'sample' => 'Build login page'],
                ['key' => 'task_status', 'label' => 'Task status', 'sample' => 'In Progress'],
                ['key' => 'task_link', 'label' => 'Task link', 'sample' => 'https://kanban.test/tasks/123'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>The status of your task has been updated successfully.</p>

<div class="card">
    <strong>Task Title:</strong> {{ task_title }}<br>
    <strong>Updated Status:</strong> {{ task_status }}
</div>

<p>You can open the task to review the latest updates, comments, and activity details from the link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ task_link }}">Open Task</a>
</p>

<p>Please continue tracking the progress to ensure smooth project completion.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

The status of your task has been updated successfully.

Task Title: {{ task_title }}
Updated Status: {{ task_status }}

You can open the task to review the latest updates, comments, and activity details from the link below:

{{ task_link }}

Please continue tracking the progress to ensure smooth project completion.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function dueDateReminder(): array
    {
        return [
            'template_name' => 'Due Date Reminder',
            'template_key' => 'task.due_reminder',
            'slug' => 'due-date-reminder',
            'category' => EmailTemplateCategory::Task->value,
            'subject' => 'Reminder: Task Due Date Approaching',
            'description' => "Friendly nudge sent ahead of a card's due date.",
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'task_title', 'label' => 'Task title', 'sample' => 'Build login page'],
                ['key' => 'due_date', 'label' => 'Due date', 'sample' => '2026-06-01'],
                ['key' => 'task_link', 'label' => 'Task link', 'sample' => 'https://kanban.test/tasks/123'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>This is a reminder that the due date for your assigned task is approaching soon.</p>

<div class="card">
    <strong>Task Title:</strong> {{ task_title }}<br>
    <strong>Due Date:</strong> {{ due_date }}
</div>

<p>Please make sure to complete the pending work before the deadline to avoid delays in project progress.</p>

<p>You can access the task details using the link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ task_link }}">Open Task</a>
</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

This is a reminder that the due date for your assigned task is approaching soon.

Task Title: {{ task_title }}
Due Date: {{ due_date }}

Please make sure to complete the pending work before the deadline to avoid delays in project progress.

You can access the task details using the link below:

{{ task_link }}

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function commentNotification(): array
    {
        return [
            'template_name' => 'Comment Notification',
            'template_key' => 'task.comment',
            'slug' => 'comment-notification',
            'category' => EmailTemplateCategory::Notification->value,
            'subject' => 'New Comment Added on Your Task',
            'description' => 'Notifies card watchers when someone posts a comment.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'commented_by', 'label' => 'Commented by', 'sample' => 'Maya Sharma'],
                ['key' => 'comment_text', 'label' => 'Comment text', 'sample' => 'Looks great — shipping today.'],
                ['key' => 'comment_link', 'label' => 'Comment link', 'sample' => 'https://kanban.test/tasks/123#comment-7'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>A new comment has been added to the task or board you are associated with.</p>

<div class="card">
    <strong>Commented By:</strong> {{ commented_by }}<br>
    <strong>Comment:</strong> {{ comment_text }}
</div>

<p>You can review the complete discussion and reply directly from the link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ comment_link }}">View Comment</a>
</p>

<p>Stay connected with your team to ensure smooth communication and collaboration.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

A new comment has been added to the task or board you are associated with.

Commented By: {{ commented_by }}
Comment: {{ comment_text }}

You can review the complete discussion and reply directly from the link below:

{{ comment_link }}

Stay connected with your team to ensure smooth communication and collaboration.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function boardInvitation(): array
    {
        return [
            'template_name' => 'Board Invitation',
            'template_key' => 'board.invitation',
            'slug' => 'board-invitation',
            'category' => EmailTemplateCategory::Board->value,
            'subject' => "You've Been Added to a Board",
            'description' => 'Sent when a board owner shares a board with a specific user.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'board_name', 'label' => 'Board name', 'sample' => 'Sprint Board'],
                ['key' => 'board_link', 'label' => 'Board link', 'sample' => 'https://kanban.test/boards/sprint'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>You have been invited to join the board <strong>"{{ board_name }}"</strong> on <strong>{{ app_name }}</strong>.</p>

<p>This board contains important project-related tasks, updates, and collaboration activities shared by your team members.</p>

<p>Please use the link below to access the board and start collaborating:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ board_link }}">Open Board</a>
</p>

<p>We're excited to have you contribute to the project.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

You have been invited to join the board "{{ board_name }}" on {{ app_name }}.

This board contains important project-related tasks, updates, and collaboration activities shared by your team members.

Please use the link below to access the board and start collaborating:

{{ board_link }}

We're excited to have you contribute to the project.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }

    /** @return array<string, mixed> */
    private function accountVerification(): array
    {
        return [
            'template_name' => 'Account Verification',
            'template_key' => 'auth.verify_email',
            'slug' => 'account-verification',
            'category' => EmailTemplateCategory::Authentication->value,
            'subject' => 'Verify Your Account',
            'description' => 'Sent after registration so a new user can prove ownership of their email.',
            'variables' => [
                ['key' => 'user_name', 'label' => 'User name', 'sample' => 'Jane Doe'],
                ['key' => 'app_name', 'label' => 'Application name', 'sample' => 'Kanban'],
                ['key' => 'verification_link', 'label' => 'Verification link', 'sample' => 'https://kanban.test/verify/abc'],
            ],
            'body_content' => <<<'HTML'
<p>Hello {{ user_name }},</p>

<p>Thank you for registering with <strong>{{ app_name }}</strong>.</p>

<p>To activate your account and access all platform features securely, please verify your account using the link below:</p>

<p style="text-align:center; margin: 20px 0;">
    <a class="btn" href="{{ verification_link }}">Verify Account</a>
</p>

<p>Account verification helps us keep your account secure and ensures uninterrupted access to the platform.</p>

<p>If you did not create this account, please ignore this email.</p>

<p>Regards,<br>
<strong>{{ app_name }} Team</strong></p>
HTML,
            'plain_text' => <<<'TEXT'
Hello {{ user_name }},

Thank you for registering with {{ app_name }}.

To activate your account and access all platform features securely, please verify your account using the link below:

{{ verification_link }}

Account verification helps us keep your account secure and ensures uninterrupted access to the platform.

If you did not create this account, please ignore this email.

Regards,
{{ app_name }} Team
TEXT,
        ];
    }
}
