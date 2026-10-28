<?php

/**
 * @file
 * Development copy only: no page, dynamic page or render caching, and templates recompiled when they change.
 *
 * While the rebuild is being made and tested, a cached page could hide a fix or a regression (Part 2 found the
 * Coder hostname baked into the original's render cache). The configuration keeps page_cache and
 * dynamic_page_cache enabled, as a production site would have them; only this file turns the caches off.
 */

$settings['container_yamls'][] = __DIR__ . '/development.services.yml';
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
