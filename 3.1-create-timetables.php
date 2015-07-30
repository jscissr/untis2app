<?php
die('disabled');

define('NO_PREVIEW', true);
require('3.0-create-timetables-preview.php');

$json = json_decode(file_get_contents('timetables.json'), true);

foreach ($json as $class) {
  makeFile($class);
  if (array_key_exists('childs', $class)) {
    foreach ($class['childs'] as $child) {
      makeFile($child);
    }
  }
}

function makeFile($entry) {
  echo '.';
  $raw = file_get_contents($entry['source']);
  $out = convert_timetable($raw, $entry['name']);

  file_put_contents('output/timetables/' . //The output dir [For appspot: 'appspot/timetables/']
      $entry['file'] . '.html', $out);
}
