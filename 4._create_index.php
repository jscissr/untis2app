<?php

$json = json_decode(file_get_contents('plans.json'), true);

//additional files like the cache manifest and images will have this base path (like the html <base>). Try ../ or /
$out_base = '';
//The folder of the plan files (note: $out_base is NOT prepended)
$out_plans_dir = 'plans/'; // [For appspot: '']
//The suffix of the plan files
$out_plans_suffix = '.htm'; // [For appspot: '']

$html = index_html_min($json, $out_base, $out_plans_dir, $out_plans_suffix);

echo $html;
file_put_contents('output/index.htm', $html); //The output dir [For appspot: 'appspot/index.htm']



function index_html($json, $out_base, $out_plans_dir, $out_plans_suffix){
ob_start();

?>
<!doctype html>
<meta charset="utf-8">
<title>Timetable App</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="icon" sizes="1024x1024" type="image/png" href="<?php echo $out_base; ?>img/1024.png">
<link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/favicon.svg">
<style>
body {
  font-family: sans-serif;
  padding: 0;
  margin: 0;
}
h1 {
  font-size: 1.5em;
  margin: 20px;
}
p{
  padding:0 30px;
}
a {
  color: black;
  text-decoration: none;
}
ul a {
  display: block;
  padding: 4px 0;
}
ul {
  list-style: none;
  padding: 0 40px;
}
ul ul {
  padding: 0 0 0 30px;
}
#footer {
  background: #f5f5f5;
  padding: 30px;
}
</style>

<h1>Timetable App</h1>
<p>Tip: Add an icon to your homescreen - Menu &gt; To homescreen
<ul>
<?php

foreach($json as $class){
  echo '  <li><a href="' . $out_plans_dir . $class['file'] . $out_plans_suffix . '">' . $class['name'] . '</a>
';
  if(!empty($class['childs'])){
    echo '    <ul>
';
    foreach($class['childs'] as $child){
      echo '      <li><a href="' . $out_plans_dir . $child['file'] . $out_plans_suffix . '">' . $child['name'] . '</a>
';
    }
    echo '    </ul>
';
  }
}

?>
</ul>
<div id="footer">
  <a href="https://github.com/jscissr/untis2app">GitHub</a>
</div><?php

return ob_get_clean();
}



function index_html_min($json, $out_base, $out_plans_dir, $out_plans_suffix){
ob_start();

?>
<!doctype html><meta charset="utf-8"><title>Timetable App</title><meta name="viewport" content="width=device-width,initial-scale=1.0"><?php ?>
<link rel="icon" sizes="1024x1024" type="image/png" href="<?php echo $out_base; ?>img/1024.png"><link rel="icon" sizes="any" type="image/svg+xml" href="<?php echo $out_base; ?>img/favicon.svg"><?php ?>
<style>body{font-family:sans-serif;padding:0;margin:0;}h1{font-size:1.5em;margin:20px;}p{padding:0 30px;}a{color:black;text-decoration:none;}ul a{display:block;padding:4px 0;}ul{list-style:none;padding:0 40px;}ul ul{padding:0 0 0 30px;}#footer{background:#f5f5f5;padding:30px;}</style><?php ?>
<h1>Timetable App</h1><p>Tip: Add an icon to your homescreen - Menu &gt; To homescreen<ul><?php

foreach($json as $class){
  echo '<li><a href="' . $out_plans_dir . $class['file'] . $out_plans_suffix . '">' . $class['name'] . '</a>';
  if(!empty($class['childs'])){
    echo '<ul>';
    foreach($class['childs'] as $child){
      echo '<li><a href="' . $out_plans_dir . $child['file'] . $out_plans_suffix . '">' . $child['name'] . '</a>';
    }
    echo '</ul>';
  }
}

?>
</ul><?php ?>
<div id="footer"><a href="https://github.com/jscissr/untis2app">GitHub</a></div><?php

return ob_get_clean();
}
