<?php

/**
 * @file
 * Imports the content of the Drupal 6 copy of hobobiker.com into this site.
 *
 * Source: a COPY of the original's sanitized database, in the schema `d6` (see AGENTS.md). Rerunnable: it first
 * removes all imported content, then reloads it with the original ids (uid, tid, fid, nid, cid), so node/N,
 * comment links and aliases keep working. Current revisions only (node.vid = node_revisions.vid).
 *
 * Dropped, by the owner's decision (declared in expected-differences.json): aliases of deleted nodes, the comment
 * on a deleted node, file records whose file is gone from disk. Each is counted below.
 *
 * Run: ddev drush php:script scripts/20-import.php
 */

use Drupal\comment\Entity\Comment;
use Drupal\Core\Database\Database;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

Database::addConnectionInfo('d6', 'default', ['database' => 'd6'] + Database::getConnectionInfo('default')['default']);
$src = Database::getConnection('default', 'd6');
$q = fn(string $sql, array $args = []) => $src->query($sql, $args)->fetchAll(\PDO::FETCH_ASSOC);
$log = fn($m) => print($m . "\n");
$etm = \Drupal::entityTypeManager();

// Drupal 6 format id => text format (10-site-shell.php). 0 is the site default, Filtered HTML.
$formats = [0 => 'filtered_html', 1 => 'filtered_html', 2 => 'trip_tables', 3 => 'full_html_no_restrictions', 5 => 'html_with_line_breaks',
  6 => 'wiki', 7 => 'html_full_with_gmap', 8 => 'full_html_with_email_obfuscation', 9 => 'tripinfo_google_map',
  10 => 'flickr_full_html_linebreaks', 11 => 'flickr_full_html'];
$fmt = fn($f) => $formats[(int) $f] ?? throw new \RuntimeException("Unknown Drupal 6 format $f");
$vocabField = [];
foreach ($q('SELECT vid, name FROM vocabulary') as $v) {
  $vocabField[$v['vid']] = 'field_' . strtolower(preg_replace('/\W+/', '_', $v['name']));
}

// 1. Remove the previous import (children before parents). File records only: their bytes are not deleted,
// because the copy step owns them (deleting a File entity would also delete its file).
foreach (['comment', 'node', 'taxonomy_term', 'path_alias'] as $type) {
  $s = $etm->getStorage($type);
  foreach (array_chunk($s->getQuery()->accessCheck(FALSE)->execute(), 100) as $ids) {
    $s->delete($s->loadMultiple($ids));
  }
}
\Drupal::database()->delete('file_usage')->execute();
\Drupal::database()->delete('file_managed')->execute();
$etm->getStorage('file')->resetCache();
foreach ($etm->getStorage('user')->loadMultiple() as $u) {
  if ($u->id() > 1) {
    $u->delete();
  }
}

// 2. Users. uid 1 here is the installed administrator: it takes the original name and keeps its own password.
// Emails: the sanitized snapshot's user+UID@localhost is not a valid address for Drupal 11; the same anonymous form
// at example.invalid is (owner, 2026-10-27). Time zones: the original's offsets become the site's zone.
$users = $q('SELECT uid, name, mail, created, access, login, status FROM users WHERE uid > 0 ORDER BY uid');
foreach ($users as $u) {
  if ($u['uid'] == 1) {
    User::load(1)->setUsername($u['name'])->save();
    continue;
  }
  User::create(['uid' => $u['uid'], 'name' => $u['name'], 'mail' => "user+{$u['uid']}@example.invalid", 'status' => $u['status'],
    'timezone' => 'America/Denver',
    'created' => $u['created'], 'access' => $u['access'], 'login' => $u['login']])->save();
}
$log(count($users) . ' users');

// 3. Terms, then their parents (Drupal 6 keeps the hierarchy in term_hierarchy).
$vocabId = [];
foreach ($q('SELECT vid, name FROM vocabulary') as $v) {
  $vocabId[$v['vid']] = strtolower(preg_replace('/\W+/', '_', $v['name']));
}
$terms = $q('SELECT tid, vid, name, description, weight FROM term_data ORDER BY tid');
foreach ($terms as $t) {
  Term::create(['tid' => $t['tid'], 'vid' => $vocabId[$t['vid']], 'name' => $t['name'], 'weight' => $t['weight'],
    'description' => ['value' => $t['description'], 'format' => 'filtered_html']])->save();
}
$parents = $q('SELECT tid, parent FROM term_hierarchy WHERE parent <> 0');
foreach ($parents as $p) {
  Term::load($p['tid'])->set('parent', $p['parent'])->save();
}
$log(count($terms) . ' terms, ' . count($parents) . ' with a parent');

// 4. File records. Paths are relative to the Drupal root (sites/default/files/..., or files/... through the
// symlink) or to the files directory. 15-copy-files.sh has copied the original's files already; records whose
// file is gone from disk are skipped.
$filesDir = \Drupal::service('file_system')->realpath('public://');
$files = $q('SELECT fid, uid, filename, filepath, filemime, filesize, status, timestamp FROM files ORDER BY fid');
$kept = $missing = 0;
$fids = [];
foreach ($files as $f) {
  $rel = preg_replace('~^(sites/default/files|files)/~', '', $f['filepath']);
  if (!is_file("$filesDir/$rel")) {
    $missing++;
    continue;
  }
  File::create(['fid' => $f['fid'], 'uid' => $f['uid'], 'filename' => basename($rel), 'uri' => "public://$rel",
    'filemime' => $f['filemime'], 'filesize' => $f['filesize'], 'status' => 1, 'created' => $f['timestamp'], 'changed' => $f['timestamp']])->save();
  $fids[$f['fid']] = TRUE;
  $kept++;
}
$log("$kept file records ($missing skipped: file gone from disk)");

// 5. Nodes at their current revision, with their CCK fields and terms.
$cck = [];
foreach (['triplog', 'wp'] as $type) {
  foreach ($q("SELECT * FROM content_type_$type") as $r) {
    $cck[$r['vid']] = $r;
  }
}
$images = [];
foreach (['field_blog_image', 'field_blog_image_portrait'] as $field) {
  foreach ($q("SELECT vid, {$field}_fid fid, {$field}_data data FROM content_$field WHERE {$field}_fid IS NOT NULL ORDER BY vid, delta") as $r) {
    // The original showed the image's description as its alt text and caption (lightbox2).
    $data = @unserialize((string) $r['data']) ?: [];
    $images[$r['vid']][$field][] = ['fid' => $r['fid'], 'alt' => $data['description'] ?? $data['alt'] ?? ''];
  }
}
// The audio module: file, tags, length, format and counts, by revision. Format as it showed it
// ("MP3 Mono 32kHz 48Kbps (CBR)", whole numbers). The size it showed is the files table's, which stopped at
// 8,388,607 bytes for three files (Drupal 11 records the true size on the file; the pages keep the original's).
$audio = [];
foreach ($q('SELECT a.*, f.filesize FROM audio a LEFT JOIN files f ON f.fid = a.fid') as $a) {
  $audio[$a['vid']] = [
    'field_audio_file' => [['target_id' => $a['fid']]], 'field_audio_length' => $a['playtime'], 'field_audio_size' => $a['filesize'],
    'field_audio_format' => sprintf('%s %s %dkHz %dKbps (%s)', strtoupper($a['format']), ucfirst($a['channel_mode']), $a['sample_rate'] / 1000,
      $a['bitrate'] / 1000, strtoupper($a['bitrate_mode'])),
    'field_audio_downloads' => $a['download_count'], 'field_audio_plays' => $a['play_count'],
  ];
}
foreach ($q('SELECT vid, tag, value FROM audio_metadata') as $m) {
  $audio[$m['vid']]['field_audio_' . $m['tag']] = $m['value'];
}

// The poll module: each poll's choices and votes, in order.
foreach ($q('SELECT n.vid, c.chtext, c.chvotes FROM poll_choices c JOIN node n ON n.nid = c.nid ORDER BY c.nid, c.chorder') as $c) {
  $audio[$c['vid']]['field_poll_choices'][] = $c['chtext'];
  $audio[$c['vid']]['field_poll_votes'][] = $c['chvotes'];
}

$termsByVid = [];
foreach ($q('SELECT tn.vid, tn.tid, td.vid AS vocab FROM term_node tn JOIN term_data td ON td.tid = tn.tid ORDER BY td.weight, td.name') as $r) {
  $termsByVid[$r['vid']][$vocabField[$r['vocab']]][] = ['target_id' => $r['tid']];
}
$nodes = $q('SELECT n.nid, n.vid, n.type, n.uid, n.status, n.created, n.changed, n.comment, n.promote, n.sticky,
  r.title, r.body, r.teaser, r.format, r.log, r.timestamp FROM node n JOIN node_revisions r ON n.vid = r.vid ORDER BY n.nid');
$count = $retyped = $retitled = $lostImages = $rewritten = 0;
foreach ($nodes as $row) {
  $vid = $row['vid'];
  $type = $row['type'] === '' ? 'page' : $row['type'];
  $retyped += $row['type'] === '' ? 1 : 0;
  // Drupal 11 cannot store an empty title (node 2307, which also has no type): it gets a placeholder (declared).
  if ($row['title'] === '') {
    $row['title'] = '(untitled)';
    $retitled++;
  }
  // No PHP is run (owner, D6): each PHP block that only prints tripinfo_table('START','END') becomes
  // [tripinfo:START,END], which the hobobiker module renders. Any other PHP stops the import.
  $php = "~<\\?php\\s+print\\s+tripinfo_table\\('(\\d{4}-\\d\\d-\\d\\d)','(\\d{4}-\\d\\d-\\d\\d)'\\);\\s*\\?>~";
  $sameTeaser = $row['teaser'] === $row['body'];
  $row['body'] = preg_replace($php, '[tripinfo:$1,$2]', $row['body'], -1, $n);
  $row['teaser'] = $sameTeaser ? $row['body'] : preg_replace($php, '[tripinfo:$1,$2]', $row['teaser']);
  $rewritten += $n;
  if (str_contains($row['body'] . $row['teaser'], '<?php')) {
    throw new \RuntimeException("Node {$row['nid']}: PHP that is not a tripinfo_table() call");
  }
  $v = [
    'nid' => $row['nid'], 'type' => $type, 'title' => $row['title'], 'uid' => $row['uid'], 'status' => $row['status'],
    'promote' => $row['promote'], 'sticky' => $row['sticky'], 'created' => $row['created'], 'changed' => $row['changed'],
    'revision_timestamp' => $row['timestamp'], 'revision_log' => $row['log'],
    // Drupal 6 stored the teaser (cut at 1,600 characters or <!--break--> when saved); kept as the summary, so
    // listings show exactly the original's teasers. A teaser equal to the body needs none.
    // An EMPTY teaser (Drupal 6 made none for bodies with PHP) showed only the read-more link; an empty summary
    // would mean "trim the body" in Drupal 11, so it is a comment that renders nothing.
    'body' => ['value' => $row['body'], 'format' => $fmt($row['format']), 'summary' => match (TRUE) {
      $row['teaser'] === $row['body'] => '',
      $row['teaser'] === '' => '<!-- no teaser on the original -->',
      default => $row['teaser'],
    }],
    'comment' => [['status' => (int) $row['comment']]],
  ] + ($termsByVid[$vid] ?? []);
  // Module data that lived in its own tables (audio, poll).
  $v += $audio[$vid] ?? [];
  if ($c = $cck[$vid] ?? NULL) {
    foreach ($c as $col => $value) {
      if ($value === NULL || !preg_match('/^(field_.+)_value$/', $col, $m)) {
        continue;
      }
      // Dates are stored as 2008-12-30T00:00:00; the fields hold the date only.
      $v[$m[1]] = in_array($m[1], ['field_date', 'field_date_0']) ? substr($value, 0, 10) : $value;
    }
  }
  foreach ($images[$vid] ?? [] as $field => $list) {
    foreach ($list as ['fid' => $fid, 'alt' => $alt]) {
      if (isset($fids[$fid])) {
        $v[$field][] = ['target_id' => $fid, 'alt' => $alt];
      }
      else {
        $lostImages++;
      }
    }
  }
  Node::create($v)->save();
  $count++;
}
$log("$count nodes ($retyped without a type became pages, $retitled empty titles became \"(untitled)\"; $lostImages image references to missing files; $rewritten PHP calls became [tripinfo:])");

// 6. Aliases, oldest first so the newest wins for generated links (as on the original). Aliases of deleted
// nodes are dropped. (The pattern is a placeholder: Drupal reads [brackets] in a query as a quoted identifier.)
$aliases = $q('SELECT a.src, a.dst FROM url_alias a
  LEFT JOIN node n ON a.src REGEXP :re AND n.nid = CAST(SUBSTRING(a.src, 6) AS UNSIGNED)
  WHERE a.src NOT REGEXP :re OR n.nid IS NOT NULL ORDER BY a.pid', [':re' => '^node/[0-9]+$']);
foreach ($aliases as $a) {
  PathAlias::create(['path' => '/' . $a['src'], 'alias' => '/' . $a['dst'], 'langcode' => 'und'])->save();
}
$dropped = $q("SELECT COUNT(*) c FROM url_alias")[0]['c'] - count($aliases);
$log(count($aliases) . " aliases ($dropped of deleted nodes dropped)");

// 7. Comments, in thread order so parents exist before replies. Drupal 6: status 0 means published.
$comments = $q('SELECT c.* FROM comments c JOIN node n ON n.nid = c.nid ORDER BY c.nid, c.thread');
foreach ($comments as $row) {
  Comment::create(['cid' => $row['cid'], 'pid' => $row['pid'] ?: NULL, 'entity_id' => $row['nid'], 'entity_type' => 'node',
    'field_name' => 'comment', 'comment_type' => 'comment', 'uid' => $row['uid'], 'subject' => $row['subject'],
    'hostname' => $row['hostname'], 'created' => $row['timestamp'], 'changed' => $row['timestamp'],
    'status' => $row['status'] == 0 ? 1 : 0, 'thread' => $row['thread'], 'name' => $row['name'] ?: NULL,
    'mail' => $row['mail'] ?: NULL, 'homepage' => $row['homepage'] ?: NULL,
    'comment_body' => ['value' => $row['comment'], 'format' => $fmt($row['format'])]])->save();
}
$droppedC = $q('SELECT COUNT(*) c FROM comments')[0]['c'] - count($comments);
$log(count($comments) . " comments ($droppedC on a deleted node dropped)");
