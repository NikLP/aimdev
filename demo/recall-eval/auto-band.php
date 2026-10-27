<?php

/**
 * @file
 * Embedding distance of contradicting versus reworded fact pairs.
 *
 * Run: ddev drush php:script demo/recall-eval/auto-band.php [classify]
 *
 * With "classify", also runs each pair through consolidation's real
 * classifier (the decision model, plus the merge writer on UPDATE): one or
 * two model calls per pair.
 *
 * Checks whether a distance band can tell a correction from a reworded
 * duplicate. Consolidation used to retire any pair under 0.09 (the removed
 * auto_threshold) with no model check; this measured that 8 of 10
 * contradictions fell inside it (2026-10-27). Uses the same embedding call
 * and distance as consolidation.
 */

$pairs = [
  'contradiction' => [
    ['Code Club meets every Thursday at 4pm.', 'Code Club meets every Wednesday at 4pm.'],
    ['The library opens at 9am.', 'The library opens at 10am.'],
    ['Dogs are allowed inside the library.', 'Dogs are not allowed inside the library.'],
    ['Membership is free for residents.', 'Membership costs 10 pounds a year for residents.'],
    ['Printing costs 10p per page.', 'Printing costs 20p per page.'],
    ['The library is open on Sundays.', 'The library is closed on Sundays.'],
    ['Sam prefers short answers.', 'Sam prefers long, detailed answers.'],
    ['The Wi-Fi needs no password.', 'The Wi-Fi needs a password.'],
    ['Members can borrow up to 20 items at a time.', 'Members can borrow up to 10 items at a time.'],
    ['Baby Rhyme Time runs every Tuesday at 10:30am.', 'Baby Rhyme Time runs every Tuesday at 11:30am.'],
  ],
  'reworded' => [
    ['The library opens at 9am.', "The library's opening time is 9am."],
    ['Code Club meets every Thursday at 4pm.', 'Every Thursday at 4pm, Code Club meets.'],
    ['Printing costs 10p per page.', 'It costs 10p to print a page.'],
    ['Dogs are not allowed inside the library.', 'No dogs are permitted in the library.'],
    ['Membership is free for residents.', 'Residents can join for free.'],
    ['Sam prefers short answers.', 'Sam likes answers to be brief.'],
    ['Members can borrow up to 20 items at a time.', 'A member may have 20 items out at once.'],
    ['The Wi-Fi needs no password.', 'No password is needed for the Wi-Fi.'],
  ],
];

$service = \Drupal::service('aim.consolidation');
$embed = (new ReflectionMethod($service, 'embedText'))->getClosure($service);
$distance = (new ReflectionMethod($service, 'cosineDistance'))->getClosure($service);
$classify = in_array('classify', $extra ?? [], TRUE) ? (new ReflectionMethod($service, 'classifyPair'))->getClosure($service) : NULL;
$fact = fn (string $text) => \Drupal\aim\Entity\AimFact::create(['scope' => 'site', 'text' => $text]);
// The removed auto_threshold's last default, kept for comparison.
$auto = 0.09;

foreach ($pairs as $kind => $list) {
  $scores = [];
  echo "\n$kind\n";
  foreach ($list as [$a, $b]) {
    $d = $distance($embed($a), $embed($b));
    $scores[] = $d;
    printf("  %.3f%s  %s | %s\n", $d, $d <= $auto ? ' AUTO' : '     ', $a, $b);
    if ($classify) {
      [$decision, $merged] = $classify($fact($a), $fact($b), '', '');
      printf("         model: %s%s\n", $decision, $merged ? " -> $merged" : '');
    }
  }
  printf("  min %.3f, max %.3f, mean %.3f\n", min($scores), max($scores), array_sum($scores) / count($scores));
}
printf("\nAUTO = under the old auto band (%.2f)\n", $auto);
