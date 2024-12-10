<?php

namespace DetectionTests;

use Detection\Cache\Cache;
use Detection\Cache\CacheItem;
use Detection\Exception\MobileDetectException;
use Detection\MobileDetect;
use PHPUnit\Framework\TestCase;

final class MobileDetectWithCacheTest extends TestCase
{
    /**
     * @var MobileDetect
     */
    protected MobileDetect $detect;

    public function testFlattenHeaders()
    {
        $detect = new MobileDetect();
        $cacheKey = $detect->flattenHeaders([
            'HTTP_REQUEST_METHOD' => 'DELETE',
            'HTTP_USER_AGENT'     => 'Mozilla/5.0 iPhone;',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
        ]);

        $expectedString = "HTTP_REQUEST_METHOD: DELETE" . PHP_EOL .
        "HTTP_USER_AGENT: Mozilla/5.0 iPhone;" . PHP_EOL .
        "HTTP_ACCEPT_LANGUAGE: en-us,en;q=0.5";

        $this->assertEquals($expectedString, $cacheKey);
    }

    /**
     * @throws MobileDetectException
     */
    public function testDefaultCacheClassCreatesACacheRecord()
    {
        $detect = new MobileDetect();
        $detect->setUserAgent('Some iPhone user agent');
        $isMobile = $detect->isMobile();

        $this->assertTrue($isMobile);
        $this->assertEquals(1, $detect->getCache()->count());
        $this->assertSame(
            sha1("mobile:Some iPhone user agent:"),
            $detect->getCache()->getKeys()[0]
        );
    }

    /**
     * @throws MobileDetectException
     */
    public function testDefaultCacheClassCreatesASingleCacheRecordOnMultipleIsMobileCalls()
    {
        $detect = new MobileDetect();
        $detect->setUserAgent('Some iPhone user agent');
        $isMobile = $detect->isMobile();
        $this->assertTrue($isMobile);
        $this->assertEquals(1, $detect->getCache()->count());

        $isMobile = $detect->isMobile();
        $this->assertTrue($isMobile);
        $this->assertEquals(1, $detect->getCache()->count());

        $detect->isMobile();
        $detect->isMobile();
        $detect->isMobile();
        $this->assertEquals(1, $detect->getCache()->count());
    }

    /**
     * @throws MobileDetectException
     */
    public function testDefaultCacheClassCreatesMultipleCacheRecordsForAllCalls()
    {
        $detect = new MobileDetect();
        $detect->setUserAgent('iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5');

        $isMobile = $detect->isMobile();
        $isTablet = $detect->isTablet();
        $isMobile2 = $detect->is("mobile");
        $isTablet2 = $detect->is("tablet");

        $isIpad = $detect->isiPad();
        $isIpad2 = $detect->is("iPad");
        $isiOS = $detect->isiOS();
        $isiOS2 = $detect->is("iOS");

        $this->assertTrue($isMobile);
        $this->assertTrue($isTablet);
        $this->assertTrue($isMobile2);
        $this->assertTrue($isTablet2);
        $this->assertTrue($isIpad);
        $this->assertTrue($isIpad2);
        $this->assertTrue($isiOS);
        $this->assertTrue($isiOS2);

        $this->assertEquals(4, $detect->getCache()->count());
        $this->assertSame(
            sha1("mobile:iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5:"),
            $detect->getCache()->getKeys()[0]
        );
        $this->assertSame(
            sha1("tablet:iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5:"),
            $detect->getCache()->getKeys()[1]
        );
        $this->assertSame(
            sha1("iPad:iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5:"),
            $detect->getCache()->getKeys()[2]
        );
        $this->assertSame(
            sha1("iOS:iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5:"),
            $detect->getCache()->getKeys()[3]
        );
    }

    /**
     * @throws MobileDetectException
     */
    public function testCustomCacheWithInvalidFnThrowsException()
    {
        $this->expectException(MobileDetectException::class);
        $this->expectExceptionMessage('Cache problem in isMobile(): cacheKeyFn is not a function.');
        $cache = new Cache();

        $detect = new MobileDetect($cache, ['cacheKeyFn' => 'not a function']);
        $detect->setUserAgent('iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5');
        $detect->isMobile();
    }

    /**
     * @throws MobileDetectException
     */
    public function testCustomCacheForConsecutiveCalls()
    {
        $cache = new Cache();

        $detect = new MobileDetect($cache, ['cacheKeyFn' => fn ($key) => base64_encode($key)]);
        $detect->setUserAgent('iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5');

        $detect->isMobile();
        $this->assertCount(1, $cache->getKeys());

        $detect->isMobile();
        $this->assertCount(1, $cache->getKeys());
    }

    /**
     * @throws MobileDetectException
     */
    public function testGetCacheKeyIsUsedInConsecutiveCallsIfFoundIn()
    {
        $cache = $this->getMockBuilder(Cache::class)
            ->onlyMethods(["get", "set"])
            ->getMock();
        $cache->method('get')->withAnyParameters()->willReturn(new CacheItem('name', 'value'));
        $cache->method('set')->withAnyParameters()->willReturn(true);

        $cache->expects($spy = $this->exactly(2))->method('get');
        $cache->expects($spy = $this->never())->method('set');

        $detect = new MobileDetect($cache);
        $detect->setUserAgent('iPad; AppleWebKit/533.17.9 Version/5.0.2 Mobile/8C148 Safari/6533.18.5');

        $detect->isMobile();
        $detect->isMobile();
    }
}
