<?php

//Enter the base directory URL (can be either a http url or a local path)
//Note: Replace backslash \ with slash /.
$base = '/path/to/export/dir/';

//Enter code for the time period you want to download (is part of the url for the timetable files)
$week = 'P1';


$json = create_json($base, $week);

header('Content-Type: text/plain');
echo $json;

file_put_contents('timetables.json', $json);



function create_json($base, $week) {
  $navbar = file_get_contents($base . 'frames/navbar.htm');
  //$navbar = file_get_contents('navbar.htm');

  $navbar = iconv('CP1252', 'UTF-8', $navbar);
  $navbar = preg_replace('/[\r\n]+/', "\n", $navbar);

  //the directory structure has two levels of folders, this specifies which comes first
  $top_dir_week = strpos($navbar, 'topDir = "w"') !== false;

  //TODO: This part is not complete. There are other lists like rooms or teachers, and each of them is optional.
  $classes = read_JSON('classes', $navbar);
  $students = read_JSON('students', $navbar);
  $studtable = read_JSON('studtable', $navbar);
  if (!$classes || !$students || !$studtable) {
    die("Did not find all arrays in navbar.htm. File content:\n\n\n" . $navbar);
  }

  $out = array();

  foreach ($classes as $i => $class) {
    $filename = str_to_filename($class);

    $src_file = str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '.htm';
    $out[$i] = array(
      'name' => $class,
      'source' => $base . ($top_dir_week ? $week . '/c/c' : 'c/' . $week . '/c') . $src_file,
      'file' => $filename);
  }

  foreach ($students as $i => $student) {
    $filename = str_to_filename($student);

    if ($studtable[$i] <= 0) {
      continue;
      //student without class, currently not supported
    }

    $src_file = str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '.htm';
    $out[$studtable[$i] - 1]['childs'][] = array(
      'name' => $student,
      'source' => $base . ($top_dir_week ? $week . '/s/s' : 's/' . $week . '/s') . $src_file,
      'file' => $filename);
  }

  /*
  //Sort list by name
  function sort_name($a, $b) {
    return strncmp($a['name'], $b['name'], 1);
  }
  usort($out, 'sort_name');*/

  return json_encode($out, JSON_PRETTY_PRINT);

}


//read a variable containing JSON data from the JavaScript in navbar.htm
function read_JSON($name, $navbar) {
  $startstr = 'var ' . $name . ' = ';
  $startpos = strpos($navbar, $startstr);
  if ($startpos === false) {
    return false;
  }
  $startpos += strlen($startstr);
  $endpos = strpos($navbar, ";\n", $startpos);
  $json = substr($navbar, $startpos, $endpos - $startpos);

  return json_decode($json, true);
}

//create unique ascii-only filenames
function str_to_filename($str) {
  static $used_filenames = array();
  $filename = trim(
    preg_replace('/[^a-z0-9]+/', '-',
      strtolower(
        strtr(
          iconv('UTF-8', 'CP1252', strtr(
            $str,
            array('™' => 'TM', 'Œ' => 'OE', 'Æ' => 'AE', 'œ' => 'oe', 'æ' => 'ae', 'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Þ' => 'TH', 'þ' => 'th', 'ß' => 'ss')
          )),
          iconv('UTF-8', 'CP1252', '€ƒŠŽšžŸ¢¥ª²³¹ºÀÁÂÃÅÇÈÉÊËÌÍÎÏÐÑÒÓÔÕ×ØÙÚÛÝàáâãåçèéêëìíîïðñòóôõøùúûýÿ'),
                                   'EfSZszYcYa231oAAAAACEEEEIIIIDNOOOOxOUUUYaaaaaceeeeiiiidnooooouuuyy'
        )
      )
    )
  , '-');

  $nr = 0;
  while (in_array($filename . ($nr == 0 ? '' : $nr), $used_filenames)) {
    $nr++;
  }
  if ($nr != 0) {
    $filename .= $nr;
  }
  array_push($used_filenames, $filename);
  return $filename;
}
