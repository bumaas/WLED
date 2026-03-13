<?php /** @noinspection AutoloadingIssuesInspection */

declare(strict_types=1);

trait ModuleDebugTrait
{
    // These categories are always logged to keep lifecycle and control-flow traceable.
    private const array BASIC_DEBUG_CATEGORIES = [
        'ApplyChanges',
        'Config',
        'Discovery',
        'MessageSink',
        'RequestAction',
        'REST',
        'UpdateCache'
    ];

    // $always=true forces logging regardless of category and EnableExpertDebug setting.
    // EnableExpertDebug=false logs only BASIC_DEBUG_CATEGORIES to keep logs concise.
    private function debugExpert(string $category, string $message, array $context = [], bool $always = false): void
    {
        $expertDebugEnabled = @($this->ReadPropertyBoolean('EnableExpertDebug'));
        if (!$always && !$expertDebugEnabled) {
            if (!in_array($category, self::BASIC_DEBUG_CATEGORIES, true)) {
                return;
            }
        }

        $suffix = '';
        if (!empty($context)) {
            $suffix = ' | ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->SendDebug($category, $message . $suffix, 0);
    }
}
