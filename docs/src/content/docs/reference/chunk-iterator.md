---
title: ChunkIterator
description: Silarhi\CursorPagination\Iterator\ChunkIterator splits any iterable into arrays of a given size.
sidebar:
    order: 3
---

`Silarhi\CursorPagination\Iterator\ChunkIterator` is the `final readonly` class behind
`CursorPagination::getChunkResults()`. It works on any iterable, generators included, and reads it lazily.

```php
use Silarhi\CursorPagination\Iterator\ChunkIterator;

$chunks = new ChunkIterator(['a' => 1, 'b' => 2, 'c' => 3], 2);

foreach ($chunks as $chunk) {
    // ['a' => 1, 'b' => 2], then ['c' => 3]
}
```

```php
public function __construct(iterable $data, int $size)
```

- Yields arrays of `$size` items; the last one may be shorter, and nothing is yielded for an empty iterable.
- Keys are preserved: a key repeated by the iterable overwrites the previous item of the same chunk.
- Use a positive `$size`.
