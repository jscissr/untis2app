<?php

$json = json_decode(file_get_contents('timetables.json'), true);

//additional files like the cache manifest and images will have this base path (like the html <base>). Try ../ or /
$out_base = '';
//The folder of the timetable files (note: $out_base is NOT prepended)
$out_timetables_dir = 'timetables/'; // [For appspot: '']
//The suffix of the timetable files
$out_timetables_suffix = '.html'; // [For appspot: '']



ob_start();

?>
<!DOCTYPE html>
<head>
<meta charset="utf-8">
<title>Timetable App</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="nofollow">
<link rel="icon" sizes="192x192" type="image/png" href="<?php echo $out_base; ?>img/192.png">
<link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/icon.svg">
<link href="https://fonts.googleapis.com/css?family=Roboto:100" rel="stylesheet">
<style>
body {
  font-family: sans-serif;
  padding: 0;
  margin: 0;
}
body, a {
  color: rgba(0, 0, 0, .87);
  text-decoration: none;
}
h1 {
  font: 100 2em 'Roboto', sans-serif;
  text-align: center;
  margin: 20px 20px 30px;
}
p {
  margin: 30px 0;
  padding: 0 20px;
}
ul a {
  display: block;
  padding: 16px 32px;
  font-size: 16px;
  height: 16px;
  -webkit-tap-highlight-color: rgba(0,0,0,0);
}
ul a:hover {
  background: #ffeb3b; /*Yellow 500*/
}
ul {
  list-style: none;
  padding: 0;
  margin: 30px 0;
}
li div {
  height: 0;
  overflow: hidden;
  transition: height .6s;
  -moz-transition: height .6s;
  -webkit-transition: height .6s;
  -o-transition: height .6s;
}
li ul {
  margin: 0;
}
li ul a {
  padding-left: 48px;
}
#footer {
  background: #f5f5f5;
  padding: 30px;
}
</style>
<h1>Timetable App</h1>

<ul><?php

$all_childs = array();
foreach ($json as $class) {
  echo '<li' . (!empty($class['childs']) ? ' id="' . $class['file'] . '"' : '') . '><a href="' . $out_timetables_dir . $class['file'] . $out_timetables_suffix . '">' . $class['name'] . '</a>';
  if (!empty($class['childs'])) {
    $no_source_childs = array();
    foreach ($class['childs'] as $child) {
      $no_source_childs[] = array('file' => $child['file'], 'name' => $child['name']);
    }
    $all_childs[$class['file']] = $no_source_childs;
    /*echo '<ul>';
    foreach ($class['childs'] as $child) {
      echo '<li><a href="' . $out_timetables_dir . $child['file'] . $out_timetables_suffix . '">' . $child['name'] . '</a>';
    }
    echo '</ul>';*/
  }
}

?>
</ul>

<div id="footer">
  <a href="https://github.com/jscissr/untis2app">GitHub</a>
</div>
<script>
!function() {
  'use strict';

  function toggleCollapse(event) {
    var div = event.currentTarget.getElementsByTagName('div')[0];
    var target = event.target;
    while ((target = target.parentNode)) {
      if (target === div) {
        return;
      }
    }
    if (div.style.height) {
      div.style.height = '';
    } else {
      div.style.height = div.firstChild.clientHeight + 'px';
    }
    event.preventDefault();
  }

  var allChilds = <?php echo json_encode($all_childs); ?>;
  for (var childs in allChilds) {
    if (allChilds.hasOwnProperty(childs)) {
      var parent = document.getElementById(childs);
      var ul = document.createElement('ul');
      for (var i = 0; i < allChilds[childs].length; i++) {
        var a = ul.appendChild(document.createElement('li'))
            .appendChild(document.createElement('a'));
        a.href = <?php echo json_encode($out_timetables_dir); ?> + allChilds[childs][i].file;
        a.appendChild(document.createTextNode(allChilds[childs][i].name));
      }
      var div = document.createElement('div');
      div.appendChild(ul);
      parent.appendChild(div);
      parent.addEventListener('click', toggleCollapse);
    }
  }

}();
</script>
<?php

file_put_contents('output/index.html', ob_get_flush()); //The output dir
