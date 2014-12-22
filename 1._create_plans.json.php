<?php

//Enter the base directory URI (can be either a http url or a local path)
//Note: Replace backslash \ with slash /.
$base = 'path/to/export/dir/';
//Enter code for the time period you want to download (is part of the url for the plan files)
$week = 'P1';

$json = create_json($base, $week);

header('Content-Type: text/plain');
echo $json;

file_put_contents('plans.json', $json);



function create_json($base, $week){
  $base = preg_replace('#[\\/][^\\/]*$#', '/', $base); //remove default.htm from $base
  
  $navbar = file_get_contents($base . 'frames/navbar.htm');
  
  $navbar = preg_replace('/[\r\n]+/', "\n", utf8_encode($navbar));

  //the directory structure has two levels of folders, this defines which comes first
  $top_dir_week = strpos($navbar, 'topDir = "w"') !== false;

  //TODO: This part is not complete. There are other lists like rooms or teachers, and each of them is optional.
  $classes = read_JSON($navbar, 'classes');
  $students = read_JSON($navbar, 'students');
  $studtable = read_JSON($navbar, 'studtable');
  if(!$classes || !$students || !$studtable){
    die("Did not find all arrays in navbar.htm. File content:\n\n\n" . $navbar);
  }

  $out = array();

  foreach($classes as $i => $class){
    $filename = str_to_filename($class);
    
    $src_file = str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '.htm';
    $out[$i] = array(
      'name' => $class,
      'source' => $base . ($top_dir_week ? $week . '/c/c' : 'c/' . $week . '/c') . $src_file,
      'file' => $filename);
  }

  foreach($students as $i => $student){
    $filename = str_to_filename($student);
    
    if($studtable[$i] == 0){
      die('classless student detected'); //I don't know if this is possible
    }
    
    $src_file = str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '.htm';
    $out[$studtable[$i] - 1]['childs'][] = array(
      'name' => $student,
      'source' => $base . ($top_dir_week ? $week . '/s/s' : 's/' . $week . '/s') . $src_file,
      'file' => $filename);
  }

  return json_encode($out, JSON_PRETTY_PRINT);
}


//read a variable containing JSON data from the JavaScript in navbar.htm
function read_JSON($navbar, $name){
  $startstr = 'var ' . $name . ' = ';
  $startpos = strpos($navbar, $startstr);
  if($startpos === false){
    return false;
  }
  $startpos += strlen($startstr);
  $endpos = strpos($navbar, ";\n", $startpos);
  $json = substr($navbar, $startpos, $endpos - $startpos);
  
  return json_decode($json, true);
}

//create unique ascii-only filenames
function str_to_filename($str){
  static $used_filenames = array();
  $filename = trim(
    preg_replace('/[^a-z0-9]+/', '-',
      strtolower(
        strtr(
          strtr(utf8_decode($str), '€ƒŠŽšžŸ¢¥ª²³¹ºÀÁÂÃÅÇÈÉÊËÌÍÎÏÐÑÒÓÔÕ×ØÙÚÛÝàáâãåçèéêëìíîïðñòóôõøùúûýÿ',
                                   'EfSZszYcYa231oAAAAACEEEEIIIIDNOOOOxOUUUYaaaaaceeeeiiiidnooooouuuyy'),
          array('™' => 'TM', 'Œ' => 'OE', 'Æ' => 'AE', 'œ' => 'oe', 'æ' => 'ae', 'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Þ' => 'TH', 'þ' => 'th', 'ß' => 'ss')
        )
      )
    )
  , '-');
  
  $nr = 0;
  while(in_array($filename . ($nr == 0 ? '' : $nr), $used_filenames)){
    $nr++;
  }
  if($nr != 0){
    $filename .= $nr;
  }
  array_push($used_filenames, $filename);
  return $filename;
}
