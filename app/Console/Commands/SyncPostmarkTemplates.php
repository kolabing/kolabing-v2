<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendTransactionalEmail;
use App\Services\PostmarkClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bootstraps / syncs the Phase 2 transactional email templates into Postmark
 * from resources/postmark/templates.php, rendering each definition's blocks
 * into the standard Kolabing HTML scaffold (matches business-welcome-01).
 *
 *   php artisan email:sync-templates                       # create missing aliases only
 *   php artisan email:sync-templates --force               # also overwrite existing
 *   php artisan email:sync-templates --only=password-reset # limit to specific aliases
 *   php artisan email:sync-templates --test=you@email.com  # send a sample of each after sync
 *   php artisan email:sync-templates --dry                 # render + print, push nothing
 */
class SyncPostmarkTemplates extends Command
{
    protected $signature = 'email:sync-templates
        {--force : Overwrite templates whose alias already exists}
        {--only=* : Limit to these aliases}
        {--test= : After syncing, send a sample render of each template to this address}
        {--dry : Render and report only; do not push to Postmark}';

    protected $description = 'Create/update Postmark transactional email templates from the local seed definitions.';

    public function handle(PostmarkClient $postmark): int
    {
        if (! $this->option('dry') && blank(config('services.postmark.key'))) {
            $this->error('POSTMARK_API_KEY is not set.');

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $definitions */
        $definitions = require resource_path('postmark/templates.php');

        $only = (array) $this->option('only');
        if ($only !== []) {
            $definitions = array_values(array_filter(
                $definitions,
                fn (array $d): bool => in_array($d['alias'], $only, true),
            ));
        }

        if ($definitions === []) {
            $this->warn('No template definitions matched.');

            return self::SUCCESS;
        }

        $existing = $this->option('dry') ? [] : $postmark->templateAliasMap();
        $synced = [];

        foreach ($definitions as $def) {
            $alias = $def['alias'];
            $html = $this->renderHtml($def);
            $text = $this->renderText($def);

            $payload = [
                'Name' => $def['name'],
                'Subject' => $def['subject'],
                'HtmlBody' => $html,
                'TextBody' => $text,
                'Alias' => $alias,
                'TemplateType' => 'Standard',
            ];

            if ($this->option('dry')) {
                $this->line("── {$alias} ── ".$def['subject']);

                continue;
            }

            try {
                if (isset($existing[$alias])) {
                    if (! $this->option('force')) {
                        $this->line("  • skip (exists) → {$alias}");
                        $synced[] = $def;

                        continue;
                    }
                    $postmark->updateTemplate($existing[$alias], $payload);
                    $this->info("  ✓ updated → {$alias}");
                } else {
                    $postmark->createTemplate($payload);
                    $this->info("  ✓ created → {$alias}");
                }
                $synced[] = $def;
            } catch (Throwable $e) {
                $this->error("  ✗ {$alias}: {$e->getMessage()}");
            }
        }

        if ($this->option('dry')) {
            $this->info('Dry run complete ('.count($definitions).' templates).');

            return self::SUCCESS;
        }

        $testTo = $this->option('test');
        if (is_string($testTo) && $testTo !== '') {
            $this->newLine();
            $this->info("Sending sample renders to {$testTo} ...");
            foreach ($synced as $def) {
                try {
                    dispatch_sync(SendTransactionalEmail::template(
                        to: $testTo,
                        templateAlias: $def['alias'],
                        model: $def['sample'] ?? [],
                    ));
                    $this->line("  ✓ test → {$def['alias']}");
                } catch (Throwable $e) {
                    $this->error("  ✗ test {$def['alias']}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info('Done. Postmark is the source of truth, edit copy there going forward.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function renderHtml(array $def): string
    {
        $body = '';

        foreach ($def['content'] as $block) {
            $body .= match ($block['type']) {
                'para' => '<p style="margin:0 0 16px;">'.$this->esc($block['text']).'</p>',
                'head' => '<p style="margin:0 0 8px;font-weight:700;">'.$this->esc($block['text']).'</p>',
                'list' => $this->renderHtmlList($block),
                'button' => $this->renderHtmlButton($block),
                'signature' => '<p style="margin:0 0 4px;">Maria</p>'
                    .'<p style="margin:0 0 24px;color:#555;">Co-founder, Kolabing</p>',
                'ps' => '<p style="margin:0;padding:16px 0 0;border-top:1px solid #eeeeee;font-size:14px;color:#555;">'
                    .'<strong>P.S.</strong> '.$this->esc($block['text']).'</p>',
                default => '',
            };
        }

        $title = $this->esc($def['subject']);

        return '<!doctype html><html><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title></head>'
            .'<body style="margin:0;padding:0;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',\'Open Sans\',Arial,sans-serif;color:#1a1a1a;line-height:1.55;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;"><tr>'
            .'<td align="center" style="padding:0;"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">'
            .'<tr><td align="center" style="padding:32px 16px;">'
            .'<img src="https://kolabing.com/brand/uploaded-logo-2.png" width="180" alt="Kolabing" style="display:block;border:0;width:180px;height:auto;max-width:52%;margin:0 auto;" /></td></tr>'
            .'<tr><td style="padding:32px 32px 16px;font-size:16px;color:#1a1a1a;">'.$body.'</td></tr>'
            .'<tr><td style="padding:16px 32px 32px;font-size:12px;color:#888;"><p style="margin:0;">Kolabing &middot; Barcelona &middot; '
            .'<a href="https://kolabing.com" style="color:#888;text-decoration:underline;">kolabing.com</a></p></td></tr>'
            .'</table></td></tr></table></body></html>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderHtmlList(array $block): string
    {
        $tag = ($block['ordered'] ?? false) ? 'ol' : 'ul';
        $items = '';
        foreach ($block['items'] as $item) {
            $items .= '<li style="margin:0 0 8px;">'.$this->esc($item).'</li>';
        }

        return '<'.$tag.' style="margin:0 0 24px;padding-left:22px;">'.$items.'</'.$tag.'>';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderHtmlButton(array $block): string
    {
        // URL may be a {{merge}} var, so it is not HTML-escaped.
        return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;"><tr>'
            .'<td style="border-radius:12px;background:#FFD861;">'
            .'<a href="'.$block['url'].'" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#232323;text-decoration:none;border-radius:12px;">'
            .$this->esc($block['label']).'</a></td></tr></table>';
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function renderText(array $def): string
    {
        $out = '';

        foreach ($def['content'] as $block) {
            $out .= match ($block['type']) {
                'para' => $block['text']."\n\n",
                'head' => $block['text']."\n",
                'list' => $this->renderTextList($block),
                'button' => $block['label'].': '.$block['url']."\n\n",
                'signature' => "Maria\nCo-founder, Kolabing\n\n",
                'ps' => 'P.S. '.$block['text']."\n\n",
                default => '',
            };
        }

        return $out."--\nKolabing - Barcelona - kolabing.com\n";
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderTextList(array $block): string
    {
        $out = '';
        $i = 1;
        foreach ($block['items'] as $item) {
            $prefix = ($block['ordered'] ?? false) ? ($i++).'. ' : '- ';
            $out .= $prefix.$item."\n";
        }

        return $out."\n";
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
