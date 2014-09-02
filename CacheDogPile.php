<?php
/**
 * @author      Alexander Stepanenko <alex.stepanenko@gmail.com>
 * @license     http://mit-license.org/
 */

class CacheDogPile extends CComponent
{

    public $storage;
    public $lockKeyPrefix  = 'lock_';
    public $backupSuffix   = '_backup';
    public $waitTime       = 3000;
    public $waitInterval   = 200;
    public $backupInterval = 10;

    public function setStorage(CCache $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    protected function generateLockKey($key)
    {
        return $this->lockKeyPrefix.$key;
    }

    protected function generateBackupKey($key)
    {
        return $key.$this->backupSuffix;
    }

    public function lock($key, $expire = 5)
    {
        return $this->storage->add($this->generateLockKey($key), '1', $expire);
    }

    public function unlock($key)
    {
        $this->storage->delete($this->generateLockKey($key));
        return true;
    }

    public function isLocked($key)
    {
        return ($this->storage->get($this->generateLockKey($key)) !== false) ? true : false;
    }

    public function waitForUnlock($key)
    {
        $sleepTime = $this->waitInterval * 1000;
        $totalSleeped = 0;
        while ($totalSleeped < $this->waitTime && $this->isLocked($key)) {
            $totalSleeped += $this->waitInterval;
            usleep($sleepTime);
        }

        if ($totalSleeped < $this->waitTime) {
            return true;
        }
        return false;
    }

    public function getHeavy($key, $callback, $expire = 3600, $generationTime = 10)
    {
        if (!($result = $this->storage->get($key))) {
            $this->storage->set($key, $this->storage->get($this->generateBackupKey($key)), $expire);
            $result = $callback();
            $this->storage->set($key, $result, $expire);
            $this->storage->set($this->generateBackupKey($key), $result, $expire + $generationTime + $this->backupInterval);
        }
        return $result;
    }

}