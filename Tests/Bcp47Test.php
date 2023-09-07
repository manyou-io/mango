<?php

namespace Mango\Tests;

use PHPUnit\Framework\TestCase;
use Mango\Utils\Bcp47;

class Bcp47Test extends TestCase
{
    /**
     * @dataProvider localeMatchingProvider
     */
    public function testGetBestMatch($availableLocales, $tests): void
    {
        $bcp47 = new Bcp47();
        foreach ($tests as [$clientLocale, $expected]) {
            $this->assertSame($expected, $bcp47->getBestMatch($clientLocale, $availableLocales));
        }
    }

    public function localeMatchingProvider(): iterable
    {
        yield [
            ['en', 'zh'],
            [
                ['en', 'en'],
                ['en-US', 'en'],
                ['en-GB', 'en'],
                ['en-CA', 'en'],
                ['zh', 'zh'],
                ['zh-SG', 'zh'],
                ['zh-CN', 'zh'],
                ['zh-Hant-SG', 'zh'],
                ['zh-Hant', 'zh'],
                ['zh-HK', 'zh'],
                ['zh-Hans-HK', 'zh'],
                ['fr-FR', 'en'],
                ['zh-u-rg-cnzzzz', 'zh'],
            ],
        ];

        yield [
            ['en_US', 'zh_TW', 'zh_CN'],
            [
                ['en', 'en_US'],
                ['en-US', 'en_US'],
                ['en-GB', 'en_US'],
                ['en-CA', 'en_US'],
                ['zh', 'zh_TW'],
                ['zh-SG', 'zh_CN'],
                ['zh-Hant-SG', 'zh_TW'],
                ['zh-Hant', 'zh_TW'],
                ['zh-HK', 'zh_TW'],
                ['zh-Hans-HK', 'zh_CN'],
                ['fr-FR', 'en_US'],
                ['zh-u-rg-cnzzzz', 'zh_CN'],
                ['zh-u-rg-twzzzz', 'zh_TW'],
            ],
        ];

        yield [
            ['en_GB', 'zh_Hant', 'zh_Hans', 'en_US'],
            [
                ['en', 'en_GB'],
                ['en-US', 'en_US'],
                ['en-GB', 'en_GB'],
                ['en-CA', 'en_GB'],
                ['zh-CN', 'zh_Hans'],
                ['zh-TW', 'zh_Hant'],
                ['zh-MO', 'zh_Hant'],
                ['zh-HK', 'zh_Hant'],
                ['zh-SG', 'zh_Hans'],
                ['zh-Hant-SG', 'zh_Hant'],
                ['zh', 'zh_Hant'],
                ['fr-FR', 'en_GB'],
                ['zh-u-rg-cnzzzz', 'zh_Hans'],
                ['zh-u-rg-twzzzz', 'zh_Hant'],
                ['en-GB-u-rg-uszzzz', 'en_GB'],
                ['en-u-rg-uszzzz-co-phonebk', 'en_US'],
            ],
        ];
    }
}
