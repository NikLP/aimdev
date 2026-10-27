<?php

/**
 * @file
 * Scores aim recall against gold.yml.
 *
 * Run: ddev drush php:script demo/recall-eval/run.php [gold file]
 *
 * Fetches the 30 nearest facts per question with no cutoff (access check
 * off, scope filter from the item), then reports the rank of each expected
 * fact and, for a range of cutoffs (with and without recall_gap), how many
 * questions come back right.
 */

use Symfony\Component\Yaml\Yaml;

$file = $extra[0] ?? __DIR__ . '/gold.yml';
$items = Yaml::parseFile($file)['items'];
$recall = \Drupal::service('aim.recall');
$live = (float) \Drupal::config('aim.settings')->get('recall_max_distance');

$runs = [];
foreach ($items as $item) {
  $rows = $recall->recall($item['q'], $item['scope'] ?? NULL, NULL, NULL, 30, NULL, FALSE, NULL, FALSE);
  $ranked = [];
  foreach (array_values($rows) as $i => $row) {
    $ranked[] = ['rank' => $i + 1, 'score' => (float) $row['score'], 'text' => $row['text']];
  }
  // For each expected substring: its rank and distance, or NULL if absent.
  $found = [];
  foreach ($item['expect'] as $needle) {
    $found[$needle] = NULL;
    foreach ($ranked as $r) {
      if (str_contains($r['text'], $needle)) {
        $found[$needle] = $r;
        break;
      }
    }
  }
  $runs[] = ['item' => $item, 'ranked' => $ranked, 'found' => $found];
}

// Per question.
foreach ($runs as $run) {
  $item = $run['item'];
  $top = $run['ranked'][0] ?? NULL;
  if (!$item['expect']) {
    printf("%-48s none expected; nearest %.3f (%s)\n", $item['q'], $top['score'] ?? 0, mb_substr($top['text'] ?? '', 0, 40));
    continue;
  }
  $parts = [];
  foreach ($run['found'] as $needle => $r) {
    $parts[] = $r ? sprintf('%s #%d %.3f', $needle, $r['rank'], $r['score']) : "$needle MISSING";
  }
  printf("%-48s %s\n", $item['q'], implode(' | ', $parts));
}

// Ranking, independent of the cutoff: share of expected facts in the top 3/5.
$expected = 0;
$top3 = 0;
$top5 = 0;
foreach ($runs as $run) {
  foreach ($run['found'] as $r) {
    $expected++;
    $top3 += ($r && $r['rank'] <= 3) ? 1 : 0;
    $top5 += ($r && $r['rank'] <= 5) ? 1 : 0;
  }
}
printf("\nRanking: %d expected facts, %d in top 3, %d in top 5\n", $expected, $top3, $top5);

// Cutoff sweep. Answerable: every expected fact within the cutoff (all) or
// at least one (any). Empty: an expect: [] question returns nothing. Noise:
// unexpected facts returned within the cutoff, summed over all questions.
$answerable = count(array_filter($runs, fn($r) => $r['item']['expect']));
$empty = count($runs) - $answerable;
printf("\n%-14s %-10s %-10s %-10s %s\n", 'cutoff/gap', "all/$answerable", "any/$answerable", "empty/$empty", 'noise');
$gap = (float) \Drupal::config('aim.settings')->get('recall_gap');
$settings = [[0.40, 0], [0.44, 0], [0.48, 0], [0.52, 0], [0.56, 0], [0.60, 0], [0.48, 0.1], [0.52, 0.1], [0.56, 0.1]];
foreach ($settings as [$cut, $g]) {
  $all = $any = $ok_empty = $noise = 0;
  foreach ($runs as $run) {
    // recall_gap also drops anything further than the best match plus gap.
    $limit = $g && $run['ranked'] ? min($cut, $run['ranked'][0]['score'] + $g) : $cut;
    $within = array_filter($run['ranked'], fn($r) => $r['score'] <= $limit);
    if (!$run['item']['expect']) {
      $ok_empty += $within ? 0 : 1;
      $noise += count($within);
      continue;
    }
    $hits = array_filter($run['found'], fn($r) => $r && $r['score'] <= $limit);
    $all += count($hits) === count($run['found']) ? 1 : 0;
    $any += $hits ? 1 : 0;
    $noise += count($within) - count($hits);
  }
  $is_live = abs($cut - $live) < 0.001 && abs($g - $gap) < 0.001;
  printf("%-14s %-10d %-10d %-10d %d%s\n", number_format($cut, 2) . ($g ? ' / ' . $g : ''), $all, $any, $ok_empty, $noise, $is_live ? '  <- live' : '');
}
