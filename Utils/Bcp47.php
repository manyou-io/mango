<?php

declare(strict_types=1);

namespace Manyou\Mango\Utils;

use function array_filter;
use function implode;
use function in_array;
use function locale_compose;
use function locale_parse;
use function preg_match;
use function strtoupper;

class Bcp47
{
    private array $firstMatchesCache = [];

    private array $candidateLocalesCache = [];

    private function extractVariantsFromLocaleTags(array $locale): array
    {
        $variants = [];
        for ($i = 0; $i <= 14; $i++) {
            $key = 'variant' . $i;
            if (! isset($locale[$key])) {
                break;
            }

            $variants[$i] = $locale[$key];
        }

        return $variants;
    }

    private function convertVariantsToLocaleTags(array $variants): array
    {
        $locale = [];
        foreach ($variants as $key => $value) {
            $locale['variant' . $key] = $value;
        }

        return $locale;
    }

    private function getCandidateLocaleTags(array $locale): array
    {
        $language = $locale['language'];
        $script   = $locale['script'] ?? '';
        $region   = $locale['region'] ?? '';
        $variants = $this->extractVariantsFromLocaleTags($locale);

        // Special handling for Norwegian
        $isNorwegianBokmal  = false;
        $isNorwegianNynorsk = false;
        if ($language === 'no') {
            if ($region === 'NO' && $variants === ['NY']) {
                $variants           = [];
                $isNorwegianNynorsk = true;
            } else {
                $isNorwegianBokmal = true;
            }
        }

        if ($language === 'nb' || $isNorwegianBokmal) {
            $tmpList = $this->enumerateDefaultCandidates('nb', $script, $region, $variants);
            // Insert a locale replacing "nb" with "no" for every list entry
            $bokmalList = [];
            foreach ($tmpList as $l) {
                $bokmalList[] = $l;
                if (! $l['language']) {
                    break;
                }

                $l['language'] = 'no';
                $bokmalList[]  = $l;
            }

            return $bokmalList;
        }

        if ($language === 'nn' || $isNorwegianNynorsk) {
            // Insert no_NO_NY, no_NO, no after nn
            $nynorskList = $this->enumerateDefaultCandidates('nn', $script, $region, $variants);

            return [...$nynorskList, locale_parse('no_NO_NY'), locale_parse('no_NO'), locale_parse('no')];
        }

        // Special handling for Chinese
        if ($language === 'zh') {
            if (! $script && $region) {
                $script = match ($region) {
                    'TW', 'HK', 'MO' => 'Hant',
                    'CN', 'SG' => 'Hans',
                    default => $script,
                };
            } elseif ($script && ! $region) {
                $region = match ($script) {
                    'Hans' => 'CN',
                    'Hant' => 'TW',
                    default => $region,
                };
            }
        }

        return $this->enumerateDefaultCandidates($language, $script, $region, $variants);
    }

    private function enumerateDefaultCandidates(
        string $language = '',
        string $script = '',
        string $region = '',
        array $variants = [],
    ): array {
        $locales = [];

        foreach ($variants as $v) {
            $locales[] = ['language' => $language, 'script' => $script, 'region' => $region, ...$this->convertVariantsToLocaleTags([$v])];
        }

        if ($region) {
            $locales[] = ['language' => $language, 'script' => $script, 'region' => $region];
        }

        if ($script) {
            $locales[] = ['language' => $language, 'script' => $script, 'region' => ''];

            // With script, after truncating variant, region and script,
            // start over without script.
            foreach ($variants as $v) {
                $locales[] = ['language' => $language, 'script' => '', 'region' => $region, ...$this->convertVariantsToLocaleTags([$v])];
            }

            if ($region) {
                $locales[] = ['language' => $language, 'script' => '', 'region' => $region];
            }
        }

        if ($language) {
            $locales[] = ['language' => $language, 'script' => '', 'region' => ''];
        }

        return $locales;
    }

    public function getCandidateLocales(string $locale): array
    {
        if (isset($this->candidateLocalesCache[$locale])) {
            return $this->candidateLocalesCache[$locale];
        }

        $parsedLocale = locale_parse($locale);

        preg_match('/-u-rg-([a-z]{2})zzzz(?:-|$)/i', $locale, $matches);
        if (isset($matches[1])) {
            $parsedLocale['region'] ??= strtoupper($matches[1]);
        }

        $candidateLocales = $this->getCandidateLocaleTags($parsedLocale);

        // Remove empty tags and compose the locale string
        foreach ($candidateLocales as $key => $tags) {
            if ([] === $tags = array_filter($tags, static fn ($v) => $v !== '')) {
                unset($candidateLocales[$key]);
                continue;
            }

            $candidateLocales[$key] = locale_compose($tags);
        }

        return $this->candidateLocalesCache[$locale] = $candidateLocales;
    }

    private function getFirstMatches(array $availableLocales): array
    {
        $cacheKey = implode("\0", $availableLocales);

        if (isset($this->firstMatchesCache[$cacheKey])) {
            return $this->firstMatchesCache[$cacheKey];
        }

        $firstMatches = [];

        foreach ($availableLocales as $locale) {
            $candidates = $this->getCandidateLocales($locale);
            foreach ($candidates as $candidate) {
                $firstMatches[$candidate] ??= $locale;
            }
        }

        return $this->firstMatchesCache[$cacheKey] = $firstMatches;
    }

    public function getBestMatch(string $clientLocale, array $availableLocales, ?string $defaultLocale = null): ?string
    {
        if ($availableLocales === []) {
            return null;
        }

        $candidates = $this->getCandidateLocales($clientLocale);

        $firstMatches = $this->getFirstMatches($availableLocales);

        foreach ($candidates as $candidate) {
            if (isset($firstMatches[$candidate])) {
                return $firstMatches[$candidate];
            }
        }

        return in_array($defaultLocale, $availableLocales, true) ? $defaultLocale : $availableLocales[0];
    }
}
