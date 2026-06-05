<?php

namespace App\Services;

use App\Models\ArticleShareLink;
use App\Models\Blog;
use App\Models\Setting;
use Illuminate\Support\Str;

class ArticleShareLinkService
{
    public const PLATFORMS = ['facebook', 'instagram', 'tiktok', 'whatsapp', 'link'];

    public function syncForBlog(Blog $blog): array
    {
        if ($blog->status !== 'published') {
            return $this->linksForBlog($blog);
        }

        $blog->loadMissing('seriesCategory:id,slug');
        $targetUrl = $this->targetUrl($blog);

        foreach (self::PLATFORMS as $platform) {
            $shareLink = ArticleShareLink::firstOrNew([
                'blog_id' => $blog->id,
                'platform' => $platform,
            ]);

            if (! $shareLink->exists || strlen((string) $shareLink->code) > 10) {
                $shareLink->code = $this->uniqueShareCode($blog, $platform, $shareLink->exists ? $shareLink->id : null);
            }

            $shareLink->target_url = $targetUrl;
            $shareLink->is_active = true;
            $shareLink->save();
        }

        $blog->unsetRelation('shareLinks');

        return $this->linksForBlog($blog);
    }

    public function syncForCategory(int $categoryId): int
    {
        $count = 0;

        Blog::with('seriesCategory:id,slug')
            ->where('category_id', $categoryId)
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(100, function ($blogs) use (&$count) {
                foreach ($blogs as $blog) {
                    $this->syncForBlog($blog);
                    $count++;
                }
            });

        return $count;
    }

    public function syncAllPublished(): int
    {
        $count = 0;

        Blog::with('seriesCategory:id,slug')
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(100, function ($blogs) use (&$count) {
                foreach ($blogs as $blog) {
                    $this->syncForBlog($blog);
                    $count++;
                }
            });

        return $count;
    }

    public function linksForBlog(Blog $blog): array
    {
        $siteUrl = $this->siteUrl();
        $links = $blog->relationLoaded('shareLinks')
            ? $blog->shareLinks
            : ArticleShareLink::where('blog_id', $blog->id)->where('is_active', true)->get();

        return $links
            ->where('is_active', true)
            ->mapWithKeys(fn (ArticleShareLink $link) => [
                $link->platform => [
                    'shortUrl' => $siteUrl.'/s/'.$link->code,
                    'targetUrl' => $link->target_url,
                ],
            ])
            ->all();
    }

    private function targetUrl(Blog $blog): string
    {
        $categorySlug = $blog->seriesCategory?->slug;
        $targetPath = $categorySlug ? '/'.$categorySlug.'/'.$blog->slug : '/'.$blog->slug;

        return $this->siteUrl().$targetPath;
    }

    private function siteUrl(): string
    {
        return rtrim(Setting::where('name', 'website_url')->value('value') ?: 'https://physicsfundamental.org', '/');
    }

    private function uniqueShareCode(Blog $blog, string $platform, ?int $ignoreId = null): string
    {
        $platformPrefix = substr(preg_replace('/[^a-z0-9]/i', '', $platform) ?: 's', 0, 1);
        $blogKey = base_convert((string) $blog->id, 10, 36);
        $hash = substr(hash('crc32b', $blog->slug.'|'.$platform), 0, 4);
        $base = Str::lower($platformPrefix.$blogKey.$hash);
        $code = $base;
        $counter = 2;

        while (ArticleShareLink::where('code', $code)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.base_convert((string) $counter++, 10, 36);
        }

        return $code;
    }
}
