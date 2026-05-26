<?php

declare(strict_types=1);

namespace App\Services\EmailTemplate;

use App\Enums\EmailTemplateStatus;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Application service for CRUD + listing on EmailTemplate. The controller
 * is intentionally thin — it parses requests and delegates everything
 * stateful to this class so the same business rules can be reused from
 * artisan commands, jobs and tests.
 */
final class EmailTemplateService
{
    /**
     * Build a paginated listing with search / category / status filters
     * and an optional sort. Used by both the Inertia index and the JSON
     * API endpoints so they stay in lockstep.
     *
     * @param  array{q?: ?string, status?: ?string, category?: ?string, sort?: ?string, direction?: ?string, per_page?: int}  $filters
     * @return LengthAwarePaginator<EmailTemplate>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = EmailTemplate::query()->with(['creator:id,name', 'updater:id,name']);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user): EmailTemplate
    {
        return DB::transaction(function () use ($data, $user) {
            $data['created_by'] = $user?->id;
            $data['updated_by'] = $user?->id;
            $data['status'] = $data['status'] ?? EmailTemplateStatus::Active->value;

            return EmailTemplate::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmailTemplate $template, array $data, ?User $user): EmailTemplate
    {
        return DB::transaction(function () use ($template, $data, $user) {
            $data['updated_by'] = $user?->id;
            $template->update($data);

            return $template->refresh();
        });
    }

    public function delete(EmailTemplate $template): void
    {
        // System templates are protected — the UI hides the delete button,
        // but we still defend the service entry-point in case the API is
        // called directly.
        if ($template->is_system_template) {
            throw new \DomainException('System templates cannot be deleted.');
        }
        $template->delete();
    }

    public function duplicate(EmailTemplate $template, ?User $user): EmailTemplate
    {
        return DB::transaction(function () use ($template, $user) {
            $copyName = $template->template_name . ' (Copy)';

            $copy = $template->replicate([
                'template_key',
                'slug',
                'created_by',
                'updated_by',
            ]);
            $copy->template_name = $copyName;
            $copy->template_key = EmailTemplate::generateUniqueKey($copyName);
            $copy->slug = EmailTemplate::generateUniqueSlug($copyName);
            $copy->is_system_template = false;
            $copy->status = EmailTemplateStatus::Draft->value;
            $copy->version = 1;
            $copy->created_by = $user?->id;
            $copy->updated_by = $user?->id;
            $copy->save();

            return $copy;
        });
    }

    public function toggleStatus(EmailTemplate $template, ?User $user): EmailTemplate
    {
        $next = $template->status === EmailTemplateStatus::Active
            ? EmailTemplateStatus::Inactive
            : EmailTemplateStatus::Active;

        return $this->update($template, ['status' => $next->value], $user);
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('template_name', 'like', $like)
                    ->orWhere('template_key', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, array_column(EmailTemplateStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            $query->where('category', $category);
        }
    }

    /**
     * @param  Builder<EmailTemplate>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sortable = [
            'template_name',
            'category',
            'status',
            'updated_at',
            'created_at',
        ];

        $sort = (string) ($filters['sort'] ?? 'updated_at');
        if (! in_array($sort, $sortable, true)) {
            $sort = 'updated_at';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';

        $query->orderBy($sort, $direction);
    }
}
