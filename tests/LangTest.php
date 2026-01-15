<?php
use PHPUnit\Framework\TestCase;
use GetAnyMessage\Locales\Lang;

class LangTest extends TestCase
{
    private string $testDataDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataDir = __DIR__ . '/data_test';
        if (!file_exists($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteDir($this->testDataDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function getLangInstance(): Lang
    {
        $contextMock = new class($this->testDataDir) {
            public string $dataPath;
            public function __construct(string $path) { $this->dataPath = $path; }
        };

        return new class($contextMock) extends Lang {
            private object $mockContext;
            public function __construct(object $context)
            {
                parent::__construct($context);
                $this->mockContext = $context;
            }
            public function getUserLang(int $senderId): string
            {
                $path = $this->mockContext->dataPath . "/$senderId/lang.txt";
                if (file_exists($path)) {
                    return trim(file_get_contents($path));
                }
                return 'en';
            }
            public function setUserLang(int $senderId, string $lang): bool
            {
                $dir = $this->mockContext->dataPath . "/$senderId";
                if (!file_exists($dir)) mkdir($dir, 0777, true);
                return file_put_contents("$dir/lang.txt", $lang) !== false;
            }
        };
    }

    public function testDefaultLangIsEnglish()
    {
        $lang = $this->getLangInstance();
        $this->assertEquals('en', $lang->getUserLang(999999));
    }

    public function testSetAndGetUserLang()
    {
        $lang = $this->getLangInstance();
        $senderId = 999999;
        $this->assertTrue($lang->setUserLang($senderId, 'fr'));
        $this->assertEquals('fr', $lang->getUserLang($senderId));
    }

    public function testLoadTranslationsFallsBackToEnglish()
    {
        $lang = $this->getLangInstance();
        $translations = $lang->loadTranslations('nonexistent');
        $this->assertArrayHasKey('language_name', $translations);
    }

    public function testGetAvailableLanguages()
    {
        $lang = $this->getLangInstance();
        $langs = $lang->getAvailableLanguages();
        $this->assertIsArray($langs);
        $this->assertNotEmpty($langs);
    }

    public function testGetLanguageButtons()
    {
        $lang = $this->getLangInstance();
        $buttons = $lang->getLanguageButtons();
        $this->assertArrayHasKey('inline_keyboard', $buttons);
    }
}
