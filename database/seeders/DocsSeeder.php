<?php

namespace Database\Seeders;

use App\Models\DocArticle;
use App\Models\DocCategory;
use App\Models\DocImage;
use Illuminate\Database\Seeder;

/**
 * The shipped help centre.
 *
 * Content lives one section per file in database/seeders/docs/ — a single
 * array in this class would be two thousand lines and every edit would touch
 * it. Each file returns:
 *
 *   ['slug' => …, 'title' => …, 'summary' => …, 'icon' => …, 'sort' => …,
 *    'articles' => [ ['slug','title','excerpt','keywords','body'], … ]]
 *
 * UPSERT, NOT REPLACE. The whole point of the /admin/docs editor is that a
 * human can correct this text without a deploy; re-running the seeder after
 * that must not silently throw their edits away. Matching is on slug, and an
 * article whose row already exists is left ALONE — reseeding adds what is
 * missing and nothing else. To deliberately reset one, delete it in the admin
 * and run the seeder again.
 */
class DocsSeeder extends Seeder
{
    public function run(): void
    {
        $added   = 0;
        $skipped = 0;

        foreach ($this->sections() as $section) {
            $category = DocCategory::firstOrCreate(
                ['slug' => $section['slug']],
                [
                    'title'        => $section['title'],
                    'summary'      => $section['summary'] ?? null,
                    'icon'         => $section['icon'] ?? 'book-open',
                    'sort_order'   => $section['sort'] ?? 0,
                    'is_published' => true,
                ]
            );

            $order = 10;

            foreach ($section['articles'] as $article) {
                $exists = DocArticle::where('slug', $article['slug'])->exists();

                if ($exists) {
                    $skipped++;
                    $order += 10;

                    continue;
                }

                $row = DocArticle::create([
                    'doc_category_id' => $category->id,
                    'title'           => $article['title'],
                    'slug'            => $article['slug'],
                    'excerpt'         => $article['excerpt'] ?? null,
                    'keywords'        => $article['keywords'] ?? null,
                    'body'            => trim($article['body']),
                    'sort_order'      => $order,
                    'is_published'    => true,
                ]);

                $this->registerFigures($row);

                $added++;
                $order += 10;
            }
        }

        $this->command?->info("Help centre: {$added} article(s) added, {$skipped} left untouched.");
    }

    /**
     * Record the figures already written into a seeded body as doc_images rows.
     *
     * The markup is the source of truth — an article is edited as HTML and a
     * figure can be moved, so the row is a HANDLE for the admin UI (reinsert,
     * rename the alt text, see what this article uses), not the thing that
     * renders. Without it the Figures panel is empty on every shipped article
     * and the only images an admin can manage are ones they uploaded
     * themselves.
     *
     * Nothing is deleted from disk when such a row is removed: these paths
     * point at public/images/docs, which is repo content on neither storage
     * disk, so DocImage's cleanup hook finds nothing and correctly does
     * nothing.
     */
    private function registerFigures(DocArticle $article): void
    {
        preg_match_all(
            '#<figure><img src="([^"]+)" alt="([^"]*)"\s*/?>(?:<figcaption>(.*?)</figcaption>)?#s',
            (string) $article->body,
            $matches,
            PREG_SET_ORDER
        );

        $order = 10;

        foreach ($matches as $match) {
            DocImage::create([
                'doc_article_id' => $article->id,
                'path'           => ltrim($match[1], '/'),
                'alt'            => html_entity_decode($match[2]) ?: null,
                'caption'        => isset($match[3]) && $match[3] !== ''
                    ? html_entity_decode(strip_tags($match[3]))
                    : null,
                'sort_order'     => $order,
            ]);

            $order += 10;
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function sections(): array
    {
        $sections = [];

        foreach (glob(__DIR__ . '/docs/*.php') ?: [] as $file) {
            $sections[] = require $file;
        }

        usort($sections, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $sections;
    }
}
