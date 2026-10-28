<?php

/**
 * @file
 * Embedding distance of contradicting versus reworded fact pairs.
 *
 * Run: ddev drush php:script demo/recall-eval/auto-band.php [classify|ground]
 *
 * With "classify", also runs each pair through consolidation's real
 * classifier (the decision model, plus the merge writer on UPDATE): one or
 * two model calls per pair.
 *
 * With "ground" (implies classify), each UPDATE's merged text also goes
 * through the merge verifier and the grounded check, with the passage
 * consolidation sends (the two inputs, one per line) and with a labelled
 * one, plus the merge with an invented sentence added as a negative
 * control. Grounding must not be off in aim.settings.
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
  // Multi-detail changes to the demo recipe's facts, after the 2026-10-27
  // lifecycle test (ADR-0036 addendum), whose exact texts were not kept.
  'lifecycle' => [
    ['Harbourside Community Library is open Monday to Friday from 9am to 8pm, Saturday from 10am to 5pm, and Sunday from 12pm to 4pm.', 'On Saturdays the library now opens at 9am.'],
    ['Members can borrow up to 20 items at a time for 3 weeks, and can renew twice online unless another member has reserved the item.', 'Members can now borrow up to 10 items at a time.'],
    ['Public computers can be booked for 60 minutes at a time, and printing costs 10p per black-and-white page.', 'Printing now costs 20p per black-and-white page.'],
    ['Code Club for ages 9 to 13 meets every Thursday at 4pm in the makerspace.', 'Code Club has moved to Wednesdays at 4pm.'],
    ['Free Wi-Fi is available in the building on the network Harbourside_Public, and it needs no password.', 'The Wi-Fi now needs a password, which is available at the main desk.'],
    ['Community groups can book a meeting room for free, for up to 4 hours at a time.', 'Meeting rooms can now be booked for up to 2 hours at a time.'],
  ],
];

$service = \Drupal::service('aim.consolidation');
$embed = (new ReflectionMethod($service, 'embedText'))->getClosure($service);
$distance = (new ReflectionMethod($service, 'cosineDistance'))->getClosure($service);
$ground = in_array('ground', $extra ?? [], TRUE);
$classify = $ground || in_array('classify', $extra ?? [], TRUE) ? (new ReflectionMethod($service, 'classifyPair'))->getClosure($service) : NULL;
$verify = (new ReflectionMethod($service, 'modelConfirmsMerge'))->getClosure($service);
$checker = \Drupal::service('aim.grounding_checker');
$passages = [
  'plain' => fn (string $a, string $b) => "$a\n$b",
  'labelled' => fn (string $a, string $b) => "Older fact: $a\nNewer fact, which replaces any value in the older fact that it changes: $b",
];
$invented = 'Booking is required at the main desk.';
$ground_rows = [];
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
      if ($ground && $decision === 'UPDATE') {
        $row = [
          'faithful' => $verify($fact($a), $fact($b), $merged, '', ''),
          'faithful +added' => $verify($fact($a), $fact($b), "$merged $invented", '', ''),
        ];
        foreach ($passages as $name => $passage) {
          $scores_g = $checker->checkGrounding($passage($a, $b), ['merge' => $merged, 'added' => "$merged $invented"]);
          $row[$name] = $scores_g['merge'];
          $row[$name . ' +added'] = $scores_g['added'];
        }
        $ground_rows[] = $row;
        printf("         verifier %s, +added %s; grounding %s\n", $row['faithful'] ? 'faithful' : 'UNFAITHFUL', $row['faithful +added'] ? 'faithful' : 'UNFAITHFUL', implode(', ', array_map(
          fn ($k) => sprintf('%s %s', $k, $row[$k] === NULL ? 'n/a' : sprintf('%.2f', $row[$k])),
          array_keys(array_diff_key($row, ['faithful' => 1, 'faithful +added' => 1])),
        )));
      }
    }
  }
  printf("  min %.3f, max %.3f, mean %.3f\n", min($scores), max($scores), array_sum($scores) / count($scores));
}
printf("\nAUTO = under the old auto band (%.2f)\n", $auto);
if ($ground_rows) {
  $threshold = \Drupal::service('aim.settings_reader')->getGroundingThreshold();
  printf("\nGrounding over %d merges, threshold %.2f:\n", count($ground_rows), $threshold);
  foreach (['faithful', 'faithful +added'] as $key) {
    printf("  verifier %-8s faithful %d/%d\n", substr($key, 9) ?: 'merge', count(array_filter(array_column($ground_rows, $key))), count($ground_rows));
  }
  foreach (array_keys(array_diff_key($ground_rows[0], ['faithful' => 1, 'faithful +added' => 1])) as $key) {
    $values = array_column($ground_rows, $key);
    printf("  %-17s pass %d/%d, min %.2f, mean %.2f\n", $key, count(array_filter($values, fn ($v) => $v !== NULL && $v >= $threshold)), count($values), min($values), array_sum($values) / count($values));
  }
}
