<?php

namespace Database\Factories;

use App\Enums\CoverOrigin;
use App\Enums\Origin;
use App\Enums\Source;
use App\Enums\Status;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'isbn' => self::anIsbn(),
            'title' => Str::title(rtrim(fake()->sentence(3), '.')),
            'authors' => [fake()->name()],
            'publisher' => fake()->company(),
            'year' => fake()->numberBetween(1950, 2026),
            'edition' => null,
            'description' => fake()->paragraph(),
            'description_origin' => Origin::Source,
            'source' => Source::OpenLibrary,
            'cover_path' => 'covers/'.fake()->uuid().'.jpg',
            'cover_source_url' => 'https://covers.openlibrary.org/b/id/'.fake()->numberBetween(1, 999999).'-L.jpg',
            'cover_thumbnail_path' => 'covers/'.fake()->uuid().'-thumbnail.jpg',
            'cover_origin' => CoverOrigin::Source,
            'status' => Status::Owned,
            'is_read' => false,
            'read_on' => null,
            'note' => null,
            'lent_to' => null,
            'lent_on' => null,
            'is_hidden' => false,
        ];
    }

    /**
     * An ISBN-13 whose check digit adds up.
     *
     * The digit is not decoration: `Isbn` rejects a number that does not add
     * up, so a made-up ISBN gives a Book that the screens cannot save.
     */
    private static function anIsbn(): string
    {
        $twelve = '978'.str_pad((string) fake()->unique()->numberBetween(1, 999999999), 9, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach (str_split($twelve) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return $twelve.((10 - $sum % 10) % 10);
    }

    /**
     * Read, with a read date.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_on' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
        ]);
    }

    /**
     * Read years ago, without knowing when.
     */
    public function readUndated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_on' => null,
        ]);
    }

    /**
     * A Book I want and do not have yet.
     */
    public function wanted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Wanted,
        ]);
    }

    public function owned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Owned,
        ]);
    }

    /**
     * Out of the Showcase.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * With the Description the Source brought.
     */
    public function descriptionFromSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => fake()->paragraph(),
            'description_origin' => Origin::Source,
            'description_provider' => null,
            'description_ai_model' => null,
        ]);
    }

    /**
     * With the Description the Source brought, in a language that is not
     * Spanish: the one BIB-45 translates.
     */
    public function descriptionInAnotherLanguage(?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $text ?? 'A practical handbook about the craft of writing software, and about what the people who do it have learned from one another over the years.',
            'description_origin' => Origin::Source,
            'description_original' => null,
            'description_provider' => null,
            'description_ai_model' => null,
        ]);
    }

    /**
     * With the Description of the Source already translated, and with the
     * original kept beside it (BIB-45).
     */
    public function descriptionTranslated(string $provider = 'gemini', string $model = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Un manual práctico sobre el oficio de escribir software, y sobre lo que quienes lo ejercen han aprendido unos de otros con los años.',
            'description_original' => 'A practical handbook about the craft of writing software, and about what the people who do it have learned from one another over the years.',
            'description_origin' => Origin::Translated,
            'description_provider' => $provider,
            'description_ai_model' => $model,
        ]);
    }

    /**
     * With the Description the AI wrote, and with which AiModel it wrote it.
     */
    public function descriptionFromAi(string $provider = 'gemini', string $model = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => fake()->paragraph(),
            'description_origin' => Origin::Ai,
            'description_provider' => $provider,
            'description_ai_model' => $model,
        ]);
    }

    /**
     * With the Description I wrote myself. No regeneration touches it.
     */
    public function descriptionFromOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => fake()->paragraph(),
            'description_origin' => Origin::Owner,
            'description_provider' => null,
            'description_ai_model' => null,
        ]);
    }

    /**
     * Without a Description: no Source brought one and the AI has not written it
     * yet.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
            'description_origin' => null,
            'description_provider' => null,
            'description_ai_model' => null,
        ]);
    }

    /**
     * With the Categories already generated by an AiModel.
     */
    public function categoriesGenerated(string $provider = 'gemini', string $model = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'categories_provider' => $provider,
            'categories_ai_model' => $model,
        ]);
    }

    /**
     * Waiting its turn in the queue: the button was pressed and the answer has
     * not arrived (BIB-22).
     */
    public function generationQueued(string $provider = 'gemini', string $aiModel = 'gemini-2.5-flash'): static
    {
        return $this->state(fn (array $attributes) => [
            'generation_queued_at' => now(),
            'generation_failed_at' => null,
            'generation_failure' => null,
            'generation_provider' => $provider,
            'generation_ai_model' => $aiModel,
        ]);
    }

    /**
     * Marked as waiting its turn for longer than a job can live: the worker was
     * killed, the queue was flushed, a deploy landed in the middle, and nothing
     * ever cleared the mark (BIB-46).
     */
    public function generationAbandoned(string $provider = 'gemini', string $aiModel = 'gemini-3.6-flash'): static
    {
        return $this->generationQueued($provider, $aiModel)->state(fn (array $attributes) => [
            'generation_queued_at' => Book::queueMarkExpiresBefore()->subHour(),
        ]);
    }

    /**
     * With the Categories corrected by hand, which a regeneration asks about
     * before replacing (BIB-22).
     */
    public function categoriesEditedByHand(): static
    {
        return $this->state(fn (array $attributes) => [
            'categories_edited_at' => now(),
        ]);
    }

    /**
     * With the generation given up on: the queue tried for hours and the
     * provider never got through (BIB-33).
     */
    public function generationFailed(
        string $reason = 'AI provider [gemini] is overloaded.',
        ?string $provider = 'gemini',
        ?string $aiModel = 'gemini-3.6-flash',
    ): static {
        return $this->state(fn (array $attributes) => [
            'generation_queued_at' => null,
            'generation_failed_at' => now(),
            'generation_failure' => $reason,
            'generation_provider' => $provider,
            'generation_ai_model' => $aiModel,
        ]);
    }

    /**
     * Lent out, with the date it left.
     */
    public function lent(?string $to = null): static
    {
        return $this->state(fn (array $attributes) => [
            'lent_to' => $to ?? fake()->firstName(),
            'lent_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ]);
    }

    /**
     * Without an ISBN: the old Books that carry no barcode.
     */
    public function withoutIsbn(): static
    {
        return $this->state(fn (array $attributes) => [
            'isbn' => null,
            'source' => Source::Manual,
            'cover_path' => null,
            'cover_source_url' => null,
            'cover_thumbnail_path' => null,
            'cover_origin' => CoverOrigin::Generated,
        ]);
    }

    /**
     * With the generated Cover and somewhere to download an image from: the
     * download failed, or has not run yet, and can be retried.
     */
    public function coverPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_path' => null,
            'cover_thumbnail_path' => null,
            'cover_source_url' => 'https://covers.openlibrary.org/b/id/'.fake()->numberBetween(1, 999999).'-L.jpg',
            'cover_origin' => CoverOrigin::Generated,
        ]);
    }

    /**
     * With the generated Cover and nowhere to download an image from: the queue
     * of what is left to photograph (ADR-008).
     */
    public function coverGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_path' => null,
            'cover_thumbnail_path' => null,
            'cover_source_url' => null,
            'cover_origin' => CoverOrigin::Generated,
        ]);
    }

    /**
     * With the download of the Cover given up on: the Source was asked and what
     * came back was not a Cover (BIB-52). It keeps somewhere to retry from,
     * which is the whole point of not clearing the URL.
     */
    public function coverDownloadFailed(string $reason = 'La Fuente contestó 404 al pedirle la imagen.'): static
    {
        return $this->coverPending()->state(fn (array $attributes) => [
            'cover_failed_at' => now(),
            'cover_failure' => $reason,
        ]);
    }

    /**
     * With the photograph the owner took. No retry against the Sources
     * replaces it.
     */
    public function coverFromOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_path' => 'covers/'.fake()->uuid().'.jpg',
            'cover_thumbnail_path' => 'covers/'.fake()->uuid().'-thumbnail.jpg',
            'cover_origin' => CoverOrigin::Owner,
        ]);
    }
}
