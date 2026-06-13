<?php

namespace App\Modules\Sites\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sites\Actions\CreateFolderAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use App\Modules\Sites\DTO\CreateFolderData;
use App\Modules\Sites\Http\Requests\StoreFolderRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FolderController extends Controller
{
    public function __construct(
        private readonly CreateFolderAction $createFolder,
        private readonly GetCurrentOrganization $getCurrentOrganization,
    )
    {
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

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $organization = $this->getCurrentOrganization->handle($request->user());

        $validated = $request->validated();

        $this->createFolder->handle(
            new CreateFolderData(
                organizationId: $organization->id,
                name: $validated['name'],
                color: "#ffffff",
                sortOrder: 0,
            ),
        );

        return to_route('sites.index');
    }
}
