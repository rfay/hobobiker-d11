<?php

/**
 * @file
 * Step 20: the 4 Drupal 6 users, with their uids, names, dates, status and roles.
 *
 * No password hash or email address is copied: each user gets a random password (log in with drush uli).
 * Updates users that exist (uid 1 is the installer's admin, which becomes D6's uid 1); never deletes one,
 * because deleting a user in Drupal 11 deletes their nodes.
 * Run: ddev drush php:script scripts/20-users.php
 */

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

require __DIR__ . '/lib.php';

// D6 roles beyond anonymous/authenticated, by name. administrator already exists in Drupal 11.
$roles = [];
foreach (d6()->query("SELECT rid, name FROM {role} WHERE rid > 2 AND name <> 'non-authenticated user'") as $role) {
  $id = $role->name === 'administrator' ? 'administrator' : preg_replace('/\W+/', '_', $role->name);
  if (!Role::load($id)) {
    Role::create(['id' => $id, 'label' => ucfirst($role->name)])->save();
  }
  $roles[$role->rid] = $id;
}

$users = d6()->query('SELECT uid, name, created, access, login, status, timezone_name FROM {users} WHERE uid > 0 ORDER BY uid')->fetchAll();
$created = 0;
foreach ($users as $row) {
  $user = User::load($row->uid);
  if (!$user) {
    $user = User::create(['uid' => $row->uid])->enforceIsNew();
    $user->setPassword(\Drupal::service('password_generator')->generate(32));
    $created++;
  }
  $user->setUsername($row->name);
  $user->set('created', $row->created);
  $user->set('access', $row->access);
  $user->set('login', $row->login);
  $user->set('status', $row->status);
  $user->set('timezone', $row->timezone_name ?: 'UTC');
  // No email is written: the source's are sanitized personal data, and none is needed.
  $user->set('mail', NULL);
  $user->set('init', NULL);
  $user->set('roles', []);
  foreach (d6()->query('SELECT rid FROM {users_roles} WHERE uid = :uid', [':uid' => $row->uid])->fetchCol() as $rid) {
    if (isset($roles[$rid])) {
      $user->addRole($roles[$rid]);
    }
  }
  $user->save();
}

$total = \Drupal::entityQuery('user')->accessCheck(FALSE)->condition('uid', 0, '>')->count()->execute();
printf("users from D6 %d (created %d, updated %d), users on site %d, roles %d\n", count($users), $created, count($users) - $created, $total, count($roles));
