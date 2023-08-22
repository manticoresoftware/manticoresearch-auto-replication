<?php

namespace Tests;

use Core\Cache\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{

    private string $cacheStorageFile = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'index_hash.dat';

    /**
     * @test
     * @return void
     */

    public function storeSaveFileJsonFormat(): void
    {
        if (file_exists($this->cacheStorageFile)) {
            unlink($this->cacheStorageFile);
        }

        $key = 'abc';
        $value = 'def';
        $cache = new Cache();
        $cache->store($key, $value);

        $this->assertFileExists($this->cacheStorageFile);
        $this->assertSame(json_encode([$key => $value]), file_get_contents($this->cacheStorageFile));
    }

    /**
     * @test
     * @return void
     */

    public function constructReadFile(): void
    {
        $dummyValue = 'value';
        file_put_contents($this->cacheStorageFile, json_encode(['dummy_key' => $dummyValue]));

        $cache = new Cache();
        $this->assertSame($dummyValue, $cache->get('dummy_key'));
    }

    /**
     * @test
     * @return void
     */

    public function emptyCacheKeyReturnArray(): void
    {
        $cache = new Cache();
        $this->assertSame([], $cache->get("Dummy key"));
    }

    /**
     * @test
     * @return void
     */

    public function assertCacheKeyNames(): void
    {
        $keys = [
            'INDEX_HASH' => 'index_hash',
            'CHECKED_WORKERS' => 'checked_workers',
            'CHECKED_INDEXES' => 'checked_indexes',
        ];
        foreach ($keys as $constName => $expectedContent) {
            $this->assertSame($expectedContent, constant("Core\Cache\Cache::$constName"));
        }
    }
}


