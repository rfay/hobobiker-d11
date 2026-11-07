<?php

/**
 * @file
 * Larger copies of the Flickr photos the posts show or link to, and their Flickr titles.
 *
 * The posts link to photos on Flickr (www.flickr.com/photos/USER/ID/) or show Flickr's small size (_m, 240 px)
 * copied into the files directory. For every Flickr photo id they refer to, this asks Flickr's oEmbed endpoint
 * (no API key) for the largest size it serves (1024 px) and, when the site has no copy at least that wide,
 * downloads it to public://flickr/ID_SECRET_b.jpg. It writes public://flickr/manifest.json: per id, the title,
 * the photo page, the local file and its size, for the photo filters (hobobiker_flickr).
 *
 * Adds files only; never changes or replaces an existing one. Rerunnable: ids already in the manifest are skipped
 * (--refresh asks Flickr again). Polite: one request at a time, 0.5 s apart.
 * Run: ddev drush php:script scripts/flickr-fetch.php [--refresh]
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;

$refresh = in_array('--refresh', $extra ?? [], TRUE);
$fs = \Drupal::service('file_system');
$dir = 'public://flickr';
$fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
$manifest_uri = "$dir/manifest.json";
$manifest = is_file($manifest_uri) ? json_decode(file_get_contents($manifest_uri), TRUE) : [];

// Every Flickr photo id the posts refer to: Flickr photo links, and images or photo tokens naming a file ID_...
$ids = [];
foreach (\Drupal::database()->query('SELECT body_value FROM {node__body}')->fetchCol() as $body) {
  preg_match_all('~flickr\.com/photos/[^/"\'\s]+/(\d{6,})~i', $body, $m);
  preg_match_all('~(?:files/|\[hobophoto:\s*)(\d{6,})(?:_[^"\'\s\]/]+)?\.(?:jpe?g|png|gif)~i', $body, $n);
  foreach (array_merge($m[1], $n[1]) as $id) {
    $ids[$id] = TRUE;
  }
}
ksort($ids);

// The widest local copy of each id already on the site.
$local = [];
foreach (glob($fs->realpath('public://') . '/*') as $path) {
  if (preg_match('/^(\d{6,})(?:_.+)?\.(jpe?g|png|gif)$/i', basename($path), $m) && ($size = @getimagesize($path))) {
    if (!isset($local[$m[1]]) || $size[0] > $local[$m[1]]['width']) {
      $local[$m[1]] = ['file' => basename($path), 'width' => $size[0], 'height' => $size[1]];
    }
  }
}

$base58 = function (string $id): string {
  $alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
  $out = '';
  for ($n = (int) $id; $n > 0; $n = intdiv($n, 58)) {
    $out = $alphabet[$n % 58] . $out;
  }
  return $out;
};

$client = \Drupal::httpClient();
$asked = $downloaded = $gone = 0;
foreach (array_keys($ids) as $id) {
  if (isset($manifest[$id]) && !$refresh) {
    continue;
  }
  usleep(500000);
  $asked++;
  try {
    $info = json_decode((string) $client->get('https://www.flickr.com/services/oembed/', [
      'query' => ['format' => 'json', 'maxwidth' => 2048, 'url' => 'https://flic.kr/p/' . $base58($id)],
      'headers' => ['User-Agent' => 'hobobiker.com migration'],
      'timeout' => 30,
    ])->getBody(), TRUE);
  }
  catch (\Exception $e) {
    // Deleted or private on Flickr: keep what the site has.
    $manifest[$id] = ['title' => '', 'page' => '', 'flickr' => FALSE] + ($local[$id] ?? ['file' => '', 'width' => 0, 'height' => 0]);
    $gone++;
    continue;
  }
  $entry = ['title' => trim((string) ($info['title'] ?? '')), 'page' => (string) ($info['web_page'] ?? ''), 'flickr' => TRUE];
  $have = $local[$id] ?? NULL;
  if (($info['type'] ?? '') === 'photo' && !empty($info['url']) && (int) $info['width'] > ($have['width'] ?? 0)) {
    $target = "$dir/" . basename(parse_url($info['url'], PHP_URL_PATH));
    if (!is_file($target)) {
      usleep(500000);
      $data = (string) $client->get($info['url'], ['headers' => ['User-Agent' => 'hobobiker.com migration'], 'timeout' => 60])->getBody();
      $fs->saveData($data, $target, FileExists::Error);
      $downloaded++;
    }
    $size = getimagesize($fs->realpath($target));
    $entry += ['file' => 'flickr/' . basename($target), 'width' => $size[0], 'height' => $size[1]];
  }
  else {
    $entry += $have ?? ['file' => '', 'width' => 0, 'height' => 0];
  }
  $manifest[$id] = $entry;
  // Saved as it goes, so an interrupted run resumes where it stopped.
  file_put_contents($manifest_uri, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}
ksort($manifest);
file_put_contents($manifest_uri, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$large = count(array_filter($manifest, fn($e) => $e['width'] >= 1000));
$titled = count(array_filter($manifest, fn($e) => $e['title'] !== ''));
printf("flickr ids referred to %d; asked flickr %d; downloaded %d; not on flickr any more %d; in manifest %d (at least 1000 px wide %d, with a flickr title %d)\n",
  count($ids), $asked, $downloaded, $gone, count($manifest), $large, $titled);
