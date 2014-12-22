<?php
die('disabled');

define('NO_PREVIEW', true);
require('3.0_create_timetables_preview.php');

$json = json_decode(file_get_contents('plans.json'), true);

foreach($json as $class){
  makeFile($class);
  if(array_key_exists('childs', $class)){
    foreach($class['childs'] as $child){
      makeFile($child);
    }
  }
}

function makeFile($entry){
  echo '.';
  $raw = file_get_contents('raw/' . $entry['file'] . '.htm');
  $out = convert_plan($raw, $entry['name']);

  file_put_contents('output/plans/' . //The output dir [For appspot: 'appspot/plans/']
    $entry['file'] . '.htm', $out);
}
