<?php

namespace App\Console\Commands;

use App\Ai\AiModel;
use App\Ai\CanonicalCategories;
use App\Ai\CategoryMapperAgent;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * One-time cleanup: merges the pre-BIB-68 ad-hoc categories into the canonical
 * vocabulary using the configured AI model.
 *
 * Each non-canonical category with books is sent to the AI together with a
 * sample of its book titles. The AI picks the best canonical match; the command
 * then calls absorb() so the books move over and the old slug keeps working.
 * Categories the AI cannot map are left for manual review on the categories
 * screen.
 */
class AutoMergeCategories extends Command
{
    protected $signature = 'categories:auto-merge
                            {--dry-run : Preview matches without merging anything}';

    protected $description = 'Merge non-canonical categories into canonical ones using AI.';

    public function handle(): int
    {
        $chosen = AiModel::chosen();

        if (! $chosen instanceof AiModel) {
            $this->error('No AI model configured. Go to Ajustes → Modelo de IA and pick one.');

            return self::FAILURE;
        }

        $pending = Category::query()
            ->pendingReview()
            ->with(['books' => fn ($q) => $q->select('books.id', 'books.title')->limit(10)])
            ->orderBy('name')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No non-canonical categories to clean up.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->line('<fg=yellow>Dry run — nothing will be merged.</>');
        }

        $this->line("Asking the AI to map {$pending->count()} categories…");
        $this->newLine();

        $merged = 0;
        $skipped = 0;

        foreach ($pending as $category) {
            $titles = $category->books->pluck('title')->all();

            $prompt = implode("\n", [
                'Non-canonical category: "' . $category->name . '"',
                '',
                'Books assigned to it:',
                ...array_map(fn (string $t) => '- ' . $t, $titles),
                '',
                'Canonical list — return one of these exact names, or "ninguna":',
                ...array_map(fn (string $n) => '- ' . $n, CanonicalCategories::LIST),
            ]);

            $response = (new CategoryMapperAgent)->prompt(
                $prompt,
                provider: $chosen->provider,
                model: $chosen->name,
            );

            if (! $response instanceof StructuredAgentResponse) {
                $this->warn("  Skipped (unusable response): {$category->name}");
                $skipped++;
                continue;
            }

            $canonicalName = trim($response->structured['canonical'] ?? '');

            if ($canonicalName === '' || strtolower($canonicalName) === 'ninguna') {
                $this->line("  No match:  {$category->name}");
                $skipped++;
                continue;
            }

            $canonical = Category::query()
                ->where('is_canonical', true)
                ->where('slug', Str::slug($canonicalName))
                ->first();

            if (! $canonical instanceof Category) {
                $this->warn("  Unknown canonical '{$canonicalName}' returned for '{$category->name}'");
                $skipped++;
                continue;
            }

            $this->line("  Merge:     {$category->name}  →  {$canonical->name}");

            if (! $dryRun) {
                $canonical->absorb($category);
            }

            $merged++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->line("Would merge {$merged}, skip {$skipped}.");
        } else {
            $this->info("Done. Merged: {$merged}. Left for manual review: {$skipped}.");
        }

        return self::SUCCESS;
    }
}
