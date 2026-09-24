<?php

namespace App\Descriptions;

use App\Enums\DescriptionLanguage;

/**
 * Whether a Description reads as Spanish, decided here and not by asking a
 * model (BIB-45).
 *
 * The catalogues bring the description of an edition in the language the
 * edition was published in, so a shelf of technical books arrives half in
 * English. Those get translated — but sending the ones that are already in
 * Spanish costs quota and risks getting back a different text than the one that
 * was there. So the question gets answered before anything is asked.
 *
 * It is a count of function words, not a language identifier. Function words
 * are what a translation cannot avoid —«de», «que», «the», «of»— and they are
 * disjoint between the two languages, which is all this needs: the texts it
 * reads are whole paragraphs of prose from a catalogue, not two words.
 *
 * **Without evidence it answers Spanish.** Being wrong in that direction leaves
 * a text as it is; being wrong the other way rewrites, through a model, a text
 * that nobody asked to have rewritten.
 */
final class TextLanguage
{
    /**
     * How many foreign function words have to show up before the text counts as
     * not being Spanish. Below that there is no paragraph to judge.
     */
    private const EVIDENCE = 3;

    /**
     * Function words of Spanish. The ones that also exist in English —«no»,
     * «a», «me», «son»— are left out on purpose: a word that counts for both
     * sides counts for neither.
     *
     * @var list<string>
     */
    private const SPANISH = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'que', 'y',
        'en', 'por', 'para', 'con', 'como', 'su', 'sus', 'este', 'esta', 'estos', 'estas',
        'se', 'al', 'lo', 'es', 'pero', 'más', 'sobre', 'entre', 'cuando', 'también',
        'libro', 'autor', 'obra', 'vida', 'mundo', 'sin', 'ya', 'fue', 'han', 'hay',
        'aquí', 'cómo', 'qué', 'quién', 'donde', 'desde', 'hasta', 'años', 'nos', 'les',
        'todo', 'todos', 'toda', 'todas', 'otro', 'otra', 'cada', 'sólo', 'solo', 'muy',
    ];

    /**
     * Function words of English. The ones a Latin filler text is full of —«in»,
     * «at», «id», «a»— are left out: the descriptions of the tests are written
     * with those and they are not English.
     *
     * @var list<string>
     */
    private const ENGLISH = [
        'the', 'of', 'and', 'to', 'is', 'that', 'for', 'with', 'this', 'these', 'those',
        'from', 'by', 'are', 'was', 'were', 'has', 'have', 'been', 'not', 'but', 'or',
        'what', 'when', 'which', 'who', 'how', 'about', 'their', 'his', 'her', 'its',
        'they', 'we', 'you', 'your', 'will', 'would', 'could', 'should', 'book', 'books',
        'author', 'more', 'than', 'all', 'one', 'also', 'into', 'through', 'between',
        'over', 'after', 'before', 'does', 'it', 'there', 'other', 'such', 'only', 'most',
        'world', 'life', 'new', 'first', 'many', 'some', 'way', 'work', 'people', 'every',
    ];

    /** The characters that only a text written in Spanish carries. */
    private const SPANISH_CHARACTERS = 'áéíóúüñ¿¡';

    /**
     * How the text reads.
     */
    public static function of(?string $text): DescriptionLanguage
    {
        if ($text === null || trim($text) === '') {
            return DescriptionLanguage::Spanish;
        }

        $words = self::wordsIn($text);

        $foreign = self::matches($words, self::ENGLISH);

        if ($foreign < self::EVIDENCE) {
            return DescriptionLanguage::Spanish;
        }

        $spanish = self::matches($words, self::SPANISH) + self::spanishCharacters($text);

        return $foreign > $spanish
            ? DescriptionLanguage::Other
            : DescriptionLanguage::Spanish;
    }

    /**
     * Whether the text is in the language of the interface, which is the only
     * question anything else asks.
     */
    public static function isSpanish(?string $text): bool
    {
        return self::of($text)->isSpanish();
    }

    /**
     * @return list<string>
     */
    private static function wordsIn(string $text): array
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $markers
     */
    private static function matches(array $words, array $markers): int
    {
        $found = 0;

        foreach ($words as $word) {
            if (in_array($word, $markers, true)) {
                $found++;
            }
        }

        return $found;
    }

    /**
     * The accents and the opening marks, worth their weight but not unlimited:
     * one «café» in an English paragraph does not make it Spanish.
     */
    private static function spanishCharacters(string $text): int
    {
        $found = 0;

        foreach (mb_str_split(mb_strtolower($text)) as $character) {
            if (mb_strpos(self::SPANISH_CHARACTERS, $character) !== false) {
                $found++;
            }
        }

        return min($found, 5);
    }
}
