<?php

namespace SilverStripe\Core\Tests\Manifest;

use SebastianBergmann\Version;
use Composer\Semver\VersionParser;
use SilverStripe\Dev\SapphireTest;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\VersionProvider;

class VersionProviderTest extends SapphireTest
{
    /**
     * @var VersionProvider
     */
    protected $provider;

    protected function setup(): void
    {
        parent::setup();
        $this->clearCache();
    }

    public function getProvider()
    {
        $provider = Injector::inst()->get(VersionProvider::class);
        return $provider;
    }

    public function testGetModules()
    {
        Config::modify()->set(VersionProvider::class, 'modules', [
            'silverstripe/mypackage' => 'My Package',
            'silverstripe/somepackage' => 'Some Package',
            'silverstripe/another' => 'Another',
            'cwp/cwp-something' => 'CWP something',
        ]);
        $result = $this->getProvider()->getModules();
        $this->assertArrayHasKey('silverstripe/mypackage', $result);
        $this->assertArrayHasKey('silverstripe/somepackage', $result);
        $this->assertArrayHasKey('silverstripe/another', $result);
        $this->assertArrayHasKey('cwp/cwp-something', $result);
    }

    public function testGetModulesEmpty()
    {
        Config::modify()->set(VersionProvider::class, 'modules', []);
        $this->assertEquals(
            ['silverstripe/framework' => 'Framework'],
            $this->getProvider()->getModules()
        );
    }

    public function testGetModulesNone()
    {
        Config::modify()->remove(VersionProvider::class, 'modules');
        $this->assertEquals(
            ['silverstripe/framework' => 'Framework'],
            $this->getProvider()->getModules()
        );
    }

    public function testGetModuleVersionFromComposer()
    {
        Config::modify()->set(VersionProvider::class, 'modules', [
            'silverstripe/config' => 'Config',
            'silverstripe/framework' => 'Framework',
        ]);

        $result = $this->getProvider()->getModules(['silverstripe/framework']);
        $this->assertArrayHasKey('silverstripe/framework', $result);
        $this->assertNotEmpty($result['silverstripe/framework']);
    }

    public function testGetVersion()
    {
        Config::modify()->set(VersionProvider::class, 'modules', [
            'silverstripe/config' => 'Config',
            'silverstripe/framework' => 'Framework'
        ]);
        $result = $this->getProvider()->getVersion();
        $this->assertStringNotContainsString('Config: ', $result);
        $this->assertStringContainsString('Framework: ', $result);
        $this->assertStringNotContainsString(', ', $result);
    }

    public function testGetModuleVersion()
    {
        if (!class_exists(VersionParser::class)) {
            $this->markTestSkipped('This test requires composer/semver to be installed');
        }
        $provider = $this->getProvider();
        Config::modify()->set(VersionProvider::class, 'modules', [
            'silverstripe/framework' => 'Framework',
        ]);
        $moduleVersion = $provider->getModuleVersion('silverstripe/framework');
        $parser = new VersionParser();
        $this->assertIsString($parser->normalize($moduleVersion), "Expected a valid semver but got $moduleVersion");
        $result = $provider->getVersion();
        $this->assertStringNotContainsString('Framework: 1.2.3', $result);
    }

    public function testGetModuleVersionWhenPackageMayNotBeInstalled()
    {
        if (!class_exists(VersionParser::class)) {
            $this->markTestSkipped('This test requires composer/semver to be installed');
        }
        $provider = $this->getProvider();
        // VersionProvider::getModuleVersion() will loop over the modules defined in the "modules" config value, which
        // may sometimes include packages that are optional (e.g. recipe-core). This tests that the version can still
        // be found even if non-existent modules are encountered
        Config::modify()->set(VersionProvider::class, 'modules', [
            'this/module/cannot/possibly/exist' => 'Oopsies',
            'silverstripe/framework' => 'Framework',
            'silverstripe/another-module-that-does-not-exist' => 'Sapphire',
        ]);
        $moduleVersion = $provider->getModuleVersion('silverstripe/framework');
        $parser = new VersionParser();
        $this->assertIsString($parser->normalize($moduleVersion), "Expected a valid semver but got $moduleVersion");
        $result = $provider->getVersion();
        $this->assertStringNotContainsString('Framework: 1.2.3', $result);
    }

    private function clearCache()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.VersionProvider');
        $cache->clear();
    }
}
