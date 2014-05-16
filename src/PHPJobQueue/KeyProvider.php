<?php

namespace PHPJobQueue;

class KeyProvider {

    protected $shmUIDBase;
    protected $shmUIDInc = 0;
    const SHM_KEY_SPACE = 100000;
    const SHM_KEY_COLLISION_MAX = 60;
    const SHM_KEY_ALLOCATION_CHECK_JUMP = 3;

    function __construct() {
        $this->updateBaseIDFromPID();
    }

    public function updateBaseIDFromPID() {
        $this->shmUIDBase = getmypid() * static::SHM_KEY_SPACE;
    }

    public function getUniqueKey() {
        /* As many instances of KeyProvider may be running within the same OS, we need to minimise the chance
         * of keys being generated twice between processes. Key range for each PID is set as SHM_KEY_SPACE.
         * We add an increment each call to return the latest key
         */

        // If key to try is outside of process range, reset to base to pick up recycled keys
        if ($this->shmUIDInc >= static::SHM_KEY_SPACE) { $this->shmUIDInc = 0; }

        $unique = false;
        $potentialKey = -1;
        $collisionCount = 0;
        while(!$unique) {
            $potentialKey = $this->shmUIDBase + $this->shmUIDInc++;

            // Reset to base if key increments over range
            if ($this->shmUIDInc >= static::SHM_KEY_SPACE) { $this->shmUIDInc = 0; }

            $checkId = @shmop_open($potentialKey, "a", 0, 0);
            if (!empty($checkId)) {
                // Memory is already allocated
                shmop_close($checkId);
                $this->shmUIDInc += static::SHM_KEY_ALLOCATION_CHECK_JUMP;

                $collisionCount++;
                if ($collisionCount > static::SHM_KEY_COLLISION_MAX) {
                    $this->logger->log("Warning: Unable to generate a working shared memory key within range " . $this->shmUIDBase . " - " . $this->shmUIDBase + static::SHM_KEY_SPACE);
                    return false;
                }
            } else {
                $unique = true;
            }
        }
        return $potentialKey;
    }

    public function getMultipleUniqueKeys($count) {
        $keys = [];
        for ($i=0; $i<$count; $i++) {
            $newKey = $this->getUniqueKey();
            if ($newKey) {
                $keys[] = $newKey;
            } else {
                $this->logger->log("Warning: Multiple unique key generation failed for key number " . $i+1);
                return false;
            }
        }
        if (count($keys) == $count) { return $keys; }
        else {
            $this->logger->log("Warning: Multiple unique key generation failed");
            return false;
        }
    }

}