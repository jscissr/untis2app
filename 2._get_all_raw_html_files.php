<?php
die('disabled');

ini_set('max_execution_time', 300);

$json = json_decode(file_get_contents('plans.json'), true);

foreach($json as $class){
  getFile($class);
  
  if(array_key_exists('childs', $class)){
    foreach($class['childs'] as $child){
      getFile($child);
    }
  }
}

file_put_contents('plans.json', json_encode($json, JSON_PRETTY_PRINT));


function getFile($element){
  echo '.';
  $newFile = 'raw/' . $element['file'] . '.htm';
  file_put_contents($newFile, file_get_contents($element['source'])); //save all files to the folder raw/
  $element['source'] = $newFile;
}
