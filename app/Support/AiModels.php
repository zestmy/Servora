<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Which OpenRouter model every AI feature uses — the only place that says.
 *
 * Most features pin their model, because the one chosen under Settings ›
 * API Keys has at times been a slow reasoning model (deepseek/deepseek-r1)
 * that times long prompts out or answers with empty content. Only the
 * features marked `configured` follow that setting. Settings › API Keys
 * shows this table, so the page cannot disagree with what the code sends.
 *
 * A new AI call gets a row here and reads its model through for().
 * AiModelsTest fails on a model name written anywhere else in app/.
 */
final class AiModels
{
    public const SONNET = 'anthropic/claude-sonnet-4';
    public const GEMINI_FLASH = 'google/gemini-2.5-flash';

    /** A configured feature with nothing chosen in Settings uses this. */
    public const FALLBACK = self::SONNET;

    /**
     * key => [feature, where it is in the product, model | 'configured', why]
     */
    public const FEATURES = [
        'invoice_scan'       => ['Invoice & delivery order capture', 'Purchasing › AI capture', self::SONNET, 'Reads photos; needs a vision model'],
        'document_scan'      => ['Supplier price list scan', 'Ingredients › Scan document', self::GEMINI_FLASH, 'Fast and cheap on long PDFs'],
        'ingredient_pdf'     => ['Ingredient import from PDF', 'Ingredients › Import', self::GEMINI_FLASH, 'Fast and cheap on long PDFs'],
        'ingredient_mapping' => ['Ingredient import: column & UOM matching', 'Ingredients › Import', 'configured', ''],
        'prep_detection'     => ['Ingredient import: prep item detection', 'Ingredients › Import', 'configured', ''],
        'recipe_extract'     => ['Recipe import: reading the file', 'Recipes › Smart import', self::GEMINI_FLASH, 'Fast and cheap on long PDFs'],
        'recipe_mapping'     => ['Recipe import: ingredient matching', 'Recipes › Smart import', 'configured', ''],
        'vision'             => ['Z-report reading & SOP method suggestions', 'Sales, Recipes', self::SONNET, 'Reads photos; needs a vision model'],
        'analytics'          => ['AI Analysis & WIP review insights', 'Reports › AI Analysis', self::SONNET, 'Large prompts time out on reasoning models'],
        'report_insights'    => ['Report insights', 'Reports', 'configured', ''],
        'pos_departments'    => ['POS department matching', 'Sales › POS Sync', 'configured', ''],
        'duplicates'         => ['Duplicate product merging', 'Ingredients › Duplicates', self::SONNET, 'Structured output must be reliable'],
        'holidays'           => ['Public holiday generator', 'Settings › Public holidays', self::SONNET, 'Structured output must be reliable'],
        'quiz'               => ['Quiz generator', 'Learn SOP', self::SONNET, 'Reasoning models time out on long SOPs'],
        'translation'        => ['Country page translation', 'Admin › Country Pages', self::SONNET, 'Reasoning models returned empty answers'],
    ];

    /** The model a feature sends. */
    public static function for(string $feature): string
    {
        $model = self::FEATURES[$feature][2] ?? throw new \InvalidArgumentException("Unknown AI feature [{$feature}].");

        return $model === 'configured' ? self::configured() : $model;
    }

    /** The model chosen under Settings › API Keys, or the fallback. */
    public static function configured(): string
    {
        return (string) (AppSetting::get('openrouter_model') ?: self::FALLBACK);
    }
}
