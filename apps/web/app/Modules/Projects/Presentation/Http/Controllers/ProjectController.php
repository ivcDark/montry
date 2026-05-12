<?php

namespace App\Modules\Projects\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Projects\Presentation\Http\Requests\StoreProjectRequest;
use App\Modules\Sites\Actions\CreateFolderAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\DTO\CreateFolderData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectController extends Controller
{
    public function __construct(
        private readonly CreateFolderAction $createProject,
        private readonly GetCurrentOrganization $getCurrentOrganization,
    ) {
    }

    public function create(Request $request): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        return Inertia::render('Sites/Folders/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        $validated = $request->validated();

        $this->createProject->handle(
            new CreateFolderData(
                organizationId: $organization->id,
                name: $validated['name'],
                color: $validated['color'] ?? '#ffffff',
                sortOrder: $validated['sort_order'] ?? 0,
            ),
        );

        return to_route('sites.index');
    }
}
