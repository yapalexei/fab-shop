<?php

namespace App\Support;

/**
 * Hygiene for text that crosses the boundary between the shop's data and an
 * AI: text that came from a customer or staff going *to* a model, and text a
 * model wrote coming *back* into the shop.
 *
 * None of this makes prompt injection impossible - nothing does - it removes
 * the cheap tricks and shrinks what an attack can carry: invisible characters
 * that hide instructions from the person reading, very long payloads, and,
 * for what an AI writes into records, links and markup (the usual way to
 * smuggle data out in a message a person later sends).
 */
final class UntrustedText
{
    /**
     * Zero-width, bidirectional-override and Unicode "tag" characters (the
     * U+E0000 block renders as nothing yet is read by models, so it can hide
     * a whole instruction), soft hyphens and the BOM; plus control codes
     * other than tab and newline.
     */
    private const HIDDEN = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}\x{00AD}\x{E0000}-\x{E007F}\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u';

    private const LINK = '/(?:\b(?:https?|ftp|file|data|javascript|mailto|tel):|\bwww\.|!?\[[^\]]*\]\()/i';

    private const MARKUP = '/<\s*\/?\s*[a-z!?][^>]*>/i';

    /**
     * The text with hidden characters removed, runs of blank lines collapsed,
     * and cut to $max characters.
     */
    public static function clean(string $text, int $max = 1000): string
    {
        $text = preg_replace(self::HIDDEN, '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).'…' : $text;
    }

    /**
     * clean() over every string in a nested structure - used on everything an
     * MCP tool hands back, so no tool has to remember to do it.
     */
    public static function cleanDeep(mixed $data, int $max = 1000): mixed
    {
        if (is_string($data)) {
            return self::clean($data, $max);
        }

        if (is_array($data)) {
            return array_map(fn ($value) => self::cleanDeep($value, $max), $data);
        }

        return $data;
    }

    /**
     * Why an AI may not write this text into a record, or null if it may. A
     * link, markup or hidden characters in a field that ends up on a quote is
     * either an attack or never needed; describing something in words always
     * works.
     */
    public static function problem(string $text): ?string
    {
        return match (true) {
            (bool) preg_match(self::HIDDEN, $text) => 'hidden or control characters',
            (bool) preg_match(self::LINK, $text) => 'a link',
            (bool) preg_match(self::MARKUP, $text) => 'HTML or markup',
            default => null,
        };
    }

    /**
     * The first problem() in any string inside a structure, with where it
     * was: ['notes' => 'a link'] style paths, or null if it is all clean.
     *
     * @return array{path: string, reason: string}|null
     */
    public static function firstProblem(mixed $data, string $path = ''): ?array
    {
        if (is_string($data)) {
            $reason = self::problem($data);

            return $reason ? ['path' => $path, 'reason' => $reason] : null;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $found = self::firstProblem($value, $path === '' ? (string) $key : "{$path}.{$key}");

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Things in a model-written message a person should look at before
     * sending it on: a link, or an email address other than the ones that
     * belong in it.
     *
     * @param  array<int, string>  $expectedEmails
     * @return array<int, string>
     */
    public static function warnings(string $text, array $expectedEmails = []): array
    {
        $warnings = [];

        if (preg_match(self::LINK, $text) || preg_match(self::MARKUP, $text)) {
            $warnings[] = 'It contains a link or markup you did not ask for - check where it points before sending.';
        }

        $expected = array_map('mb_strtolower', array_filter($expectedEmails));
        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $found);

        if (collect($found[0])->map(fn (string $address) => mb_strtolower($address))->diff($expected)->isNotEmpty()) {
            $warnings[] = 'It mentions an email address other than the customer\'s - check it.';
        }

        if (preg_match(self::HIDDEN, $text)) {
            $warnings[] = 'It contains hidden characters.';
        }

        return $warnings;
    }
}
