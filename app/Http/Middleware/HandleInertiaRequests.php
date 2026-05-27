<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Shape the user payload here (instead of returning the
                // raw Eloquent model) so we can attach the RBAC fields
                // (`roles`, `permissions`) needed by the frontend
                // `usePermissions` composable + `v-can` directive.
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->gravatar(),
                    'timezone' => $user->timezone,
                    'mentor_id' => $user->mentor_id,
                    'job_title' => $user->job_title?->value,
                    'job_title_label' => $user->job_title?->label(),
                    // Flat lists so the frontend can do
                    //   permissions.includes('roles.manage')
                    // and
                    //   roles.includes('Super Admin')
                    // without further reshaping.
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'is_super_admin' => $user->isSuperAdmin(),
                ],
            ],
            // Workspaces the current user belongs to. Powers the workspace
            // switcher dropdown in the top bar across every authenticated
            // page. Wrapped in a closure so unauthenticated requests skip
            // the query entirely.
            'nav' => [
                'workspaces' => fn () => $request->user()
                    ? $request->user()
                        ->workspaces()
                        ->select('workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.avatar_url')
                        // Newest workspace first so the top-bar switcher
                        // / sidebar shortcuts stay in sync with the
                        // workspace listing screen.
                        ->orderByDesc('workspaces.created_at')
                        ->get()
                        ->map(fn ($w) => [
                            'id' => $w->id,
                            'name' => $w->name,
                            'slug' => $w->slug,
                            'avatar_url' => $w->avatar_url,
                        ])
                        ->values()
                    : [],
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
