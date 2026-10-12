---
title: Installation
description: Install silarhi/cursor-pagination in a project using Doctrine ORM.
sidebar:
    order: 1
---

## Requirements

| Dependency   | Version |
| ------------ | ------- |
| PHP          | 8.2+    |
| doctrine/orm | ^3.0    |

PHP 8.0 and 8.1, or doctrine/orm 2, need the 1.x releases.

## Installation

```bash
composer require silarhi/cursor-pagination
```

The library is plain PHP: there is no bundle to register and no configuration. It works on any Doctrine ORM
`QueryBuilder`, in a Symfony application or not.

Next: the [quick start](/getting-started/quick-start/).
