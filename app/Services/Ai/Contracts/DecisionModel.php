<?php

namespace App\Services\Ai\Contracts;

/**
 * Seam for a System 1 decision model (Jev, Clef, ...): given an input and a
 * closed list of options, return the best option with a probability - no
 * generated text. Declared now, implemented later, so adding one is a driver
 * plus a call-site change rather than a refactor. Nothing uses it yet.
 *
 * It is a supplement to the main LLM, not a replacement: the intent is for
 * cheap closed-set decisions (which catalog item is this line? does this
 * need the LLM at all?) to be answered here and only escalate to a TextModel
 * when the probability is below config('ai.confidence_threshold').
 */
interface DecisionModel
{
    /**
     * @param  array<string, string>  $options  Option id => label.
     * @return array{choice: string, probability: float}
     */
    public function decide(string $input, string $question, array $options): array;
}
