<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two images a Quote gets shared with (ADR-009): the horizontal one a
     * network draws when the link is pasted, and the vertical one that gets
     * uploaded to Instagram.
     *
     * They are columns of the Quote and not a table of their own: there are
     * exactly two, they are born and die with the Quote, and the only question
     * ever asked of them is which file each one is.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('card_image_path')->nullable()->after('page');
            $table->string('story_image_path')->nullable()->after('card_image_path');

            /*
             * The signature of what the two images were drawn from. It travels
             * in their address, because a network caches `og:image` by URL and
             * with a will: an address that stays the same keeps showing the
             * image of a month ago.
             */
            $table->string('images_fingerprint', 32)->nullable()->after('story_image_path');

            /*
             * Waiting its turn, and what the queue gave up on. A Quote with no
             * images is not one thing but three — one on its way, one the
             * renderer refused, and one whose credits ran out — and the screen
             * that counts them says which.
             */
            $table->timestamp('images_queued_at')->nullable()->after('images_fingerprint');
            $table->timestamp('images_failed_at')->nullable()->after('images_queued_at');
            $table->string('images_failure')->nullable()->after('images_failed_at');

            $table->index('images_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['images_queued_at']);

            $table->dropColumn([
                'card_image_path',
                'story_image_path',
                'images_fingerprint',
                'images_queued_at',
                'images_failed_at',
                'images_failure',
            ]);
        });
    }
};
