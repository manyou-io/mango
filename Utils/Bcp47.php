<?php

declare(strict_types=1);

namespace Manyou\Mango\Utils;

use function array_filter;
use function array_key_exists;
use function array_splice;
use function count;
use function locale_compose;
use function locale_parse;
use function preg_match;
use function strlen;
use function strrpos;
use function strtoupper;
use function substr;

class Bcp47
{
    public static function createObject($base)
    {
        $language = $base['language'];
        $script   = $base['script'] ?? '';
        $region   = $base['region'] ?? '';
        $variant  = $base['variant'] ?? '';

        // Special handling for Norwegian
        $isNorwegianBokmal  = false;
        $isNorwegianNynorsk = false;
        if ($language === 'no') {
            if ($region === 'NO' && $variant === 'NY') {
                $variant            = '';
                $isNorwegianNynorsk = true;
            } else {
                $isNorwegianBokmal = true;
            }
        }

        if ($language === 'nb' || $isNorwegianBokmal) {
            $tmpList = self::getDefaultList('nb', $script, $region, $variant);
            // Insert a locale replacing "nb" with "no" for every list entry
            $bokmalList = [];
            foreach ($tmpList as $l) {
                $bokmalList[] = $l;
                if (strlen($l['language']) === 0) {
                    break;
                }

                $l['language'] = 'no';
                $bokmalList[]  = $l;
            }

            return $bokmalList;
        }

        if ($language === 'nn' || $isNorwegianNynorsk) {
            // Insert no_NO_NY, no_NO, no after nn
            $nynorskList = self::getDefaultList('nn', $script, $region, $variant);
            array_splice($nynorskList, count($nynorskList), 0, [locale_parse('no_NO_NY'), locale_parse('no_NO'), locale_parse('no')]);

            return $nynorskList;
        }

        // Special handling for Chinese

        if ($language === 'zh') {
            if (strlen($script) === 0 && strlen($region) > 0) {
                // Supply script for users who want to use zh_Hans/zh_Hant
                // as bundle names (recommended for PHP7+)
                if ($region === 'TW' || $region === 'HK' || $region === 'MO') {
                    $script = 'Hant';
                } elseif ($region === 'CN' || $region === 'SG') {
                    $script = 'Hans';
                }
            } elseif (strlen($script) > 0 && strlen($region) === 0) {
                // Supply region(country) for users who still package Chinese
                // bundles using old convension.
                if ($script === 'Hans') {
                    $region = 'CN';
                } elseif ($script === 'Hant') {
                    $region = 'TW';
                }
            }
        }

        return self::getDefaultList($language, $script, $region, $variant);
    }

    private function getDefaultList($language, $script, $region, $variant)
    {
        $variants = null;

        if (strlen($variant) > 0) {
            $variants = [];
            $idx      = strlen($variant);
            while ($idx !== -1) {
                $variants[] = substr($variant, 0, $idx);
                $idx        = strrpos($variant, '_', --$idx);
            }
        }

        $list = [];

        if ($variants !== null) {
            foreach ($variants as $v) {
                $list[] = ['language' => $language, 'script' => $script, 'region' => $region, 'variant' => $v];
            }
        }

        if (strlen($region) > 0) {
            $list[] = ['language' => $language, 'script' => $script, 'region' => $region, 'variant' => ''];
        }

        if (strlen($script) > 0) {
            $list[] = ['language' => $language, 'script' => $script, 'region' => '', 'variant' => ''];

            // With script, after truncating variant, region and script,
            // start over without script.
            if ($variants !== null) {
                foreach ($variants as $v) {
                    $list[] = ['language' => $language, 'script' => '', 'region' => $region, 'variant' => $v];
                }
            }

            if (strlen($region) > 0) {
                $list[] = ['language' => $language, 'script' => '', 'region' => $region, 'variant' => ''];
            }
        }

        if (strlen($language) > 0) {
            $list[] = ['language' => $language, 'script' => '', 'region' => '', 'variant' => ''];
        }

        // Add root locale at the end
        $list[] = ['language' => '', 'script' => '', 'region' => '', 'variant' => ''];

        return $list;
    }

    public function getCandidateLocales($locale)
    {
        $baseLocale = locale_parse($locale);

        preg_match('/-u-rg-([a-z]{2})zzzz(?:-|$)/i', $locale, $matches);
        if (isset($matches[1])) {
            $baseLocale['region'] ??= strtoupper($matches[1]);
        }

        $candidateLocales = self::createObject($baseLocale);

        // Convert the associative array to string and remove empty fields
        foreach ($candidateLocales as $key => $value) {
            if ([] === $value = array_filter($value, static fn ($v) => $v !== '')) {
                unset($candidateLocales[$key]);
                continue;
            }

            $candidateLocales[$key] = locale_compose($value);
        }

        return $candidateLocales;
    }

    public function getBestMatch($userLocale, $availableLocales, $defaultLocale = null)
    {
        if ($availableLocales === []) {
            return $defaultLocale;
        }

        $userCandidates = self::getCandidateLocales($userLocale);

        $firstMatches = [];

        foreach ($availableLocales as $language) {
            $languageCandidates = self::getCandidateLocales($language);
            foreach ($languageCandidates as $languageCandidate) {
                $firstMatches[$languageCandidate] ??= $language;
            }
        }

        foreach ($userCandidates as $userCandidate) {
            if (array_key_exists($userCandidate, $firstMatches)) {
                return $firstMatches[$userCandidate];
            }
        }

        return $defaultLocale ?? $availableLocales[0];
    }
}
