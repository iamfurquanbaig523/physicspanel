<?php

namespace App\Console\Commands;

use App\Services\ArticleShareLinkService;
use App\Services\PublicContentCacheService;
use Illuminate\Console\Command;

class SyncArticleShareLinks extends Command
{
    protected $signature = 'articles:sync-share-links';

    protected $description = 'Create or repair share links for all published articles.';

    public function handle(ArticleShareLinkService $shareLinks): int
    {
        $count = $shareLinks->syncAllPublished();
        PublicContentCacheService::invalidate();

        $this->info("Synced share links for {$count} published articles.");

        return self::SUCCESS;
    }
}
