<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The horizontal card a network draws when the link of a Book is pasted
     * (BIB-50).
     *
     * It is one image and not two: a Book has nothing to upload to Instagram by
     * hand, which is the whole reason a Quote has a second, vertical one
     * (ADR-009). So it gets columns of its own instead of the ones a Quote
     * carries, and they are singular for the same reason.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('share_image_path')->nullable()->after('cover_origin');

            /*
             * The signature of what the card was drawn from — the title, the
             * Authors and the Cover. It travels in the address of the image,
             * because a network caches `og:image` by URL and with a will: an
             * address that stays the same keeps showing the card of a month
             * ago.
             */
            $table->string('share_image_fingerprint', 32)->nullable()->after('share_image_path');

            /*
             * Waiting its turn, and what the queue gave up on. They are the
             * same three marks the Quotes carry, and they answer the same
             * question: a Book with no card is one on its way, one the renderer
             * refused, or one whose credits ran out.
             */
            $table->timestamp('share_image_queued_at')->nullable()->after('share_image_fingerprint');
            $table->timestamp('share_image_failed_at')->nullable()->after('share_image_queued_at');
            $table->string('share_image_failure')->nullable()->after('share_image_failed_at');

            $table->index('share_image_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['share_image_queued_at']);

            $table->dropColumn([
                'share_image_path',
                'share_image_fingerprint',
                'share_image_queued_at',
                'share_image_failed_at',
                'share_image_failure',
            ]);
        });
    }
};
