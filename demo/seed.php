<?php

/**
 * @file
 * Seeds the demo site: facts, site identity, front page and chat persona.
 *
 * Idempotent. Run from the site root:
 *
 *   ddev drush php:script demo/seed.php
 *
 * Replaces every fact tagged source "demo-seed" and leaves other facts alone.
 * To also remove facts added during a demo (a full reset), run:
 *
 *   ddev exec DEMO_RESET=1 drush php:script demo/seed.php
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

$data = json_decode(file_get_contents(__DIR__ . '/seed-facts.json'), TRUE);
$mm = \Drupal::service('aim.memory_manager');
$storage = \Drupal::entityTypeManager()->getStorage('aim_fact');
$source = 'demo-seed';

// Facts.
// DEMO_RESET=1 also removes every fact the audience added through chat or
// MCP, returning the site to its starting state between demo runs. Without
// it only the seeded facts are replaced.
$query = $storage->getQuery()->accessCheck(FALSE);
if (!getenv('DEMO_RESET')) {
  $query->condition('source', $source);
}
$old = $query->execute();
if ($old) {
  $storage->delete($storage->loadMultiple($old));
}
$count = 0;
foreach ($data['site'] as $text) {
  $mm->remember($text, 'site', NULL, $source);
  $count++;
}
foreach ($data['role'] as $role => $texts) {
  foreach ($texts as $text) {
    $mm->remember($text, 'role', $role, $source);
    $count++;
  }
}
foreach ($data['user'] as $uid => $texts) {
  foreach ($texts as $text) {
    $mm->remember($text, 'user', (string) $uid, $source);
    $count++;
  }
}
foreach ($data['case'] as $texts) {
  $case_id = NULL;
  foreach ($texts as $text) {
    // The first fact mints the case ID, the rest join it.
    $fact = $mm->remember($text, 'case', $case_id, $source);
    $case_id ??= $fact->get('subject')->value;
    $count++;
  }
}
\Drupal::database()->query("DELETE FROM {queue} WHERE name = 'aim_consolidate'");
echo "$count facts saved (replaced " . count($old) . ").\n";

// Site identity.
\Drupal::configFactory()->getEditable('system.site')
  ->set('name', $data['persona']['site_name'])
  ->set('slogan', $data['persona']['site_slogan'])
  ->save();

// Front page: a basic page, created on a fresh install with no content types.
if (!NodeType::load('page')) {
  NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
}
// No "By Anonymous, date" byline on the front page.
NodeType::load('page')->set('display_submitted', FALSE)->save();
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary'])->save();
}
if (!FieldConfig::loadByName('node', 'page', 'body')) {
  FieldConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'bundle' => 'page', 'label' => 'Body'])->save();
}
if (!EntityViewDisplay::load('node.page.default')) {
  EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'page', 'mode' => 'default', 'status' => TRUE])
    ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0])
    ->save();
}
$body = <<<HTML
<p>Harbourside Community Library is fictional. This site demonstrates <strong>AIM</strong>, agent memory built natively in Drupal: facts are entities, searched with MariaDB vectors through Search API, written through Guardrails, and shared with other agents over MCP.</p>
<p>Open the chat at the bottom right and ask it something, or tell it something to remember.</p>
<h2>Try asking</h2>
<ul>
<li>When are you open on Sundays?</li>
<li>Can I bring my dog?</li>
<li>Do you have a cafe? (it should say it doesn't know)</li>
<li>Tell it: "We now have a cafe, open 10 to 3 every day." Then ask about the cafe again.</li>
</ul>
HTML;
$front = \Drupal::config('system.site')->get('page.front');
$node = preg_match('#^/node/(\d+)$#', (string) $front, $m) ? Node::load((int) $m[1]) : NULL;
if (!$node || $node->bundle() !== 'page') {
  $node = Node::create(['type' => 'page']);
}
$node->set('title', $data['persona']['front_page_title'])->set('body', ['value' => $body, 'format' => 'full_html'])->setPublished()->set('promote', 0)->save();
\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/node/' . $node->id())->save();

// Chat persona.
\Drupal::configFactory()->getEditable('ai_agents.ai_agent.aim_chatbot')->set('system_prompt', $data['persona']['agent_prompt'])->save();
\Drupal::configFactory()->getEditable('ai_assistant_api.ai_assistant.aim_demo_assistant')->set('instructions', $data['persona']['agent_prompt'])->save();

// Chat widget wording and the recall cutoff calibrated for this dataset.
\Drupal::configFactory()->getEditable('block.block.olivero_aimdemochat')
  ->set('settings.label', $data['persona']['widget_label'])
  ->set('settings.bot_name', $data['persona']['bot_name'])
  ->set('settings.first_message', $data['persona']['welcome'])
  ->save();
\Drupal::configFactory()->getEditable('aim.settings')->set('recall_max_distance', $data['config']['recall_max_distance'])->save();
echo "Site identity, front page (node/{$node->id()}), chat persona and recall cutoff set.\n";
