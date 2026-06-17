<?php

namespace App\Modules\Feedback\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Feedback\Infrastructure\Persistence\Models\ProductIdea;
use App\Modules\Feedback\Presentation\Http\Requests\StoreProductIdeaRequest;
use Illuminate\Http\RedirectResponse;

final class ProductIdeaController extends Controller
{
    public function store(StoreProductIdeaRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = $user?->organizations()->first(['organizations.id']);

        ProductIdea::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization?->id,
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString(),
            'type' => $request->string('type', 'feature')->toString(),
            'status' => 'new',
            'page_url' => $request->headers->get('referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Спасибо, идея сохранена. Мы посмотрим ее при планировании доработок.');
    }
}
