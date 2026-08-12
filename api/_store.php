<?php
/**
 * Locked, atomic JSON store helpers.
 *
 * Every endpoint here keeps state in flat JSON files under ../data/. The
 * original pattern was:
 *
 *     $d = json_decode(file_get_contents($f), true);   // read
 *     $d['users'][$u]['coins'] += $n;                  // modify
 *     file_put_contents($f, json_encode($d));          // write
 *
 * with no locking anywhere across 39 endpoints. That has two distinct failure
 * modes on a site where several players act at once:
 *
 *   1. Lost updates. Two requests read the same snapshot, each applies its own
 *      change, and whichever writes second silently discards the other's. This
 *      is how coins and inventory items "disappear".
 *
 *   2. Corruption. file_put_contents truncates before writing, so a request
 *      that dies mid-write leaves a half-written file. json_decode then returns
 *      null, the `?: ['users' => []]` fallback kicks in, and the next write
 *      persists an EMPTY user database over the real one. users.json is 2.9 MB
 *      and holds every account.
 *
 * store_mutate() fixes both: it holds an exclusive lock across the whole
 * read-modify-write, so concurrent callers queue instead of racing, and it
 * writes through a single handle it already owns rather than truncating a path
 * another process might be reading.
 *
 * Use store_mutate() for anything that CHANGES state. store_read() is only for
 * read-only paths.
 */

/**
 * Read a JSON file under a shared lock. Returns $default if missing/unparseable.
 */
function store_read(string $file, $default = null) {
    if ($default === null) {
        $default = [];
    }
    if (!file_exists($file)) {
        return $default;
    }
    $fh = @fopen($file, 'rb');
    if ($fh === false) {
        return $default;
    }
    try {
        if (!flock($fh, LOCK_SH)) {
            return $default;
        }
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
    if ($raw === '' || $raw === false) {
        return $default;
    }
    $data = json_decode($raw, true);
    return $data === null ? $default : $data;
}

/**
 * Read-modify-write under a single exclusive lock.
 *
 * $mutator receives the decoded data by reference and may return a value to be
 * passed back to the caller. The (possibly modified) data is written back
 * unless the mutator returns false, which aborts the write and leaves the file
 * untouched -- use that for validation failures.
 *
 * Returns whatever the mutator returned, or null if the file could not be
 * locked. A refusal to write is NOT an error the caller can ignore silently:
 * check the return value.
 */
function store_mutate(string $file, callable $mutator, $default = null) {
    if ($default === null) {
        $default = [];
    }
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    // 'c+' creates the file if absent and opens read/write WITHOUT truncating,
    // so the lock is acquired before any existing content can be destroyed.
    $fh = @fopen($file, 'c+b');
    if ($fh === false) {
        return null;
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            return null;
        }

        $raw = stream_get_contents($fh);
        if ($raw === '' || $raw === false) {
            $data = $default;
        } else {
            $decoded = json_decode($raw, true);
            if ($decoded === null) {
                // Refuse to operate on a corrupt file rather than overwriting
                // it with the empty default, which is what the old fallback did.
                return null;
            }
            $data = $decoded;
        }

        $result = $mutator($data);
        if ($result === false) {
            return false;
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return null;
        }

        rewind($fh);
        if (ftruncate($fh, 0) === false) {
            return null;
        }
        if (fwrite($fh, $encoded) === false) {
            return null;
        }
        fflush($fh);

        return $result;
    } finally {
        @flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Overwrite a JSON file wholesale, atomically, under an exclusive lock.
 * Prefer store_mutate() -- this is only for callers that legitimately replace
 * the entire document and do not depend on its previous contents.
 */
function store_write(string $file, $data): bool {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $encoded = json_encode($data, JSON_PRETTY_PRINT);
    if ($encoded === false) {
        return false;
    }
    $fh = @fopen($file, 'c+b');
    if ($fh === false) {
        return false;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return false;
        }
        rewind($fh);
        if (ftruncate($fh, 0) === false) {
            return false;
        }
        $ok = fwrite($fh, $encoded) !== false;
        fflush($fh);
        return $ok;
    } finally {
        @flock($fh, LOCK_UN);
        fclose($fh);
    }
}
