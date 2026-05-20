<?php

namespace App\Http\Controllers;

use App\Models\ArticleShareLink;

class ShareLinkController extends Controller
{
    public function redirect(string $code)
    {
        $shareLink = ArticleShareLink::where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        $shareLink->increment('click_count');

        return redirect()->away($shareLink->target_url);
    }
}
