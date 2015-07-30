<?php
die('disabled');

ini_set('max_execution_time', 300);

$json = file_get_contents('timetables.json');
$json = json_decode($json, true);

foreach ($json as &$class) {
  get_file($class);
  if (array_key_exists('childs', $class)) {
    foreach ($class['childs'] as &$child) {
      get_file($child);
    }
  }
}

file_put_contents('timetables.json', json_encode($json, JSON_PRETTY_PRINT));


function get_file(&$element) {
  echo '.';
  $new_file = 'raw/' . $element['file'] . '.html';
  file_put_contents($new_file, file_get_contents($element['source'])); //save all files to the folder raw/
  $element['source'] = $new_file;
}
