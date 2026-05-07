<?php

namespace App\Modules\Sites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use App\Modules\Sites\Actions\CreateSite;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\Http\Requests\StoreSiteRequest;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IndexController extends Controller
{
    public function index(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
    ): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $sites = Site::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'host' => $site->host,
                'status' => $site->status,
            ]);

        return Inertia::render('Sites/Index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'sites' => $sites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Sites/Create');
    }

    public function store(
        StoreSiteRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CreateDefaultFolderForOrganization $createDefaultFolderForOrganization,
        CreateSite $createSite
    ): RedirectResponse
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $folder = $createDefaultFolderForOrganization->handle($organization);

        $createSite->handle(
            $request->toData(
                organizationId: $organization->id,
                folder: $folder,
            )
        );

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site added.');
    }

    public function show(
        Request $request,
        Site $site,
        GetCurrentOrganization $getCurrentOrganization,
    ): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($site->organization_id !== $organization->id) {
            throw new NotFoundHttpException();
        }

        return Inertia::render('Sites/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],

            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'scheme' => $site->scheme,
                'host' => $site->host,
                'port' => $site->port,
                'path' => $site->path,
                'status' => $site->status,
                'created_at' => $site->created_at?->toISOString(),
            ],
        ]);
    }
}
