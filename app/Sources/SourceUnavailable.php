<?php

namespace App\Sources;

use RuntimeException;

/**
 * The Source could not answer. It is used inside an adapter to cut the lookup
 * short; outwards it comes out turned into `LookupResult::unavailable`.
 */
final class SourceUnavailable extends RuntimeException {}
