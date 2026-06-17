<?php

namespace App\Modules\Feedback\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Feedback\Application\Handlers\SendFeedbackMessageHandler;
use App\Modules\Feedback\Presentation\Http\Requests\StoreFeedbackMessageRequest;
use Illuminate\Http\RedirectResponse;

final class FeedbackMessageController extends Controller
{
    public function store(
        StoreFeedbackMessageRequest $request,
        SendFeedbackMessageHandler $handler,
    ): RedirectResponse {
        $handler->handle($request->toCommand());

        return back()->with('success', 'Спасибо, ваше обращение отправлено. Мы ответим на указанную почту.');
    }
}
